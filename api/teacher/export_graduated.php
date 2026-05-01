<?php
/**
 * 导出毕业生信息 CSV
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['teacher', 'admin']);

$db = Database::getInstance();
$ids = $_GET['ids'] ?? '';
$filters = [
    'keyword' => $_GET['keyword'] ?? '',
    'graduation_year' => normalizeFilterValues($_GET['graduation_year'] ?? []),
    'political_status' => normalizeFilterValues($_GET['political_status'] ?? [])
];

if ($ids !== '') {
    $idArray = array_values(array_filter(array_map('intval', explode(',', $ids))));
    if (empty($idArray)) {
        echo '无效的 ID 参数';
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($idArray), '?'));
    $graduates = $db->fetchAll(
        "SELECT * FROM graduated_students WHERE id IN ($placeholders) ORDER BY graduation_year DESC, student_no",
        $idArray
    );
} else {
    $where = ['1=1'];
    $params = [];

    if ($filters['keyword'] !== '') {
        $where[] = "(student_no LIKE ? OR name LIKE ?)";
        $params[] = '%' . $filters['keyword'] . '%';
        $params[] = '%' . $filters['keyword'] . '%';
    }

    if (!empty($filters['graduation_year'])) {
        appendMultiSelectFilter($where, $params, 'graduation_year', $filters['graduation_year']);
    }

    if (!empty($filters['political_status'])) {
        appendPoliticalStatusFilter($where, $params, 'political_status', $filters['political_status']);
    }

    $whereClause = implode(' AND ', $where);
    $graduates = $db->fetchAll(
        "SELECT * FROM graduated_students WHERE $whereClause ORDER BY graduation_year DESC, student_no",
        $params
    );
}

logOperation('export', 'graduated_students', null, '导出毕业生信息', [
    'export_type' => 'graduated_students',
    'filters' => $ids !== '' ? ['ids' => $ids] : $filters,
    'result_count' => count($graduates)
]);

$filename = '毕业生信息_' . date('YmdHis') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

fputcsv($output, [
    '学号', '姓名', '性别', '民族', '身份证号', '出生日期',
    '联系方式', '邮箱', '学院', '原年级', '原班级', '家庭住址',
    '政治面貌', '毕业年份', '入团时间', '递交入党申请书时间',
    '确定积极分子时间', '确定预备党员时间', '转正时间', '归档时间'
]);

foreach ($graduates as $graduate) {
    fputcsv($output, [
        $graduate['student_no'],
        $graduate['name'],
        $graduate['gender'],
        $graduate['ethnicity'],
        $graduate['id_card'],
        $graduate['birth_date'],
        $graduate['phone'],
        $graduate['email'],
        $graduate['college'],
        $graduate['grade'],
        $graduate['class'],
        $graduate['address'],
        $graduate['political_status'],
        $graduate['graduation_year'],
        $graduate['join_league_date'],
        $graduate['apply_party_date'],
        $graduate['activist_date'],
        $graduate['probationary_date'],
        $graduate['full_member_date'],
        $graduate['graduated_at']
    ]);
}

fclose($output);
exit;
