<?php
/**
 * 管理员端 - 删除系统选项 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

requireRole('admin');
Security::requireCSRFToken();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

$data = requireAdminPasswordConfirmation();
$id = (int) ($data['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();
$option = $db->fetchOne("SELECT * FROM system_options WHERE id = ?", [$id]);

if (!$option) {
    echo json_encode(['success' => false, 'message' => '选项不存在']);
    exit;
}

try {
    $db->execute("DELETE FROM system_options WHERE id = ?", [$id]);

    $logDetails = [
        'option_type' => $option['type'],
        'value' => $option['value'],
        'sort_order' => (int) $option['sort_order']
    ];
    logAdminSensitiveOperation('delete', 'system_option', $id, "删除系统选项: {$option['type']} = {$option['value']}", $logDetails, $logDetails, 'admin_delete_option', 1);

    echo json_encode([
        'success' => true,
        'message' => '选项已删除',
        'data' => [
            'id' => $id,
            'type' => $option['type'],
            'value' => $option['value'],
        ],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '删除失败: ' . $e->getMessage()]);
}
