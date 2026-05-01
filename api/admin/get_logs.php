<?php
/**
 * Admin operation logs polling endpoint.
 */
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
sendNoCacheHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'superadmin') {
    http_response_code(401);
    jsonResponse(['success' => false, 'message' => 'Unauthorized']);
}

$db = Database::getInstance();
$hasDetailsColumn = operationLogHasDetailsColumn();
$hasFullDetailsTable = operationLogHasFullDetailsTable();

$filters = [
    'keyword' => trim($_GET['keyword'] ?? ''),
    'action' => normalizeFilterValues($_GET['action'] ?? []),
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to' => trim($_GET['date_to'] ?? '')
];

$where = ['1=1'];
$params = [];

if ($filters['keyword'] !== '') {
    $keywordParts = [
        'ol.username LIKE ?',
        'u.username LIKE ?',
        'ol.description LIKE ?'
    ];
    $keyword = '%' . $filters['keyword'] . '%';
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    if ($hasDetailsColumn) {
        $keywordParts[] = 'ol.details LIKE ?';
        $params[] = $keyword;
    }

    $where[] = '(' . implode(' OR ', $keywordParts) . ')';
}

if (!empty($filters['action'])) {
    appendMultiSelectFilter($where, $params, 'ol.action', $filters['action']);
}

if ($filters['date_from'] !== '') {
    $where[] = 'DATE(ol.created_at) >= ?';
    $params[] = $filters['date_from'];
}

if ($filters['date_to'] !== '') {
    $where[] = 'DATE(ol.created_at) <= ?';
    $params[] = $filters['date_to'];
}

$whereClause = implode(' AND ', $where);
$afterId = max(0, (int) ($_GET['after_id'] ?? 0));
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

try {
    $total = (int) $db->fetchOne("
        SELECT COUNT(*) AS count
        FROM operation_logs ol
        LEFT JOIN users u ON ol.user_id = u.id
        WHERE $whereClause
    ", $params)['count'];

    $latestRow = $db->fetchOne("
        SELECT MAX(ol.id) AS latest_id
        FROM operation_logs ol
        LEFT JOIN users u ON ol.user_id = u.id
        WHERE $whereClause
    ", $params);

    $detailsSelect = $hasDetailsColumn ? 'ol.details' : 'NULL AS details';
    $fullDetailsSelect = $hasFullDetailsTable ? 'olfd.id AS full_detail_id, olfd.detail_scope, olfd.detail_count' : 'NULL AS full_detail_id, NULL AS detail_scope, NULL AS detail_count';
    $fullDetailsJoin = $hasFullDetailsTable ? 'LEFT JOIN operation_log_full_details olfd ON olfd.operation_log_id = ol.id' : '';
    $newWhereClause = $whereClause . ' AND ol.id > ?';
    $newParams = array_merge($params, [$afterId]);

    $logs = $db->fetchAll("
        SELECT
            ol.id,
            ol.user_id,
            ol.username AS log_username,
            ol.action,
            ol.description,
            $detailsSelect,
            $fullDetailsSelect,
            ol.ip_address,
            ol.created_at,
            u.username AS account_username,
            u.name AS real_name
        FROM operation_logs ol
        LEFT JOIN users u ON ol.user_id = u.id
        $fullDetailsJoin
        WHERE $newWhereClause
        ORDER BY ol.created_at DESC, ol.id DESC
        LIMIT $limit
    ", $newParams);

    foreach ($logs as &$log) {
        $log['id'] = (int) $log['id'];
        $log['user_id'] = $log['user_id'] !== null ? (int) $log['user_id'] : null;
        $log['full_detail_id'] = $log['full_detail_id'] !== null ? (int) $log['full_detail_id'] : null;
        $log['detail_count'] = $log['detail_count'] !== null ? (int) $log['detail_count'] : 0;
        $log['created_at_display'] = date('Y-m-d H:i:s', strtotime($log['created_at']));
    }
    unset($log);

    jsonResponse([
        'success' => true,
        'data' => [
            'logs' => $logs,
            'total' => $total,
            'latest_id' => (int) ($latestRow['latest_id'] ?? 0),
            'timestamp' => time()
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    jsonResponse(['success' => false, 'message' => 'Failed to load logs']);
}
