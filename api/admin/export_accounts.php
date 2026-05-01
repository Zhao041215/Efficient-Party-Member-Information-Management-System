<?php
/**
 * 管理员端 - 导出账户信息 CSV
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin', 'superadmin']);

try {
    $db = Database::getInstance();

    // 获取筛选参数（与accounts.php相同）
    $filters = [
        'keyword' => $_GET['keyword'] ?? '',
        'role' => normalizeFilterValues($_GET['role'] ?? []),
        'status' => normalizeFilterValues($_GET['status'] ?? []),
        'grade' => normalizeFilterValues($_GET['grade'] ?? []),
        'info_status' => normalizeFilterValues($_GET['info_status'] ?? []),
    ];

    // 构建查询条件（与accounts.php相同逻辑）
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['keyword'])) {
        $where[] = "(u.username LIKE ? OR u.name LIKE ?)";
        $params[] = '%' . $filters['keyword'] . '%';
        $params[] = '%' . $filters['keyword'] . '%';
    }

    if (!empty($filters['role'])) {
        appendMultiSelectFilter($where, $params, 'u.role', $filters['role']);
    }

    if (count($filters['status']) === 1) {
        $statusValue = $filters['status'][0] === 'active' ? 1 : 0;
        $where[] = "u.is_active = ?";
        $params[] = $statusValue;
    }

    if (!empty($filters['grade'])) {
        appendMultiSelectFilter($where, $params, 'si.grade', $filters['grade']);
    }

    if (count($filters['info_status']) === 1) {
        if ($filters['info_status'][0] === 'completed') {
            $where[] = "si.info_completed = 1";
        } else {
            $where[] = "(si.info_completed = 0 OR si.info_completed IS NULL)";
        }
    }

    $whereClause = implode(' AND ', $where);

    // 查询账户
    $accounts = $db->fetchAll(
        "SELECT u.username, u.name, u.role, u.is_active,
                si.gender, si.college, si.grade, si.class, si.info_completed, u.created_at
         FROM users u
         LEFT JOIN student_info si ON u.id = si.user_id
         WHERE $whereClause
         ORDER BY u.created_at DESC",
        $params
    );

    // 记录操作日志
    logOperation('export', 'accounts', null, '导出账户信息', [
        'export_type' => 'accounts',
        'filters' => $filters,
        'result_count' => count($accounts)
    ]);

    // 生成CSV
    $filename = '账户信息_' . date('YmdHis') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // UTF-8 BOM for Excel compatibility
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // CSV header
    fputcsv($output, [
        '用户名', '姓名', '角色', '性别', '学院', '年级', '班级',
        '账户状态', '信息状态', '创建时间'
    ]);

    // Role mapping
    $roleMap = [
        'student' => '学生',
        'teacher' => '教师',
        'admin' => '管理员',
        'superadmin' => '系统管理员',
    ];

    // Write data rows
    foreach ($accounts as $account) {
        fputcsv($output, [
            $account['username'],
            $account['name'] ?: '-',
            $roleMap[$account['role']] ?? $account['role'],
            $account['gender'] ?: '-',
            $account['college'] ?: '-',
            $account['grade'] ?: '-',
            $account['class'] ?: '-',
            $account['is_active'] ? '正常' : '禁用',
            ($account['role'] === 'student')
                ? ($account['info_completed'] ? '已完善' : '未完善')
                : '-',
            date('Y-m-d H:i:s', strtotime($account['created_at']))
        ]);
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    error_log('Export accounts error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(500);
    echo '导出失败：' . htmlspecialchars($e->getMessage());
    exit;
}
