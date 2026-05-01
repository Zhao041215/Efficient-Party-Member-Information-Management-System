<?php
/**
 * 学生待审核列表页面
 */
$pageTitle = '待审核列表 - 生化学院党员信息管理系统';
$currentPage = 'pending_list';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// 检查角色
requireRole('student');

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// 获取该学生的所有修改申请（按批次分组）
$requests = $db->fetchAll("
    SELECT 
        batch_id,
        field_name,
        field_label,
        old_value,
        new_value,
        status,
        created_at,
        reviewed_at,
        reviewed_by
    FROM info_change_requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC
", [$userId]);

// 按批次分组
$batches = [];
foreach ($requests as $request) {
    $batchId = $request['batch_id'];
    if (!isset($batches[$batchId])) {
        $batches[$batchId] = [
            'batch_id' => $batchId,
            'status' => $request['status'],
            'created_at' => $request['created_at'],
            'reviewed_at' => $request['reviewed_at'],
            'fields' => []
        ];
    }
    $batches[$batchId]['fields'][] = [
        'field_name' => $request['field_name'],
        'field_label' => $request['field_label'],
        'old_value' => $request['old_value'],
        'new_value' => $request['new_value']
    ];
}

// 字段名称映射
$fieldLabels = getFieldLabels();

// 状态样式映射
$statusStyles = [
    'pending' => ['class' => 'badge-warning', 'text' => '待审核'],
    'approved' => ['class' => 'badge-success', 'text' => '已通过'],
    'rejected' => ['class' => 'badge-danger', 'text' => '已拒绝']
];
?>

<div class="card">
    <?php renderFeedbackTipAlert(); ?>
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-clock"></i> 信息修改记录</h3>
    </div>
    <div class="card-body">
        <?php if (empty($batches)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                <p>暂无信息修改记录</p>
                <a href="/pages/student/edit_info.php" class="btn btn-primary">
                    <i class="fa-solid fa-pen-to-square"></i> 去修改信息
                </a>
            </div>
        <?php else: ?>
            <div class="request-list">
                <?php foreach ($batches as $batch): ?>
                    <div class="request-card <?php echo $batch['status']; ?>">
                        <div class="request-header">
                            <div class="request-info">
                                <span class="badge <?php echo $statusStyles[$batch['status']]['class']; ?>">
                                    <?php echo $statusStyles[$batch['status']]['text']; ?>
                                </span>
                                <span class="request-time">
                                    <i class="fa-regular fa-clock"></i>
                                    提交于 <?php echo date('Y-m-d H:i', strtotime($batch['created_at'])); ?>
                                </span>
                            </div>
                            <?php if ($batch['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-danger" onclick="cancelRequest('<?php echo e($batch['batch_id']); ?>')">
                                    <i class="fa-solid fa-xmark"></i> 撤回申请
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="request-body">
                            <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>修改字段</th>
                                        <th>原值</th>
                                        <th>新值</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batch['fields'] as $field): ?>
                                        <tr>
                                            <td><?php echo e($field['field_label'] ?: ($fieldLabels[$field['field_name']] ?? $field['field_name'])); ?></td>
                                            <td class="old-value"><?php echo e($field['old_value'] ?: '(空)'); ?></td>
                                            <td class="new-value"><?php echo e($field['new_value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                        
                        <?php if ($batch['status'] !== 'pending' && $batch['reviewed_at']): ?>
                            <div class="request-footer">
                                <i class="fa-solid fa-check-circle"></i>
                                审核于 <?php echo date('Y-m-d H:i', strtotime($batch['reviewed_at'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}
.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}
.empty-state p {
    font-size: 1.1rem;
    margin-bottom: 20px;
}

.request-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.request-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
}
.request-card.pending {
    border-left: 4px solid #ffc107;
}
.request-card.approved {
    border-left: 4px solid #28a745;
}
.request-card.rejected {
    border-left: 4px solid #dc3545;
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
}
.request-info {
    display: flex;
    align-items: center;
    gap: 15px;
}
.request-time {
    color: #6c757d;
    font-size: 0.9rem;
}

.request-body {
    padding: 15px 20px;
}
.request-body .table {
    margin-bottom: 0;
}
.request-body .table th {
    background: #f8f9fa;
    font-weight: 500;
}
.old-value {
    color: #6c757d;
    text-decoration: line-through;
}
.new-value {
    color: #28a745;
    font-weight: 500;
}

.request-footer {
    padding: 12px 20px;
    background: #f8f9fa;
    border-top: 1px solid #e0e0e0;
    font-size: 0.9rem;
    color: #6c757d;
}
.request-footer.reject-reason {
    background: #fff5f5;
    color: #dc3545;
}
.request-footer i {
    margin-right: 8px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.875rem;
}

@media (max-width: 576px) {
    .request-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .request-info {
        width: 100%;
        flex-wrap: wrap;
        gap: 10px;
    }

    .request-body,
    .request-footer {
        padding-left: 16px;
        padding-right: 16px;
    }
}
</style>

<script>
function cancelRequest(batchId) {
    Modal.confirm('确定要撤回这个修改申请吗？撤回后需要重新提交。', async function() {
        try {
            const response = await Ajax.post('/api/student/cancel_request.php', { batch_id: batchId });
            
            if (response.success) {
                Toast.success(response.message);
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                Toast.error(response.message || '撤回失败');
            }
        } catch (error) {
            Toast.error('网络错误,请稍后重试');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
