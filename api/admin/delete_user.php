<?php
/**
 * 管理员端 - 删除用户 API
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
    jsonResponse(['success' => false, 'message' => '您没有权限删除该用户']);
}

if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)) {
    jsonResponse(['success' => false, 'message' => '不能删除自己的账户']);
}

if ($user['role'] === 'superadmin') {
    $superadminCount = (int)$db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'superadmin'")['count'];
    if ($superadminCount <= 1) {
        jsonResponse(['success' => false, 'message' => '不能删除最后一个系统管理员']);
    }
}

try {
    $db->beginTransaction();

    $deletedStudentInfoCount = 0;
    $deletedChangeRequestCount = 0;

    if ($user['role'] === 'student') {
        $deletedStudentInfoCount = (int) $db->fetchOne("SELECT COUNT(*) AS count FROM student_info WHERE user_id = ?", [$userId])['count'];
        $deletedChangeRequestCount = (int) $db->fetchOne("SELECT COUNT(*) AS count FROM info_change_requests WHERE user_id = ?", [$userId])['count'];
        $db->execute("DELETE FROM student_info WHERE user_id = ?", [$userId]);
        $db->execute("DELETE FROM info_change_requests WHERE user_id = ?", [$userId]);
    }

    $db->execute("DELETE FROM users WHERE id = ?", [$userId]);

    $logDetails = [
        'username' => $user['username'],
        'name' => $user['name'],
        'role' => $user['role'],
        'deleted_student_info_count' => $deletedStudentInfoCount,
        'deleted_change_request_count' => $deletedChangeRequestCount
    ];
    logAdminSensitiveOperation('delete', 'user', $userId, "删除用户: {$user['username']} ({$user['name']})", $logDetails, $logDetails, 'admin_delete_user', 1);

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => '用户已删除'
    ]);
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['success' => false, 'message' => '删除失败：' . $e->getMessage()]);
}
