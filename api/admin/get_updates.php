<?php
/**
 * 实时数据更新API
 * 路径: /api/admin/get_updates.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 验证登录
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$db = Database::getInstance();
$role = $_SESSION['role'];

try {
    // 获取待审核数量
    $pendingCount = $db->fetchOne(
        "SELECT COUNT(DISTINCT batch_id) as count FROM info_change_requests WHERE status = 'pending'"
    )['count'];
    
    // 获取活跃用户数（仅管理员）
    $totalUsers = 0;
    if ($role === 'admin') {
        $totalUsers = $db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE is_active = 1"
        )['count'];
    }
    
    // 获取学生总数
    $totalStudents = $db->fetchOne(
        "SELECT COUNT(*) as count FROM student_info WHERE info_completed = 1"
    )['count'];
    
    // 获取最新的审核记录（用于检测新提交）
    $latestBatch = $db->fetchOne(
        "SELECT batch_id, created_at FROM info_change_requests 
         WHERE status = 'pending' 
         ORDER BY created_at DESC LIMIT 1"
    );
    
    echo json_encode([
        'success' => true,
        'data' => [
            'pending_count' => (int)$pendingCount,
            'total_users' => (int)$totalUsers,
            'total_students' => (int)$totalStudents,
            'latest_batch_id' => $latestBatch['batch_id'] ?? null,
            'latest_batch_time' => $latestBatch['created_at'] ?? null,
            'timestamp' => time()
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '获取更新失败: ' . $e->getMessage()
    ]);
}
