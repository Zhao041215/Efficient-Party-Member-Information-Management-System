<?php
/**
 * 教师端修改密码API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
requireRole(['teacher', 'admin']);
Security::requireCSRFToken();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法错误']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$oldPassword = $data['old_password'] ?? '';
$newPassword = $data['new_password'] ?? '';

// 验证
if (empty($oldPassword) || empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => '请填写完整信息']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => '新密码至少需要6位字符']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// 获取当前用户
$user = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);

if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

// 验证原密码
if (!password_verify($oldPassword, $user['password'])) {
    echo json_encode(['success' => false, 'message' => '原密码错误']);
    exit;
}

try {
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->execute("UPDATE users SET password = ?, is_first_login = 0, force_change_password = 0, updated_at = NOW() WHERE id = ?", [$hashedPassword, $userId]);
    
    logOperation('password', 'user', $userId, '教师修改登录密码');
    
    // 清除session，要求重新登录
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => '密码修改成功，请重新登录', 'require_relogin' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '修改失败']);
}
