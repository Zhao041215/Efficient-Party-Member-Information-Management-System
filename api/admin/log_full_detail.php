<?php
/**
 * 管理员端 - 操作日志完整明细查询
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json; charset=utf-8');

requireRole(['superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => '请求方法不正确']);
}

$logId = (int) ($_GET['id'] ?? 0);
if ($logId <= 0) {
    jsonResponse(['success' => false, 'message' => '参数错误']);
}

if (!operationLogHasFullDetailsTable()) {
    jsonResponse(['success' => false, 'message' => '完整明细表不存在']);
}

$db = Database::getInstance();
$detail = $db->fetchOne(
    "SELECT id, operation_log_id, detail_scope, detail_count, details_json, created_at
     FROM operation_log_full_details
     WHERE operation_log_id = ?
     ORDER BY id DESC
     LIMIT 1",
    [$logId]
);

if (!$detail) {
    jsonResponse(['success' => false, 'message' => '完整明细不存在']);
}

$decoded = decodeLogDetails($detail['details_json']);

jsonResponse([
    'success' => true,
    'data' => [
        'id' => (int) $detail['id'],
        'operation_log_id' => (int) $detail['operation_log_id'],
        'detail_scope' => $detail['detail_scope'],
        'detail_count' => (int) $detail['detail_count'],
        'created_at' => $detail['created_at'],
        'details' => $decoded
    ]
]);
