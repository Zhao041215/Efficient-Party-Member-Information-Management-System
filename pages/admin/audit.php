<?php
/**
 * 管理员端信息审核 - 含实时更新
 */
$pageTitle = '信息审核 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'superadmin']);

$db = Database::getInstance();
$status = $_GET['status'] ?? 'pending';

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

$statusMap = [
    'pending' => ['label' => '待审核', 'class' => 'warning'],
    'approved' => ['label' => '已通过', 'class' => 'success'],
    'rejected' => ['label' => '已拒绝', 'class' => 'danger']
];
?>

<script src="/assets/js/realtime.js"></script>

<div class="card">
    <?php renderFeedbackTipAlert(); ?>
    
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-clipboard-check"></i> 信息审核</h3>
    </div>
    <div class="card-body">
        <div class="status-tabs">
            <a href="?status=pending" class="status-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clock"></i> 待审核 <span class="badge badge-warning" id="pendingBadge"><?php echo count($status === 'pending' ? $batches : []); ?></span>
            </a>
            <a href="?status=approved" class="status-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">
                <i class="fa-solid fa-check"></i> 已通过
            </a>
            <a href="?status=rejected" class="status-tab <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                <i class="fa-solid fa-times"></i> 已拒绝
            </a>
        </div>
        
        <?php if ($status === 'pending' && !empty($batches)): ?>
            <div class="batch-actions-top">
                <button class="btn btn-success btn-sm" onclick="batchApprove()">
                    <i class="fa-solid fa-check-double"></i> 批量通过
                </button>
                <button class="btn btn-danger btn-sm" onclick="batchReject()">
                    <i class="fa-solid fa-times"></i> 批量拒绝
                </button>
            </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table" id="auditTable">
                <thead>
                    <tr>
                        <?php if ($status === 'pending'): ?>
                            <th><input type="checkbox" id="checkAll" onchange="toggleAll()"></th>
                        <?php endif; ?>
                        <th>学号</th>
                        <th>姓名</th>
                        <th>年级班级</th>
                        <th>修改项数</th>
                        <th>提交时间</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="auditTableBody">
                    <?php if (empty($batches)): ?>
                        <tr id="emptyRow">
                            <td colspan="<?php echo $status === 'pending' ? 8 : 7; ?>" class="text-center text-muted">
                                暂无<?php echo $statusMap[$status]['label']; ?>的申请
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($batches as $batch): ?>
                            <tr data-batch-id="<?php echo e($batch['batch_id']); ?>">
                                <?php if ($status === 'pending'): ?>
                                    <td><input type="checkbox" class="batch-check" value="<?php echo e($batch['batch_id']); ?>"></td>
                                <?php endif; ?>
                                <td><?php echo e($batch['student_no']); ?></td>
                                <td><?php echo e($batch['name']); ?></td>
                                <td><?php echo e($batch['grade']); ?> <?php echo e($batch['class']); ?></td>
                                <td><span class="badge badge-info"><?php echo $batch['change_count']; ?> 项</span></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($batch['created_at'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $statusMap[$status]['class']; ?>">
                                        <?php echo $statusMap[$status]['label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="viewDetail('<?php echo e($batch['batch_id']); ?>')">
                                        <i class="fa-solid fa-eye"></i> 查看
                                    </button>
                                    <?php if ($status === 'pending'): ?>
                                        <button class="btn btn-success btn-sm" onclick="approveOne('<?php echo e($batch['batch_id']); ?>')">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="rejectOne('<?php echo e($batch['batch_id']); ?>')">
                                            <i class="fa-solid fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-list-alt"></i> 修改详情</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailContent">
            <div class="loading-spinner">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
            </div>
        </div>
        <div class="modal-footer" id="detailFooter">
        </div>
    </div>
</div>

<style>
.status-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #eee;
}
.status-tab {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    color: #666;
    background: #f8f9fa;
    transition: all 0.2s;
    position: relative;
}
.status-tab:hover {
    background: #e9ecef;
}
.status-tab.active {
    background: #c41e3a;
    color: #fff;
}
.status-tab .badge {
    margin-left: 6px;
    font-size: 11px;
    padding: 2px 6px;
}
.batch-actions-top {
    margin-bottom: 16px;
    display: flex;
    gap: 8px;
}
.loading-spinner {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 200px;
    color: #c41e3a;
}
.change-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.change-item {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #c41e3a;
}
.change-field {
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 8px;
}
.change-values {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
}
.change-old {
    color: #dc3545;
    text-decoration: line-through;
}
.change-arrow {
    color: #666;
}
.change-new {
    color: #28a745;
    font-weight: 500;
}
.new-row-highlight {
    animation: highlightRow 2s;
}
@keyframes highlightRow {
    0% { background-color: #fff3cd; }
    100% { background-color: transparent; }
}
</style>

<script>
let currentBatchId = null;
let currentStatus = '<?php echo $status; ?>';
let auditUpdater = null;
let auditKnownBatchIds = new Set(<?php echo json_encode(array_column($batches, 'batch_id')); ?>);

function toggleAll() {
    const checkAll = document.getElementById('checkAll');
    document.querySelectorAll('.batch-check').forEach(cb => cb.checked = checkAll.checked);
}

function getSelectedBatchIds() {
    return Array.from(document.querySelectorAll('.batch-check:checked')).map(cb => cb.value);
}

async function viewDetail(batchId) {
    currentBatchId = batchId;
    const modal = document.getElementById('detailModal');
    const content = document.getElementById('detailContent');
    const footer = document.getElementById('detailFooter');
    
    modal.classList.add('active');
    content.innerHTML = '<div class="loading-spinner"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';
    
    try {
        const response = await Ajax.get('/api/admin/audit_detail.php?batch_id=' + batchId);
        
        if (response.success) {
            const data = response.data;
            let html = `
                <div class="mb-3">
                    <strong>学生：</strong>${data.student_no} - ${data.name}<br>
                    <strong>年级班级：</strong>${data.grade} ${data.class}<br>
                    <strong>提交时间：</strong>${data.created_at}
                </div>
                <div class="change-list">
            `;
            
            data.changes.forEach(change => {
                html += `
                    <div class="change-item">
                        <div class="change-field">${change.field_label}</div>
                        <div class="change-values">
                            <span class="change-old">${change.old_value || '(空)'}</span>
                            <span class="change-arrow"><i class="fa-solid fa-arrow-right"></i></span>
                            <span class="change-new">${change.new_value}</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            if (data.status === 'rejected' && data.reject_reason) {
                html += `
                    <div class="mt-3 p-3" style="background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 6px;">
                        <strong style="color: #856404;"><i class="fa-solid fa-exclamation-triangle"></i> 拒绝原因：</strong>
                        <div style="margin-top: 8px; color: #856404;">${data.reject_reason}</div>
                    </div>
                `;
            }
            
            content.innerHTML = html;
            
            if (data.status === 'pending') {
                footer.innerHTML = `
                    <button class="btn btn-secondary" onclick="closeModal()">关闭</button>
                    <button class="btn btn-danger" onclick="rejectOne('${batchId}')">拒绝</button>
                    <button class="btn btn-success" onclick="approveOne('${batchId}')">通过</button>
                `;
            } else {
                footer.innerHTML = `<button class="btn btn-secondary" onclick="closeModal()">关闭</button>`;
            }
        } else {
            content.innerHTML = '<div class="text-center text-danger">加载失败</div>';
        }
    } catch (error) {
        content.innerHTML = '<div class="text-center text-danger">网络错误</div>';
    }
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
}

document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function setPendingBadges(count) {
    const pendingBadge = document.getElementById('pendingBadge');
    if (pendingBadge) {
        pendingBadge.textContent = count;
    }

    const sidebarBadge = document.getElementById('sidebarPendingAuditBadge');
    if (sidebarBadge) {
        sidebarBadge.textContent = count;
        sidebarBadge.classList.toggle('d-none', count <= 0);
    }
}

function updateAuditTable(newBatches) {
    if (currentStatus !== 'pending') {
        return;
    }

    const tbody = document.getElementById('auditTableBody');
    if (!tbody) {
        return;
    }

    if (!Array.isArray(newBatches) || newBatches.length === 0) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="8" class="text-center text-muted">暂无待审核的申请</td></tr>';
        setPendingBadges(0);
        auditKnownBatchIds = new Set();
        return;
    }

    const nextIds = new Set(newBatches.map(batch => batch.batch_id));
    const addedIds = newBatches
        .filter(batch => !auditKnownBatchIds.has(batch.batch_id))
        .map(batch => batch.batch_id);

    tbody.innerHTML = newBatches.map(batch => createBatchRow(batch)).join('');
    addedIds.forEach(batchId => {
        const row = tbody.querySelector(`tr[data-batch-id="${batchId}"]`);
        if (row) {
            row.classList.add('new-row-highlight');
        }
    });

    auditKnownBatchIds = nextIds;
    setPendingBadges(newBatches.length);
}

function createBatchRow(batch) {
    const createdTime = new Date(batch.created_at).toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    return `
        <tr data-batch-id="${batch.batch_id}">
            <td><input type="checkbox" class="batch-check" value="${batch.batch_id}"></td>
            <td>${batch.student_no}</td>
            <td>${batch.name}</td>
            <td>${batch.grade} ${batch.class}</td>
            <td><span class="badge badge-info">${batch.change_count} 项</span></td>
            <td>${createdTime}</td>
            <td><span class="badge badge-warning">待审核</span></td>
            <td>
                <button class="btn btn-primary btn-sm" onclick="viewDetail('${batch.batch_id}')">
                    <i class="fa-solid fa-eye"></i> 查看
                </button>
                <button class="btn btn-success btn-sm" onclick="approveOne('${batch.batch_id}')">
                    <i class="fa-solid fa-check"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="rejectOne('${batch.batch_id}')">
                    <i class="fa-solid fa-times"></i>
                </button>
            </td>
        </tr>
    `;
}

async function refreshPendingAuditList() {
    if (currentStatus !== 'pending') {
        return;
    }

    try {
        const response = await fetch(`/api/admin/get_audit_list.php?status=pending&_t=${Date.now()}`, {
            method: 'GET',
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            return;
        }

        const result = await response.json();
        if (result.success && result.data && Array.isArray(result.data.batches)) {
            updateAuditTable(result.data.batches);
        }
    } catch (error) {
        // ignore refresh errors
    }
}

document.addEventListener('admin-pending-updated', function(event) {
    const count = parseInt(event.detail?.pending_count ?? 0, 10) || 0;
    setPendingBadges(count);
});

async function executeAuditAction(batchIds, action, rejectReason = '') {
    return Ajax.post('/api/admin/audit_action.php', {
        batch_ids: batchIds,
        action,
        reject_reason: rejectReason
    });
}

async function approveOne(batchId) {
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-check"></i> 通过申请',
        messageHtml: '<p>确定通过这条修改申请吗？</p>',
        confirmText: '确认通过',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/audit_action.php', {
                batch_ids: [batchId],
                action: 'approve',
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            Toast.success(response.message || '审核通过');
            closeModal();
            await refreshPendingAuditList();
            return true;
        }
    });
}

async function batchApprove() {
    const batchIds = getSelectedBatchIds();
    if (batchIds.length === 0) {
        Toast.warning('请选择要操作的申请');
        return;
    }

    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-check-double"></i> 批量通过申请',
        messageHtml: `<p>确定批量通过选中的 <strong>${batchIds.length}</strong> 个申请吗？</p>`,
        confirmText: '确认通过',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/audit_action.php', {
                batch_ids: batchIds,
                action: 'approve',
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            Toast.success(response.message);
            await refreshPendingAuditList();
            return true;
        }
    });
}

async function rejectOne(batchId) {
    closeModal();

    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-times-circle"></i> 拒绝申请',
        messageHtml: '<p>确定拒绝这条修改申请吗？</p>',
        extraContentHtml: `
            <div class="form-group" style="margin-top: 12px;">
                <label>拒绝原因</label>
                <textarea class="form-control" data-reject-reason rows="4" placeholder="请输入拒绝原因，可留空"></textarea>
            </div>
        `,
        confirmText: '确认拒绝',
        confirmButtonClass: 'btn-danger',
        onConfirm: async ({ adminPassword, overlay }) => {
            const rejectReason = overlay.querySelector('[data-reject-reason]')?.value.trim() || '';
            const response = await Ajax.post('/api/admin/audit_action.php', {
                batch_ids: [batchId],
                action: 'reject',
                reject_reason: rejectReason,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            Toast.success(response.message);
            await refreshPendingAuditList();
            return true;
        }
    });
}

async function batchReject() {
    const batchIds = getSelectedBatchIds();
    if (batchIds.length === 0) {
        Toast.warning('请选择要操作的申请');
        return;
    }

    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-times-circle"></i> 批量拒绝申请',
        messageHtml: `<p>确定拒绝选中的 <strong>${batchIds.length}</strong> 个申请吗？</p>`,
        extraContentHtml: `
            <div class="form-group" style="margin-top: 12px;">
                <label>拒绝原因</label>
                <textarea class="form-control" data-reject-reason rows="4" placeholder="请输入拒绝原因，可留空"></textarea>
            </div>
        `,
        confirmText: '确认拒绝',
        confirmButtonClass: 'btn-danger',
        onConfirm: async ({ adminPassword, overlay }) => {
            const rejectReason = overlay.querySelector('[data-reject-reason]')?.value.trim() || '';
            const response = await Ajax.post('/api/admin/audit_action.php', {
                batch_ids: batchIds,
                action: 'reject',
                reject_reason: rejectReason,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            Toast.success(response.message);
            await refreshPendingAuditList();
            return true;
        }
    });
}

if (currentStatus === 'pending') {
    auditUpdater = new RealtimeUpdater({
        interval: 5000,
        apiUrl: '/api/admin/get_audit_list.php?status=pending',
        onUpdate: function(data) {
            if (data.batches) {
                updateAuditTable(data.batches);
            }
        },
        onNewPending: function() {
            Toast.info('New pending audit request received');
        }
    });

    auditUpdater.start();

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            auditUpdater.stop();
        } else {
            auditUpdater.start();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
