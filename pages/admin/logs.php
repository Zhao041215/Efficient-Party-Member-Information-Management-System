<?php
/**
 * Superadmin operation logs.
 */
if (!function_exists('adminLogsText')) {
    function adminLogsText($value) {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }
}

$pageTitle = adminLogsText('&#25805;&#20316;&#26085;&#24535; - &#29983;&#21270;&#23398;&#38498;&#20826;&#21592;&#20449;&#24687;&#31649;&#29702;&#31995;&#32479;');

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superadmin']);

require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

$db = Database::getInstance();
$hasDetailsColumn = false;
$hasFullDetailsTable = false;

try {
    $hasDetailsColumn = (bool) $db->fetchOne("SHOW COLUMNS FROM operation_logs LIKE 'details'");
} catch (Exception $e) {
    $hasDetailsColumn = false;
}

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
    $where[] = "DATE(ol.created_at) >= ?";
    $params[] = $filters['date_from'];
}

if ($filters['date_to'] !== '') {
    $where[] = "DATE(ol.created_at) <= ?";
    $params[] = $filters['date_to'];
}

$whereClause = implode(' AND ', $where);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$total = (int) $db->fetchOne("
    SELECT COUNT(*) AS count
    FROM operation_logs ol
    LEFT JOIN users u ON ol.user_id = u.id
    WHERE $whereClause
", $params)['count'];

$totalPages = max(1, (int) ceil($total / $perPage));
$offset = ($page - 1) * $perPage;
$detailsSelect = $hasDetailsColumn ? 'ol.details' : 'NULL AS details';
$fullDetailsSelect = $hasFullDetailsTable ? 'olfd.id AS full_detail_id, olfd.detail_scope, olfd.detail_count' : 'NULL AS full_detail_id, NULL AS detail_scope, NULL AS detail_count';
$fullDetailsJoin = $hasFullDetailsTable ? 'LEFT JOIN operation_log_full_details olfd ON olfd.operation_log_id = ol.id' : '';
$latestLogRow = $db->fetchOne("
    SELECT MAX(ol.id) AS latest_id
    FROM operation_logs ol
    LEFT JOIN users u ON ol.user_id = u.id
    WHERE $whereClause
", $params);
$latestLogId = (int) ($latestLogRow['latest_id'] ?? 0);

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
    WHERE $whereClause
    ORDER BY ol.created_at DESC, ol.id DESC
    LIMIT $perPage OFFSET $offset
", $params);

$actions = $db->fetchAll("SELECT DISTINCT action FROM operation_logs ORDER BY action");

$actionMap = [
    'login' => ['label' => adminLogsText('&#30331;&#24405;'), 'class' => 'info'],
    'logout' => ['label' => adminLogsText('&#36864;&#20986;'), 'class' => 'secondary'],
    'create' => ['label' => adminLogsText('&#26032;&#22686;'), 'class' => 'success'],
    'update' => ['label' => adminLogsText('&#26356;&#26032;'), 'class' => 'warning'],
    'delete' => ['label' => adminLogsText('&#21024;&#38500;'), 'class' => 'danger'],
    'export' => ['label' => adminLogsText('&#23548;&#20986;'), 'class' => 'primary'],
    'import' => ['label' => adminLogsText('&#23548;&#20837;'), 'class' => 'primary'],
    'audit' => ['label' => adminLogsText('&#23457;&#26680;'), 'class' => 'info'],
    'password' => ['label' => adminLogsText('&#23494;&#30721;'), 'class' => 'warning'],
    'fill_info' => ['label' => adminLogsText('&#23436;&#21892;&#20449;&#24687;'), 'class' => 'success'],
    'submit_change_request' => ['label' => adminLogsText('&#25552;&#20132;&#20462;&#25913;'), 'class' => 'warning'],
    'update_email' => ['label' => adminLogsText('&#20462;&#25913;&#37038;&#31665;'), 'class' => 'warning'],
    'bind_email' => ['label' => adminLogsText('&#32465;&#23450;&#37038;&#31665;'), 'class' => 'success'],
    'send_email_code' => ['label' => adminLogsText('&#21457;&#36865;&#39564;&#35777;&#30721;'), 'class' => 'secondary'],
    'cancel' => ['label' => adminLogsText('&#21462;&#28040;'), 'class' => 'secondary'],
    'sort_options' => ['label' => adminLogsText('&#25490;&#24207;'), 'class' => 'secondary'],
    'feedback_submit' => ['label' => adminLogsText('&#25552;&#20132;&#21453;&#39304;'), 'class' => 'success'],
    'feedback_reply' => ['label' => adminLogsText('&#21453;&#39304;&#22238;&#22797;'), 'class' => 'info'],
    'feedback_status' => ['label' => adminLogsText('&#21453;&#39304;&#29366;&#24577;'), 'class' => 'warning']
];

$queryFilters = array_filter($filters, static function ($value) {
    if (is_array($value)) {
        return !empty($value);
    }
    return $value !== '';
});

$paginationBase = '?' . http_build_query($queryFilters);
$paginationBase .= empty($queryFilters) ? 'page=' : '&page=';
?>

<div class="card">
    <?php renderFeedbackTipAlert(); ?>
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-history"></i> &#25805;&#20316;&#26085;&#24535;</h3>
    </div>
    <div class="card-body">
        <form class="filter-form" method="get">
            <div class="filter-row">
                <div class="filter-item">
                    <input
                        type="text"
                        class="form-control"
                        name="keyword"
                        placeholder="&#29992;&#25143;&#21517; / &#25688;&#35201; / &#26085;&#24535;&#35814;&#24773;"
                        value="<?php echo e($filters['keyword']); ?>"
                    >
                </div>
                <div class="filter-item">
                    <select class="form-control filter-multi-select" name="action[]" multiple data-placeholder="&#20840;&#37096;&#25805;&#20316;">
                        <?php foreach ($actions as $action): ?>
                            <?php $actionName = $action['action']; ?>
                            <option value="<?php echo e($actionName); ?>" <?php echo in_array($actionName, $filters['action'], true) ? 'selected' : ''; ?>>
                                <?php echo e($actionMap[$actionName]['label'] ?? $actionName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <input type="date" class="form-control" name="date_from" value="<?php echo e($filters['date_from']); ?>">
                </div>
                <div class="filter-item">
                    <input type="date" class="form-control" name="date_to" value="<?php echo e($filters['date_to']); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> &#25628;&#32034;</button>
                    <a href="/pages/admin/logs.php" class="btn btn-secondary"><i class="fa-solid fa-undo"></i> &#37325;&#32622;</a>
                </div>
            </div>
        </form>

        <div class="result-info">
            &#20849; <strong id="logsTotalCount"><?php echo $total; ?></strong> &#26465;&#26085;&#24535;
            <?php if ($page === 1): ?>
                <span class="realtime-status" id="logRealtimeStatus"><i class="fa-solid fa-arrows-rotate"></i> &#23454;&#26102;&#26356;&#26032;&#20013;</span>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table" id="operationLogsTable">
                <thead>
                    <tr>
                        <th>&#26102;&#38388;</th>
                        <th>&#29992;&#25143;</th>
                        <th>&#25805;&#20316;</th>
                        <th>&#25688;&#35201;</th>
                        <th>&#26085;&#24535;&#35814;&#24773;</th>
                        <th>IP&#22320;&#22336;</th>
                    </tr>
                </thead>
                <tbody id="operationLogsTableBody">
                    <?php if (empty($logs)): ?>
                        <tr id="emptyLogsRow">
                            <td colspan="6" class="text-center text-muted">&#26242;&#26080;&#26085;&#24535;</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php $actionInfo = $actionMap[$log['action']] ?? ['label' => $log['action'], 'class' => 'secondary']; ?>
                            <?php $displayUsername = $log['log_username'] ?: $log['account_username']; ?>
                            <tr data-log-id="<?php echo (int) $log['id']; ?>">
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <?php if (!empty($displayUsername)): ?>
                                        <?php echo e($displayUsername); ?>
                                        <?php if (!empty($log['real_name'])): ?>
                                            <small class="text-muted">(<?php echo e($log['real_name']); ?>)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">&#31995;&#32479;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo e($actionInfo['class']); ?>">
                                        <?php echo e($actionInfo['label']); ?>
                                    </span>
                                </td>
                                <td class="desc-cell" title="<?php echo e($log['description'] ?: '-'); ?>"><?php echo e($log['description'] ?: '-'); ?></td>
                                <td class="log-details-cell">
                                    <?php if (!empty($log['full_detail_id'])): ?>
                                        <button type="button" class="btn btn-secondary btn-sm full-log-detail-btn" data-log-id="<?php echo (int) $log['id']; ?>" data-detail-count="<?php echo (int) $log['detail_count']; ?>">
                                            &#26597;&#30475;&#23436;&#25972;&#26126;&#32454;<?php echo !empty($log['detail_count']) ? ' (' . (int) $log['detail_count'] . ')' : ''; ?>
                                        </button>
                                        <div class="full-log-detail-panel" id="fullLogDetail<?php echo (int) $log['id']; ?>" style="display: none;"></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo e($log['ip_address']); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrapper">
                <?php echo generatePagination($page, $totalPages, $paginationBase); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.filter-form { background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 20px; }
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.filter-item { min-width: 150px; }
.filter-actions { display: flex; gap: 8px; }
.result-info { padding: 12px 0; color: #666; border-bottom: 1px solid #eee; margin-bottom: 16px; }
.log-details-cell { min-width: 300px; max-width: 420px; }
.desc-cell { max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.full-log-detail-btn { white-space: nowrap; }
.full-log-detail-panel { margin-top: 8px; max-height: 360px; overflow: auto; padding: 10px 12px; background: #fff; border: 1px solid #e9ecef; border-radius: 8px; }
.full-log-detail-panel pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: 12px; line-height: 1.5; }
.realtime-status { display: inline-flex; align-items: center; gap: 5px; margin-left: 12px; padding: 3px 8px; border-radius: 999px; background: #e8f5ee; color: #1f7a3f; font-size: 12px; font-weight: 600; vertical-align: middle; }
.realtime-status i { font-size: 11px; }
.new-log-row-highlight { animation: newLogRowHighlight 2.4s ease-out; }
@keyframes newLogRowHighlight {
    0% { background-color: #fff3cd; }
    100% { background-color: transparent; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.filter-multi-select').select2({
        width: '100%',
        language: 'zh-CN',
        allowClear: true,
        closeOnSelect: false
    });

    document.querySelectorAll('.full-log-detail-btn').forEach(function(button) {
        button.addEventListener('click', async function() {
            const logId = this.dataset.logId;
            const panel = document.getElementById('fullLogDetail' + logId);
            if (!panel) {
                return;
            }

            if (panel.style.display !== 'none') {
                panel.style.display = 'none';
                return;
            }

            panel.style.display = 'block';
            if (panel.dataset.loaded === '1') {
                return;
            }

            panel.innerHTML = '<span class="text-muted">加载中...</span>';
            try {
                const response = await fetch('/api/admin/log_full_detail.php?id=' + encodeURIComponent(logId), {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!result.success) {
                    panel.innerHTML = '<span class="text-danger">' + escapeLogDetailText(result.message || '加载失败') + '</span>';
                    return;
                }
                panel.innerHTML = '<pre>' + escapeLogDetailText(JSON.stringify(result.data.details, null, 2)) + '</pre>';
                panel.dataset.loaded = '1';
            } catch (error) {
                panel.innerHTML = '<span class="text-danger">加载失败</span>';
            }
        });
    });
});

function escapeLogDetailText(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', bootOperationLogRealtime);

function bootOperationLogRealtime() {
    const config = {
        enabled: <?php echo $page === 1 ? 'true' : 'false'; ?>,
        latestId: <?php echo $latestLogId; ?>,
        filters: <?php echo json_encode($filters, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        actionMap: <?php echo json_encode($actionMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        perPage: <?php echo (int) $perPage; ?>,
        interval: 5000
    };

    if (!config.enabled) {
        return;
    }

    const tbody = document.getElementById('operationLogsTableBody');
    if (!tbody) {
        return;
    }

    let timer = null;
    let inFlight = false;
    let latestId = config.latestId;
    const knownIds = new Set(
        Array.from(tbody.querySelectorAll('tr[data-log-id]'))
            .map(row => parseInt(row.dataset.logId || '0', 10))
            .filter(Boolean)
    );

    async function checkForNewLogs() {
        if (inFlight) {
            return;
        }

        inFlight = true;
        try {
            const params = new URLSearchParams();
            params.set('after_id', String(latestId));
            params.set('limit', String(config.perPage));

            if (config.filters.keyword) {
                params.set('keyword', config.filters.keyword);
            }
            if (Array.isArray(config.filters.action)) {
                config.filters.action.forEach(action => params.append('action[]', action));
            }
            if (config.filters.date_from) {
                params.set('date_from', config.filters.date_from);
            }
            if (config.filters.date_to) {
                params.set('date_to', config.filters.date_to);
            }
            params.set('_t', String(Date.now()));

            const response = await fetch('/api/admin/get_logs.php?' + params.toString(), {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.status === 401) {
                window.location.href = '/';
                return;
            }
            if (!response.ok) {
                return;
            }

            const result = await response.json();
            if (!result.success || !result.data) {
                return;
            }

            if (Number.isInteger(result.data.latest_id) && result.data.latest_id > latestId) {
                latestId = result.data.latest_id;
            }

            if (Number.isInteger(result.data.total)) {
                updateLogTotalCount(result.data.total);
            }

            const newLogs = Array.isArray(result.data.logs)
                ? result.data.logs.filter(log => log && log.id && !knownIds.has(log.id))
                : [];

            if (newLogs.length > 0) {
                insertNewLogRows(newLogs);
            }
        } catch (error) {
            // Polling should recover quietly on the next tick.
        } finally {
            inFlight = false;
        }
    }

    function insertNewLogRows(logs) {
        const emptyRow = document.getElementById('emptyLogsRow');
        if (emptyRow) {
            emptyRow.remove();
        }

        tbody.insertAdjacentHTML('afterbegin', logs.map(log => createOperationLogRow(log, config.actionMap)).join(''));
        logs.forEach(log => {
            knownIds.add(log.id);
            const row = tbody.querySelector('tr[data-log-id="' + log.id + '"]');
            if (row) {
                row.classList.add('new-log-row-highlight');
                bindRealtimeDetailButtons(row);
            }
        });

        const rows = Array.from(tbody.querySelectorAll('tr[data-log-id]'));
        rows.slice(config.perPage).forEach(row => {
            const id = parseInt(row.dataset.logId || '0', 10);
            if (id) {
                knownIds.delete(id);
            }
            row.remove();
        });
    }

    checkForNewLogs();
    timer = setInterval(checkForNewLogs, config.interval);

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        } else if (!timer) {
            checkForNewLogs();
            timer = setInterval(checkForNewLogs, config.interval);
        }
    });
}

function bindRealtimeDetailButtons(container) {
    container.querySelectorAll('.full-log-detail-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            toggleRealtimeFullLogDetail(button);
        });
    });
}

async function toggleRealtimeFullLogDetail(button) {
    const logId = button.dataset.logId;
    const panel = document.getElementById('fullLogDetail' + logId);
    if (!panel) {
        return;
    }

    if (panel.style.display !== 'none') {
        panel.style.display = 'none';
        return;
    }

    panel.style.display = 'block';
    if (panel.dataset.loaded === '1') {
        return;
    }

    panel.innerHTML = '<span class="text-muted">\u52a0\u8f7d\u4e2d...</span>';
    try {
        const response = await fetch('/api/admin/log_full_detail.php?id=' + encodeURIComponent(logId), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (!result.success) {
            panel.innerHTML = '<span class="text-danger">' + escapeLogDetailText(result.message || '\u52a0\u8f7d\u5931\u8d25') + '</span>';
            return;
        }
        panel.innerHTML = '<pre>' + escapeLogDetailText(JSON.stringify(result.data.details, null, 2)) + '</pre>';
        panel.dataset.loaded = '1';
    } catch (error) {
        panel.innerHTML = '<span class="text-danger">\u52a0\u8f7d\u5931\u8d25</span>';
    }
}

function createOperationLogRow(log, actionMap) {
    const actionInfo = actionMap[log.action] || { label: log.action || '-', class: 'secondary' };
    const displayUsername = log.log_username || log.account_username || '';
    const description = log.description || '-';
    const detailCount = parseInt(log.detail_count || 0, 10) || 0;
    const detailHtml = log.full_detail_id
        ? '<button type="button" class="btn btn-secondary btn-sm full-log-detail-btn" data-log-id="' + escapeLogDetailText(log.id) + '" data-detail-count="' + escapeLogDetailText(detailCount) + '">' +
            '\u67e5\u770b\u5b8c\u6574\u660e\u7ec6' + (detailCount ? ' (' + escapeLogDetailText(detailCount) + ')' : '') +
          '</button><div class="full-log-detail-panel" id="fullLogDetail' + escapeLogDetailText(log.id) + '" style="display: none;"></div>'
        : '<span class="text-muted">-</span>';

    let userHtml = '<span class="text-muted">\u7cfb\u7edf</span>';
    if (displayUsername) {
        userHtml = escapeLogDetailText(displayUsername);
        if (log.real_name) {
            userHtml += ' <small class="text-muted">(' + escapeLogDetailText(log.real_name) + ')</small>';
        }
    }

    return [
        '<tr data-log-id="' + escapeLogDetailText(log.id) + '">',
            '<td>' + escapeLogDetailText(log.created_at_display || log.created_at || '') + '</td>',
            '<td>' + userHtml + '</td>',
            '<td><span class="badge badge-' + escapeLogDetailText(actionInfo.class || 'secondary') + '">' + escapeLogDetailText(actionInfo.label || log.action || '-') + '</span></td>',
            '<td class="desc-cell" title="' + escapeLogDetailText(description) + '">' + escapeLogDetailText(description) + '</td>',
            '<td class="log-details-cell">' + detailHtml + '</td>',
            '<td><small>' + escapeLogDetailText(log.ip_address || '') + '</small></td>',
        '</tr>'
    ].join('');
}

function updateLogTotalCount(total) {
    const element = document.getElementById('logsTotalCount');
    if (element) {
        element.textContent = total;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
