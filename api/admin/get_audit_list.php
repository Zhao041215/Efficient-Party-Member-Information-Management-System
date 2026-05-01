<?php
/**
 * 获取审核列表（用于实时更新）
 * 路径: /api/admin/get_audit_list.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未授权']);
    exit;
}

$db = Database::getInstance();
$status = $_GET['status'] ?? 'pending';

try {
    $batches = $db->fetchAll("
        SELECT 
            icr.batch_id,
            icr.created_at,
            icr.student_no,
            si.name,
            si.grade,
            si.class,
            COUNT(*) as change_count,
            MAX(icr.status) as status
        FROM info_change_requests icr
        JOIN student_info si ON icr.user_id = si.user_id
        WHERE icr.status = ?
        GROUP BY icr.batch_id
        ORDER BY icr.created_at DESC
    ", [$status]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'batches' => $batches,
            'count' => count($batches),
            'timestamp' => time()
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '获取列表失败: ' . $e->getMessage()
    ]);
}
