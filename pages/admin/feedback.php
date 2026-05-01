<?php
/**
 * 管理员反馈管理页面
 */
$pageTitle = '反馈管理 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// 检查角色
requireRole(['admin', 'superadmin']);

$db = Database::getInstance();

// 获取筛选参数
$type = normalizeFilterValues($_GET['type'] ?? []);
$status = normalizeFilterValues($_GET['status'] ?? []);

// 构建查询
$sql = "SELECT f.*, u.username, u.name as user_name, u.role 
        FROM feedback f 
        LEFT JOIN users u ON f.user_id = u.id 
        WHERE 1=1";
$params = [];

if (!empty($type)) {
    $typeWhere = [];
    appendMultiSelectFilter($typeWhere, $params, 'f.type', $type);
    $sql .= " AND " . implode(' AND ', $typeWhere);
}

if (!empty($status)) {
    $statusWhere = [];
    appendMultiSelectFilter($statusWhere, $params, 'f.status', $status);
    $sql .= " AND " . implode(' AND ', $statusWhere);
}

$sql .= " ORDER BY f.created_at DESC";

$feedbackList = $db->fetchAll($sql, $params);

// 统计数据
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN type = 'bug' THEN 1 ELSE 0 END) as bugs,
        SUM(CASE WHEN type = 'suggestion' THEN 1 ELSE 0 END) as suggestions
    FROM feedback
");
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
    border-radius: 12px;
    padding: 20px;
    color: white;
    text-align: center;
}

.stat-card.warning {
    background: linear-gradient(135deg, #d4475c 0%, #c41e3a 100%);
}

.stat-card.success {
    background: linear-gradient(135deg, #d4a756 0%, #b45309 100%);
}

.stat-card.info {
    background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    margin: 10px 0;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
}

.filter-bar {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-label {
    font-weight: 500;
    color: #666;
}

.filter-select {
    padding: 8px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
}

.feedback-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.feedback-row {
    border-bottom: 1px solid #f0f0f0;
    padding: 20px;
    transition: background 0.2s;
}

.feedback-row:hover {
    background: #f8f9fa;
}

.feedback-row:last-child {
    border-bottom: none;
}

.feedback-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
}

.feedback-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.feedback-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.feedback-content {
    color: #666;
    line-height: 1.6;
    margin-bottom: 12px;
}

.feedback-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
}

.feedback-info {
    font-size: 13px;
    color: #999;
}

.feedback-actions {
    display: flex;
    gap: 10px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: #c41e3a;
    color: white;
}

.btn-primary:hover {
    background: #5568d3;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

/* 模态框样式 */
#replyModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: var(--z-modal-backdrop, 1040);
    align-items: center;
    justify-content: center;
}

#replyModal.active {
    display: flex;
}

#replyModal .modal-content {
    background: white;
    border-radius: 12px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

#replyModal .modal-header {
    margin-bottom: 20px;
}

#replyModal .modal-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
}

#replyModal .modal-body {
    margin-bottom: 20px;
}

#replyModal .modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
</style>

<div class="card">
    <?php renderFeedbackTipAlert(); ?>
    
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-comments"></i> 反馈管理</h3>
    </div>
    
    <div class="card-body">
        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">总反馈数</div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">待处理</div>
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card info">
                <div class="stat-label">处理中</div>
                <div class="stat-number"><?php echo $stats['processing']; ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">已解决</div>
                <div class="stat-number"><?php echo $stats['resolved']; ?></div>
            </div>
        </div>

        <!-- 筛选栏 -->
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">类型：</span>
                <select class="filter-select filter-multi-select" id="typeFilter" multiple data-placeholder="全部">
                    <option value="bug" <?php echo in_array('bug', $type, true) ? 'selected' : ''; ?>>Bug</option>
                    <option value="suggestion" <?php echo in_array('suggestion', $type, true) ? 'selected' : ''; ?>>建议</option>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">状态：</span>
                <select class="filter-select filter-multi-select" id="statusFilter" multiple data-placeholder="全部">
                    <option value="pending" <?php echo in_array('pending', $status, true) ? 'selected' : ''; ?>>待处理</option>
                    <option value="processing" <?php echo in_array('processing', $status, true) ? 'selected' : ''; ?>>处理中</option>
                    <option value="resolved" <?php echo in_array('resolved', $status, true) ? 'selected' : ''; ?>>已解决</option>
                    <option value="closed" <?php echo in_array('closed', $status, true) ? 'selected' : ''; ?>>已关闭</option>
                </select>
            </div>
            <button type="button" class="btn-sm btn-primary" onclick="applyFilter()">
                <i class="fa-solid fa-search"></i> 筛选
            </button>
            <a href="/pages/admin/feedback.php" class="btn-sm btn-secondary">
                <i class="fa-solid fa-undo"></i> 重置
            </a>
        </div>

        <!-- 反馈列表 -->
        <div class="feedback-table">
            <?php if (empty($feedbackList)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <p>暂无反馈记录</p>
                </div>
            <?php else: ?>
                <?php foreach ($feedbackList as $feedback): ?>
                    <div class="feedback-row">
                        <div class="feedback-header">
                            <div>
                                <div class="feedback-title"><?php echo e($feedback['title']); ?></div>
                                <div class="feedback-meta">
                                    <span class="feedback-type-badge <?php echo $feedback['type']; ?>">
                                        <?php echo $feedback['type'] === 'bug' ? 'Bug' : '建议'; ?>
                                    </span>
                                    <span class="feedback-status-badge <?php echo $feedback['status']; ?>">
                                        <?php 
                                        $statusMap = [
                                            'pending' => '待处理',
                                            'processing' => '处理中',
                                            'resolved' => '已解决',
                                            'closed' => '已关闭'
                                        ];
                                        echo $statusMap[$feedback['status']];
                                        ?>
                                    </span>
                                    <span>提交人：<?php echo e($feedback['user_name']); ?></span>
                                    <?php if ($feedback['contact']): ?>
                                        <span>联系方式：<?php echo e($feedback['contact']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="feedback-content">
                            <?php echo nl2br(e($feedback['content'])); ?>
                        </div>
                        
                        <?php if ($feedback['type'] === 'bug' && ($feedback['device'] || $feedback['device_model'] || $feedback['bug_time'] || $feedback['screenshot'])): ?>
                            <div class="feedback-bug-info" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #ff6b6b;">
                                <div style="font-weight: 600; color: #333; margin-bottom: 10px;">
                                    <i class="fa-solid fa-info-circle"></i> Bug详细信息
                                </div>
                                <?php if ($feedback['device']): ?>
                                    <div style="margin-bottom: 8px; color: #666;">
                                        <i class="fa-solid fa-mobile-screen"></i> 设备类型：<?php echo e($feedback['device']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($feedback['device_model']): ?>
                                    <div style="margin-bottom: 8px; color: #666;">
                                        <i class="fa-solid fa-laptop"></i> 设备机型：<?php echo e($feedback['device_model']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($feedback['bug_time']): ?>
                                    <div style="margin-bottom: 8px; color: #666;">
                                        <i class="fa-solid fa-clock"></i> Bug出现时间：<?php echo date('Y-m-d H:i', strtotime($feedback['bug_time'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($feedback['screenshot']): ?>
                                    <div style="margin-top: 12px;">
                                        <div style="font-weight: 500; color: #666; margin-bottom: 8px;">
                                            <i class="fa-solid fa-image"></i> 截图：
                                        </div>
                                        <a href="/<?php echo e($feedback['screenshot']); ?>" target="_blank">
                                            <img src="/<?php echo e($feedback['screenshot']); ?>" 
                                                 alt="Bug截图" 
                                                 style="max-width: 100%; max-height: 300px; border-radius: 8px; cursor: pointer; border: 2px solid #e0e0e0;">
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($feedback['admin_reply']): ?>
                            <div class="feedback-reply">
                                <div class="feedback-reply-label">
                                    <i class="fa-solid fa-reply"></i> 管理员回复：
                                </div>
                                <div><?php echo nl2br(e($feedback['admin_reply'])); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="feedback-footer">
                            <div class="feedback-info">
                                <i class="fa-solid fa-clock"></i> 
                                <?php echo date('Y-m-d H:i:s', strtotime($feedback['created_at'])); ?>
                            </div>
                            <div class="feedback-actions">
                                <button class="btn-sm btn-primary" data-feedback-id="<?php echo (int) $feedback['id']; ?>" data-feedback-title="<?php echo e($feedback['title']); ?>" onclick="replyFeedbackFromButton(this)">
                                    <i class="fa-solid fa-reply"></i> 回复
                                </button>
                                <button class="btn-sm btn-success" onclick="updateStatus(<?php echo (int) $feedback['id']; ?>, 'processing')">
                                    处理中
                                </button>
                                <button class="btn-sm btn-success" onclick="updateStatus(<?php echo (int) $feedback['id']; ?>, 'resolved')">
                                    已解决
                                </button>
                                <button class="btn-sm btn-secondary" onclick="updateStatus(<?php echo (int) $feedback['id']; ?>, 'closed')">
                                    关闭
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 回复模态框 -->
<div class="feedback-reply-modal" id="replyModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="replyModalTitle">回复反馈</h3>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">回复内容</label>
                <textarea class="form-control" id="replyContent" rows="6" placeholder="请输入回复内容..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-sm btn-secondary" onclick="closeReplyModal()">取消</button>
            <button class="btn-sm btn-primary" onclick="submitReply()">提交回复</button>
        </div>
    </div>
</div>

<script>
let currentFeedbackId = null;

document.addEventListener('DOMContentLoaded', function() {
    $('.filter-multi-select').select2({
        width: '180px',
        language: 'zh-CN',
        allowClear: true,
        closeOnSelect: false
    });
});

function applyFilter() {
    const params = new URLSearchParams();
    Array.from(document.getElementById('typeFilter').selectedOptions).forEach(option => {
        params.append('type[]', option.value);
    });
    Array.from(document.getElementById('statusFilter').selectedOptions).forEach(option => {
        params.append('status[]', option.value);
    });
    window.location.href = params.toString() ? `?${params.toString()}` : '/pages/admin/feedback.php';
}

function replyFeedbackFromButton(button) {
    replyFeedback(Number(button.dataset.feedbackId), button.dataset.feedbackTitle || '');
}

function replyFeedback(id, title) {
    currentFeedbackId = id;
    document.getElementById('replyModalTitle').textContent = `回复：${title}`;
    document.getElementById('replyContent').value = '';
    document.getElementById('replyModal').classList.add('active');
}

function closeReplyModal() {
    document.getElementById('replyModal').classList.remove('active');
    currentFeedbackId = null;
}

async function submitReply() {
    const content = document.getElementById('replyContent').value.trim();
    if (!currentFeedbackId) {
        Toast.error('请选择要回复的反馈');
        return;
    }

    if (!content) {
        Toast.error('请输入回复内容');
        return;
    }

    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-reply"></i> 提交回复',
        messageHtml: '<p>确认提交这条反馈回复吗？</p>',
        confirmText: '确认提交',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/feedback_reply.php', {
                feedback_id: currentFeedbackId,
                reply: content,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message || '回复失败');
                return false;
            }

            closeReplyModal();
            Toast.success(response.message || '回复成功');
            setTimeout(() => location.reload(), 1000);
            return true;
        }
    });
}

async function updateStatus(id, status) {
    const statusMap = {
        processing: '处理中',
        resolved: '已解决',
        closed: '已关闭'
    };

    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-flag"></i> 更新反馈状态',
        messageHtml: `<p>确认将反馈状态更新为 <strong>${statusMap[status] || status}</strong> 吗？</p>`,
        confirmText: '确认更新',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/feedback_status.php', {
                feedback_id: id,
                status,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message || '更新失败');
                return false;
            }

            Toast.success(response.message || '状态更新成功');
            setTimeout(() => location.reload(), 1000);
            return true;
        }
    });
}

document.getElementById('replyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReplyModal();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
