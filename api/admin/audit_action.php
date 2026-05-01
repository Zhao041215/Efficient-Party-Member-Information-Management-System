<?php
/**
 * 管理员端 - 审核操作 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/validator.php';

requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

Security::requireCSRFToken();

$data = requireAdminPasswordConfirmation();
$action = Validator::cleanString($data['action'] ?? '', 20);
$batchIds = $data['batch_ids'] ?? [];
$rejectReason = Validator::cleanString($data['reject_reason'] ?? '', 500);

if (!Validator::validateInArray($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => '无效的操作']);
    exit;
}

if (!is_array($batchIds) || empty($batchIds)) {
    echo json_encode(['success' => false, 'message' => '请选择要审核的申请']);
    exit;
}

$batchIds = array_values(array_filter(array_map(function ($id) {
    return Validator::cleanString($id, 50);
}, $batchIds)));

if (empty($batchIds)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();
$adminId = (int) $_SESSION['user_id'];

try {
    $db->beginTransaction();

    $successCount = 0;
    $failCount = 0;

    foreach ($batchIds as $batchId) {
        $requests = $db->fetchAll(
            "SELECT icr.*, si.name AS student_name
             FROM info_change_requests icr
             JOIN student_info si ON icr.user_id = si.user_id
             WHERE icr.batch_id = ? AND icr.status = 'pending'",
            [$batchId]
        );

        if (empty($requests)) {
            $failCount++;
            continue;
        }

        $userId = (int) $requests[0]['user_id'];
        $studentNo = $requests[0]['student_no'];
        $studentName = $requests[0]['student_name'];
        $changeDetails = array_map(function ($request) {
            return createLogFieldChange(
                $request['field_name'],
                $request['field_label'],
                $request['old_value'],
                $request['new_value']
            );
        }, $requests);

        if ($action === 'approve') {
            foreach ($requests as $request) {
                $fieldName = $request['field_name'];
                $newValue = $request['new_value'];

                $db->execute(
                    "UPDATE student_info SET `{$fieldName}` = ?, updated_at = NOW() WHERE user_id = ?",
                    [$newValue, $userId]
                );
            }

            $db->execute(
                "UPDATE info_change_requests
                 SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?
                 WHERE batch_id = ?",
                [$adminId, $batchId]
            );

            $logDetails = [
                'batch_id' => $batchId,
                'student_no' => $studentNo,
                'student_name' => $studentName,
                'result' => 'approved',
                'change_count' => count($requests),
                'changes' => $changeDetails
            ];
            logAdminSensitiveOperation('audit', 'change_request', null, '审核通过信息变更申请', $logDetails, $logDetails, 'admin_audit_change_request', count($requests));
        } else {
            $db->execute(
                "UPDATE info_change_requests
                 SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, reject_reason = ?
                 WHERE batch_id = ?",
                [$adminId, $rejectReason, $batchId]
            );

            $logDetails = [
                'batch_id' => $batchId,
                'student_no' => $studentNo,
                'student_name' => $studentName,
                'result' => 'rejected',
                'reject_reason' => $rejectReason,
                'change_count' => count($requests),
                'changes' => $changeDetails
            ];
            logAdminSensitiveOperation('audit', 'change_request', null, '审核拒绝信息变更申请', $logDetails, $logDetails, 'admin_audit_change_request', count($requests));
        }

        $successCount++;
    }

    $db->commit();

    $actionText = $action === 'approve' ? '通过' : '拒绝';
    $message = "已{$actionText} {$successCount} 个申请";
    if ($failCount > 0) {
        $message .= "，{$failCount} 个失败（可能已被处理）";
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
}
