<?php
/**
 * 管理员端 - 保存系统选项 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/validator.php';

requireRole('admin');
Security::requireCSRFToken();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

$data = requireAdminPasswordConfirmation();
$id = Validator::cleanInt($data['id'] ?? 0, 0);
$type = Validator::cleanString($data['type'] ?? '', 50);
$value = Validator::cleanString($data['value'] ?? '', 200);

$validTypes = ['college', 'grade', 'class', 'political_status', 'development_time', 'ethnicity'];

if (!Validator::validateInArray($type, $validTypes)) {
    echo json_encode(['success' => false, 'message' => '无效的选项类型']);
    exit;
}

if ($value === '') {
    echo json_encode(['success' => false, 'message' => '请输入选项值']);
    exit;
}

$db = Database::getInstance();

try {
    if ($id > 0) {
        $existing = $db->fetchOne("SELECT id, type, value, sort_order FROM system_options WHERE id = ?", [$id]);
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => '选项不存在']);
            exit;
        }

        $duplicate = $db->fetchOne(
            "SELECT id FROM system_options WHERE type = ? AND value = ? AND id != ?",
            [$type, $value, $id]
        );
        if ($duplicate) {
            echo json_encode(['success' => false, 'message' => '该选项值已存在']);
            exit;
        }

        $db->execute("UPDATE system_options SET value = ? WHERE id = ?", [$value, $id]);

        $logDetails = [
            'option_type' => $type,
            'changes' => [
                createLogFieldChange('value', '选项值', $existing['value'], $value)
            ],
            'sort_order' => (int) $existing['sort_order']
        ];
        logAdminSensitiveOperation('update', 'system_option', $id, "更新系统选项: {$type} = {$value}", $logDetails, $logDetails, 'admin_update_option', 1);

        echo json_encode([
            'success' => true,
            'message' => '选项已更新',
            'data' => [
                'id' => (int) $id,
                'type' => $type,
                'value' => $value,
            ],
        ]);
        exit;
    }

    $duplicate = $db->fetchOne(
        "SELECT id FROM system_options WHERE type = ? AND value = ?",
        [$type, $value]
    );
    if ($duplicate) {
        echo json_encode(['success' => false, 'message' => '该选项值已存在']);
        exit;
    }

    $maxSort = $db->fetchOne(
        "SELECT MAX(sort_order) AS max_sort FROM system_options WHERE type = ?",
        [$type]
    );
    $sortOrder = ((int) ($maxSort['max_sort'] ?? 0)) + 1;

    $db->execute(
        "INSERT INTO system_options (type, value, sort_order, created_at) VALUES (?, ?, ?, NOW())",
        [$type, $value, $sortOrder]
    );
    $newId = (int) $db->lastInsertId();

    $logDetails = [
        'option_type' => $type,
        'value' => $value,
        'sort_order' => $sortOrder
    ];
    logAdminSensitiveOperation('create', 'system_option', $newId, "添加系统选项: {$type} = {$value}", $logDetails, $logDetails, 'admin_create_option', 1);

    echo json_encode([
        'success' => true,
        'message' => '选项已添加',
        'data' => [
            'id' => $newId,
            'type' => $type,
            'value' => $value,
        ],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()]);
}
