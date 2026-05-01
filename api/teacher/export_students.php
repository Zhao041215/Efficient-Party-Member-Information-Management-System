<?php
/**
 * 导出学生信息 CSV
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['teacher', 'admin']);

$db = Database::getInstance();
$filters = [
    'keyword' => $_GET['keyword'] ?? '',
    'grade' => normalizeFilterValues($_GET['grade'] ?? []),
    'class' => normalizeFilterValues($_GET['class'] ?? []),
    'political_status' => normalizeFilterValues($_GET['political_status'] ?? []),
    'gender' => normalizeFilterValues($_GET['gender'] ?? [])
];

$where = ['si.info_completed = 1'];
$params = [];

if ($filters['keyword'] !== '') {
    $where[] = "(si.student_no LIKE ? OR si.name LIKE ?)";
    $params[] = '%' . $filters['keyword'] . '%';
    $params[] = '%' . $filters['keyword'] . '%';
}

if (!empty($filters['grade'])) {
    appendMultiSelectFilter($where, $params, 'si.grade', $filters['grade']);
}

if (!empty($filters['class'])) {
    appendMultiSelectFilter($where, $params, 'si.class', $filters['class']);
}

if (!empty($filters['political_status'])) {
    appendPoliticalStatusFilter($where, $params, 'si.political_status', $filters['political_status']);
}

if (!empty($filters['gender'])) {
    appendMultiSelectFilter($where, $params, 'si.gender', $filters['gender']);
}

$whereClause = implode(' AND ', $where);
$students = $db->fetchAll(
    "SELECT si.* FROM student_info si WHERE $whereClause ORDER BY si.grade, si.class, si.student_no",
    $params
);

logOperation('export', 'student_info', null, '导出学生信息', [
    'export_type' => 'student_info',
    'filters' => $filters,
    'result_count' => count($students)
]);

$filename = '学生信息_' . date('YmdHis') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

fputcsv($output, [
    '学号', '姓名', '性别', '民族', '身份证号', '出生日期', '年龄',
    '联系方式', '邮箱', '学院', '年级', '班级', '家庭住址',
    '政治面貌', '预计毕业时间', '入团时间', '递交入党申请书时间',
    '确定积极分子时间', '确定预备党员时间', '转正时间'
]);

foreach ($students as $student) {
    fputcsv($output, [
        $student['student_no'],
        $student['name'],
        $student['gender'],
        $student['ethnicity'],
        $student['id_card'],
        $student['birth_date'],
        calculateAgeFromIdCard($student['id_card']),
        $student['phone'],
        $student['email'],
        $student['college'],
        $student['grade'],
        $student['class'],
        $student['address'],
        $student['political_status'],
        $student['graduation_year'],
        $student['join_league_date'],
        $student['apply_party_date'],
        $student['activist_date'],
        $student['probationary_date'],
        $student['full_member_date']
    ]);
}

fclose($output);
exit;
