<?php
/**
 * 学生修改密码API
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
$currentPassword = $input['current_password'] ?? '';
$newPassword = $input['new_password'] ?? '';

// 验证输入
if (empty($currentPassword) || empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => '请填写完整信息']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => '新密码长度至少6位']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

try {
    // 获取当前用户信息
    $user = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户不存在']);
        exit;
    }
    
    // 验证当前密码
    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode(['success' => false, 'message' => '当前密码错误']);
        exit;
    }
    
    // 更新密码,同时清除首次登录和强制修改密码标记
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->execute("UPDATE users SET password = ?, is_first_login = 0, force_change_password = 0, updated_at = NOW() WHERE id = ?", [$hashedPassword, $userId]);
    
    // 记录操作日志
    logOperation('password', 'user', $userId, '学生修改登录密码');
    
    // 清除session，要求重新登录
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => '密码修改成功，请重新登录', 'require_relogin' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '操作失败，请稍后重试']);
}
