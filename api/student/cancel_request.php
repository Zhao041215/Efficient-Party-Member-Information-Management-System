<?php
/**
 * 学生撤回修改申请API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json; charset=utf-8');

// 检查登录和角色
requireRole('student');

// CSRF验证
Security::requireCSRFToken();

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
$batchId = trim($input['batch_id'] ?? '');

if (empty($batchId)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

try {
    // 检查该批次是否属于当前学生且状态为待审核
    $request = $db->fetchOne("
        SELECT id, status 
        FROM info_change_requests 
        WHERE batch_id = ? AND user_id = ? 
        LIMIT 1
    ", [$batchId, $userId]);
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => '申请记录不存在']);
        exit;
    }
    
    if ($request['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => '该申请已处理，无法撤回']);
        exit;
    }
    
    // 删除该批次的所有记录
    $db->execute("
        DELETE FROM info_change_requests 
        WHERE batch_id = ? AND user_id = ?
    ", [$batchId, $userId]);
    
    // 记录操作日志
    logOperation('cancel', 'change_request', $userId, "撤回信息修改申请，批次ID: $batchId");
    
    echo json_encode(['success' => true, 'message' => '申请已撤回']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '操作失败，请稍后重试']);
}
