<?php
/**
 * 管理员端 - 批量重置密码 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

requireRole('admin');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不正确']);
}

Security::requireCSRFToken();

$data = requireAdminPasswordConfirmation(getPostData());
$userIds = $data['user_ids'] ?? [];

if (!is_array($userIds) || empty($userIds)) {
    jsonResponse(['success' => false, 'message' => '请选择要操作的用户']);
}

$userIds = array_values(array_filter(array_map('intval', $userIds)));
if (empty($userIds)) {
    jsonResponse(['success' => false, 'message' => '参数错误']);
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
if (in_array($currentUserId, $userIds, true)) {
    jsonResponse(['success' => false, 'message' => '不能通过批量操作重置自己的密码']);
}

$db = Database::getInstance();
$placeholders = implode(',', array_fill(0, count($userIds), '?'));
$usersToReset = $db->fetchAll("SELECT id, username, name, role FROM users WHERE id IN ($placeholders)", $userIds);

$unauthorizedUsers = [];
foreach ($usersToReset as $user) {
    if (!canManageUser($user['role'])) {
        $unauthorizedUsers[] = $user['username'];
    }
}

if (!empty($unauthorizedUsers)) {
    jsonResponse([
        'success' => false,
        'message' => '您没有权限重置以下用户的密码：' . implode(', ', $unauthorizedUsers)
    ]);
}

try {
    $db->beginTransaction();

    $successCount = 0;
    $targets = [];

    foreach ($usersToReset as $user) {
        $newPassword = password_hash($user['username'], PASSWORD_DEFAULT);
        $db->execute(
            "UPDATE users SET password = ?, is_first_login = 1, force_change_password = 1, updated_at = NOW() WHERE id = ?",
            [$newPassword, $user['id']]
        );

        $successCount++;
        $targets[] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'role' => $user['role']
        ];
    }

    logAdminSensitiveOperation('password', 'user', null, '批量重置用户密码', [
        'requested_count' => count($userIds),
        'success_count' => $successCount,
        'fail_count' => max(0, count($userIds) - $successCount),
        'reset_to_username' => true,
        'force_change_password' => true,
        'targets' => limitLogTargets($targets)
    ], $targets, 'admin_batch_reset_passwords', count($targets));

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => "已重置 {$successCount} 个账户的密码为用户名"
    ]);
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
}
