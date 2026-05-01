<?php
/**
 * 管理员端 - 修改密码API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
requireRole('admin');
Security::requireCSRFToken();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$currentPassword = $data['current_password'] ?? '';
$newPassword = $data['new_password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

// 验证
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => '请填写所有字段']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => '两次输入的新密码不一致']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => '新密码长度不能少于6位']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// 获取当前用户
$user = $db->fetchOne("SELECT id, password FROM users WHERE id = ?", [$userId]);

if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

// 验证当前密码
if (!password_verify($currentPassword, $user['password'])) {
    echo json_encode(['success' => false, 'message' => '当前密码不正确']);
    exit;
}

try {
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->execute("UPDATE users SET password = ?, is_first_login = 0, force_change_password = 0, updated_at = NOW() WHERE id = ?", [$hashedPassword, $userId]);
    
    logOperation('password', 'user', $userId, '管理员修改自己的密码');
    
    // 清除session，要求重新登录
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => '密码修改成功，请重新登录', 'require_relogin' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '修改失败：' . $e->getMessage()]);
}
