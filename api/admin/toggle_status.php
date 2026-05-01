<?php
/**
 * 管理员端 - 启用/禁用账户API
 */
ob_start(); // 开启输出缓冲
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
requireRole('admin');

// 清除可能的错误输出
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

// CSRF验证
Security::requireCSRFToken();

$data = requireAdminPasswordConfirmation();
$userId = intval($data['user_id'] ?? 0);
$action = $data['action'] ?? '';

// 转换action为status值
$status = ($action === 'enable') ? 1 : 0;

if (!$userId || !in_array($action, ['enable', 'disable'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();

// 获取用户信息
$user = $db->fetchOne("SELECT id, username, name, role, is_active FROM users WHERE id = ?", [$userId]);

if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

// 权限检查：只能操作有权限管理的用户
if (!canManageUser($user['role'])) {
    echo json_encode(['success' => false, 'message' => '您没有权限操作该用户']);
    exit;
}

// 不能禁用自己
if ($user['id'] == $_SESSION['user_id'] && $status === 0) {
    echo json_encode(['success' => false, 'message' => '不能禁用自己的账户']);
    exit;
}

// 如果是系统管理员且要禁用，检查是否是最后一个激活的系统管理员
if ($user['role'] === 'superadmin' && $status === 0) {
    $activeCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'superadmin' AND is_active = 1")['count'];
    if ($activeCount <= 1) {
        echo json_encode(['success' => false, 'message' => '不能禁用最后一个系统管理员']);
        exit;
    }
}

// 状态相同不需要更新
if ($user['is_active'] == $status) {
    $statusText = $status === 1 ? '已启用' : '已禁用';
    echo json_encode(['success' => true, 'message' => "账户{$statusText}"]);
    exit;
}

try {
    $db->execute("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?", [$status, $userId]);
    
    $action = $status === 1 ? '启用' : '禁用';
    $logDetails = [
        'username' => $user['username'],
        'name' => $user['name'],
        'role' => $user['role'],
        'old_status' => (int) $user['is_active'],
        'new_status' => $status
    ];
    logAdminSensitiveOperation('update', 'user', $userId, "{$action}用户账户: {$user['username']} ({$user['name']})", $logDetails, $logDetails, 'admin_toggle_user_status', 1);
    
    echo json_encode([
        'success' => true,
        'message' => "账户已{$action}"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
}
exit; // 添加 exit 确保没有额外输出
