<?php
/**
 * 下载学生信息导入模板
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('superadmin');

logOperation('export', 'template', null, '下载学生信息导入模板', [
    'export_type' => 'student_import_template',
    'filters' => [],
    'result_count' => 0
]);

$filename = '学生信息导入模板.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

fputcsv($output, [
    '学号', '姓名', '性别', '学院', '年级', '班级',
    '民族', '身份证号', '出生日期', '联系方式', '邮箱', '家庭住址',
    '政治面貌', '毕业时间', '入团时间', '递交入党申请书时间',
    '确定积极分子时间', '确定预备党员时间', '转正时间'
]);

fputcsv($output, [
    '2021001', '张三', '男', '生化学院', '2021级', '1班',
    '汉族', '110101200001011234', '2000-01-01', '13800138000', 'zhangsan@example.com', '北京市朝阳区xxx',
    '共青团员', '2025年', '2015-05-04', '2021-09-01',
    '2022-03-01', '', ''
]);

fclose($output);
exit;
