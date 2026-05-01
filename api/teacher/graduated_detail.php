<?php
/**
 * 获取毕业生详细信息API
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['teacher', 'admin']);

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();

$graduate = $db->fetchOne("SELECT * FROM graduated_students WHERE id = ?", [$id]);

if (!$graduate) {
    echo json_encode(['success' => false, 'message' => '毕业生记录不存在']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $graduate
]);
