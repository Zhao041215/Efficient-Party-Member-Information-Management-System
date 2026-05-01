<?php
/**
 * 教师端 - 批量导出选中学生信息（CSV）
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['teacher', 'admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$studentIds = $data['student_ids'] ?? [];

if (!is_array($studentIds) || empty($studentIds)) {
    echo json_encode(['success' => false, 'message' => '请选择要导出的学生']);
    exit;
}

$studentIds = array_values(array_filter(array_map('intval', $studentIds)));
if (empty($studentIds)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();

try {
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $students = $db->fetchAll(
        "SELECT
            student_no, name, gender, college, grade, class,
            birth_date, ethnicity, id_card, address, phone, email,
            political_status, age, join_league_date, apply_party_date,
            activist_date, probationary_date, full_member_date, graduation_year
         FROM student_info
         WHERE id IN ($placeholders) AND info_completed = 1
         ORDER BY grade, class, student_no",
        $studentIds
    );

    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => '没有找到符合条件的学生']);
        exit;
    }

    $filename = '批量学生信息_' . date('YmdHis') . '.csv';
    $filepath = __DIR__ . '/../../uploads/' . $filename;

    if (!is_dir(__DIR__ . '/../../uploads/')) {
        mkdir(__DIR__ . '/../../uploads/', 0755, true);
    }

    $fp = fopen($filepath, 'w');
    fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($fp, [
        '学号', '姓名', '性别', '学院', '年级', '班级',
        '出生日期', '民族', '身份证号', '家庭住址', '联系方式', '邮箱',
        '政治面貌', '年龄', '入团时间', '递交入党申请书时间',
        '确定积极分子时间', '确定预备党员时间', '转正时间', '预计毕业时间'
    ]);

    foreach ($students as $student) {
        fputcsv($fp, [
            $student['student_no'],
            $student['name'],
            $student['gender'],
            $student['college'],
            $student['grade'],
            $student['class'],
            $student['birth_date'],
            $student['ethnicity'],
            $student['id_card'],
            $student['address'],
            $student['phone'],
            $student['email'],
            $student['political_status'],
            calculateAgeFromIdCard($student['id_card']),
            $student['join_league_date'],
            $student['apply_party_date'],
            $student['activist_date'],
            $student['probationary_date'],
            $student['full_member_date'],
            $student['graduation_year']
        ]);
    }

    fclose($fp);

    logOperation('export', 'student', null, '批量导出学生信息', [
        'export_type' => 'student_batch',
        'filters' => [],
        'requested_count' => count($studentIds),
        'result_count' => count($students)
    ]);

    echo json_encode([
        'success' => true,
        'message' => '导出成功',
        'filename' => $filename,
        'download_url' => '/uploads/' . $filename
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '导出失败，请稍后重试']);
}
