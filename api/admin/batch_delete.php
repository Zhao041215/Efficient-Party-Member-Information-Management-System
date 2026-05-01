<?php
/**
 * 管理员端 - 批量删除用户 API
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
    jsonResponse(['success' => false, 'message' => '请选择要删除的用户']);
}

$userIds = array_values(array_filter(array_map('intval', $userIds)));
if (empty($userIds)) {
    jsonResponse(['success' => false, 'message' => '参数错误']);
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
if (in_array($currentUserId, $userIds, true)) {
    jsonResponse(['success' => false, 'message' => '不能删除自己的账户']);
}

$db = Database::getInstance();
$placeholders = implode(',', array_fill(0, count($userIds), '?'));
$usersToDelete = $db->fetchAll("SELECT id, username, name, role FROM users WHERE id IN ($placeholders)", $userIds);

$unauthorizedUsers = [];
$superadminCount = 0;

foreach ($usersToDelete as $user) {
    if (!canManageUser($user['role'])) {
        $unauthorizedUsers[] = $user['username'];
    }
    if ($user['role'] === 'superadmin') {
        $superadminCount++;
    }
}

if (!empty($unauthorizedUsers)) {
    jsonResponse([
        'success' => false,
        'message' => '您没有权限删除以下用户：' . implode(', ', $unauthorizedUsers)
    ]);
}

if ($superadminCount > 0) {
    $totalSuperadmins = (int) $db->fetchOne("SELECT COUNT(*) AS count FROM users WHERE role = 'superadmin'")['count'];
    if ($totalSuperadmins <= $superadminCount) {
        jsonResponse(['success' => false, 'message' => '不能删除所有系统管理员']);
    }
}

try {
    $db->beginTransaction();

    $successCount = 0;
    $deletedTargets = [];

    foreach ($usersToDelete as $user) {
        $deletedStudentInfoCount = 0;
        $deletedChangeRequestCount = 0;

        if ($user['role'] === 'student') {
            $deletedStudentInfoCount = (int) $db->fetchOne("SELECT COUNT(*) AS count FROM student_info WHERE user_id = ?", [$user['id']])['count'];
            $deletedChangeRequestCount = (int) $db->fetchOne("SELECT COUNT(*) AS count FROM info_change_requests WHERE user_id = ?", [$user['id']])['count'];
            $db->execute("DELETE FROM student_info WHERE user_id = ?", [$user['id']]);
            $db->execute("DELETE FROM info_change_requests WHERE user_id = ?", [$user['id']]);
        }

        $db->execute("DELETE FROM users WHERE id = ?", [$user['id']]);
        $successCount++;
        $deletedTargets[] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'role' => $user['role'],
            'deleted_student_info_count' => $deletedStudentInfoCount,
            'deleted_change_request_count' => $deletedChangeRequestCount
        ];
    }

    logAdminSensitiveOperation('delete', 'user', null, '批量删除用户', [
        'requested_count' => count($userIds),
        'success_count' => $successCount,
        'fail_count' => max(0, count($userIds) - $successCount),
        'targets' => limitLogTargets($deletedTargets)
    ], $deletedTargets, 'admin_batch_delete_users', count($deletedTargets));

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => "已删除 {$successCount} 个用户"
    ]);
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['success' => false, 'message' => '删除失败：' . $e->getMessage()]);
}
