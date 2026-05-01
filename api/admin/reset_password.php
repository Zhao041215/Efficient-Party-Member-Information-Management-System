<?php
/**
 * 管理员端 - 重置密码 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/validator.php';

requireRole('admin');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不正确']);
}

Security::requireCSRFToken();

$data = requireAdminPasswordConfirmation(getPostData());
$userId = Validator::cleanInt($data['user_id'] ?? 0, 1);

if (!$userId) {
    jsonResponse(['success' => false, 'message' => '参数错误']);
}

$db = Database::getInstance();
$user = $db->fetchOne("SELECT id, username, name, role FROM users WHERE id = ?", [$userId]);

if (!$user) {
    jsonResponse(['success' => false, 'message' => '用户不存在']);
}

if (!canManageUser($user['role'])) {
    jsonResponse(['success' => false, 'message' => '您没有权限重置该用户密码']);
}

if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)) {
    jsonResponse(['success' => false, 'message' => '不能通过此方式重置自己的密码']);
}

try {
    $newPassword = password_hash($user['username'], PASSWORD_DEFAULT);
    $db->execute(
        "UPDATE users SET password = ?, is_first_login = 1, force_change_password = 1, updated_at = NOW() WHERE id = ?",
        [$newPassword, $userId]
    );

    $logDetails = [
        'username' => $user['username'],
        'name' => $user['name'],
        'role' => $user['role'],
        'force_change_password' => true,
        'reset_to_username' => true
    ];
    logAdminSensitiveOperation('password', 'user', $userId, "重置用户密码: {$user['username']} ({$user['name']})", $logDetails, $logDetails, 'admin_reset_password', 1);

    jsonResponse([
        'success' => true,
        'message' => '密码已重置为用户名'
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => '重置失败：' . $e->getMessage()]);
}
