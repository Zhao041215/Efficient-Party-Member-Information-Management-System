<?php
/**
 * 管理员端 - 审核详情API
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

$batchId = $_GET['batch_id'] ?? '';

if (empty($batchId)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();

// 获取批次信息
$batch = $db->fetchOne("
    SELECT 
        icr.*,
        si.name,
        si.grade,
        si.class
    FROM info_change_requests icr
    JOIN student_info si ON icr.user_id = si.user_id
    WHERE icr.batch_id = ?
    LIMIT 1
", [$batchId]);

if (!$batch) {
    echo json_encode(['success' => false, 'message' => '记录不存在']);
    exit;
}

// 获取所有变更项
$changes = $db->fetchAll("
    SELECT field_name, field_label, old_value, new_value
    FROM info_change_requests
    WHERE batch_id = ?
    ORDER BY id
", [$batchId]);

echo json_encode([
    'success' => true,
    'data' => [
        'student_no' => $batch['student_no'],
        'name' => $batch['name'],
        'grade' => $batch['grade'],
        'class' => $batch['class'],
        'status' => $batch['status'],
        'created_at' => date('Y-m-d H:i', strtotime($batch['created_at'])),
        'reject_reason' => $batch['reject_reason'] ?? null,
        'changes' => $changes
    ]
]);
