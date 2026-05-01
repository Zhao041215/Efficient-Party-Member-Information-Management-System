<?php
/**
 * 更新反馈状态API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json; charset=utf-8');

requireRole(['admin', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不允许']);
}

Security::requireCSRFToken();

$data = requireAdminPasswordConfirmation(getPostData());

$feedbackId = intval($data['feedback_id'] ?? 0);
$status = $data['status'] ?? '';

if ($feedbackId <= 0) {
    jsonResponse(['success' => false, 'message' => '无效的反馈ID']);
}

if (!in_array($status, ['pending', 'processing', 'resolved', 'closed'])) {
    jsonResponse(['success' => false, 'message' => '无效的状态']);
}

$db = Database::getInstance();
$feedback = $db->fetchOne("SELECT id, title, status FROM feedback WHERE id = ?", [$feedbackId]);

if (!$feedback) {
    jsonResponse(['success' => false, 'message' => '反馈不存在']);
}

try {
    $sql = "UPDATE feedback SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $db->getConnection()->prepare($sql);
    $result = $stmt->execute([$status, $feedbackId]);
    
    if ($result) {
        $logDetails = [
            'title' => $feedback['title'],
            'old_status' => $feedback['status'],
            'new_status' => $status
        ];
        logAdminSensitiveOperation('feedback_status', 'feedback', $feedbackId, '更新反馈状态：' . $feedback['title'], $logDetails, $logDetails, 'admin_feedback_status', 1);

        jsonResponse([
            'success' => true,
            'message' => '状态更新成功'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => '状态更新失败']);
    }
} catch (Exception $e) {
    error_log("Feedback status update error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '系统错误']);
}
