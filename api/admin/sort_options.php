<?php
/**
 * 管理员端 - 排序系统选项 API
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

$data = requireAdminPasswordConfirmation();
$items = $data['items'] ?? [];

if (!is_array($items) || empty($items)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    $ids = array_values(array_filter(array_map(function ($item) {
        return (int) ($item['id'] ?? 0);
    }, $items)));
    $existingOptions = [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        foreach ($db->fetchAll("SELECT id, type, value, sort_order FROM system_options WHERE id IN ($placeholders)", $ids) as $option) {
            $existingOptions[(int) $option['id']] = $option;
        }
    }
    $sortTargets = [];

    foreach ($items as $item) {
        $id = (int) ($item['id'] ?? 0);
        $sortOrder = (int) ($item['sort_order'] ?? 0);

        if ($id > 0) {
            $existing = $existingOptions[$id] ?? null;
            $db->execute("UPDATE system_options SET sort_order = ? WHERE id = ?", [$sortOrder, $id]);
            $sortTargets[] = [
                'id' => $id,
                'option_type' => $existing['type'] ?? null,
                'value' => $existing['value'] ?? null,
                'old_sort_order' => $existing ? (int) $existing['sort_order'] : null,
                'new_sort_order' => $sortOrder
            ];
        }
    }

    logAdminSensitiveOperation('sort_options', 'system_option', 0, '调整系统选项排序', [
        'total_count' => count($items),
        'targets' => limitLogTargets($sortTargets)
    ], $sortTargets, 'admin_sort_options', count($sortTargets));

    $db->commit();

    echo json_encode(['success' => true, 'message' => '排序已保存']);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => '保存失败：' . $e->getMessage()]);
}
