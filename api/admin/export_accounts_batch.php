<?php
/**
 * 管理员端 - 批量导出选中账户信息 CSV
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin', 'superadmin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userIds = $data['user_ids'] ?? [];

if (!is_array($userIds) || empty($userIds)) {
    echo json_encode(['success' => false, 'message' => '请选择要导出的账户']);
    exit;
}

$userIds = array_values(array_filter(array_map('intval', $userIds)));
if (empty($userIds)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();

try {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $accounts = $db->fetchAll(
        "SELECT u.username, u.name, u.role, u.is_active,
                si.gender, si.college, si.grade, si.class, si.info_completed, u.created_at
         FROM users u
         LEFT JOIN student_info si ON u.id = si.user_id
         WHERE u.id IN ($placeholders)
         ORDER BY u.created_at DESC",
        $userIds
    );

    if (empty($accounts)) {
        echo json_encode(['success' => false, 'message' => '没有找到符合条件的账户']);
        exit;
    }

    $filename = '批量账户信息_' . date('YmdHis') . '.csv';
    $filepath = __DIR__ . '/../../uploads/' . $filename;

    if (!is_dir(__DIR__ . '/../../uploads/')) {
        mkdir(__DIR__ . '/../../uploads/', 0755, true);
    }

    $fp = fopen($filepath, 'w');
    fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    // CSV header
    fputcsv($fp, [
        '用户名', '姓名', '角色', '性别', '学院', '年级', '班级',
        '账户状态', '信息状态', '创建时间'
    ]);

    $roleMap = [
        'student' => '学生',
        'teacher' => '教师',
        'admin' => '管理员',
        'superadmin' => '系统管理员',
    ];

    foreach ($accounts as $account) {
        fputcsv($fp, [
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

    fclose($fp);

    logOperation('export', 'accounts', null, '批量导出账户信息', [
        'export_type' => 'accounts_batch',
        'requested_count' => count($userIds),
        'result_count' => count($accounts)
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
