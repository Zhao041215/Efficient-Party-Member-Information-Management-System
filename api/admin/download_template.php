<?php
/**
 * 管理员端 - 下载用户导入模板 API
 */
require_once __DIR__ . '/../../includes/auth.php';

requireRole('admin');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="user_import_template.csv"');

echo "\xEF\xBB\xBF";

$headers = ['用户名', '姓名', '角色', '性别', '学院', '年级', '班级'];
echo implode(',', $headers) . "\n";

$examples = [
    ['2024001', '张三', '学生', '男', '生物化学与分子生物学学院', '2024', '生物技术1班'],
    ['2024002', '李四', '学生', '女', '生物化学与分子生物学学院', '2024', '生物技术2班'],
    ['T001', '王老师', '教师', '', '', '', ''],
    ['A001', '管理员', '管理员', '', '', '', '']
];

foreach ($examples as $row) {
    echo implode(',', $row) . "\n";
}

logOperation('export', 'template', null, '下载用户导入模板', [
    'export_type' => 'user_import_template',
    'filters' => [],
    'result_count' => 0
]);
