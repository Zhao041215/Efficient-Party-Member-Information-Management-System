<?php
/**
 * 获取学生详细信息API
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

$student = $db->fetchOne("
    SELECT * FROM student_info WHERE id = ? AND info_completed = 1
", [$id]);

$student['age'] = calculateAgeFromIDCard($student['id_card']);

if (!$student) {
    echo json_encode(['success' => false, 'message' => '学生不存在']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $student
]);
