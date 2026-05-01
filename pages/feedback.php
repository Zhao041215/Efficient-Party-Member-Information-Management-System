<?php
/**
 * Bug/建议反馈页面
 */
$pageTitle = 'Bug/建议反馈 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/header.php';

// 检查登录状态
requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$currentUser = getCurrentUser();

// 获取用户的历史反馈
$feedbackList = $db->fetchAll("
    SELECT * FROM feedback 
    WHERE user_id = ? 
    ORDER BY created_at DESC
", [$userId]);
?>

<style>
.feedback-container {
    max-width: 1200px;
    margin: 0 auto;
}

.feedback-form-card {
    background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
    border-radius: 16px;
    padding: 0;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(196, 30, 58, 0.22);
    overflow: hidden;
}

.feedback-form-header {
    background: rgba(255, 255, 255, 0.1);
    padding: 24px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.feedback-form-header h2 {
    color: white;
    font-size: 28px;
    font-weight: bold;
    margin: 0;
}

.feedback-form-header p {
    color: rgba(255, 255, 255, 0.9);
    margin: 8px 0 0 0;
    font-size: 14px;
}

.feedback-form-body {
    background: white;
    padding: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-label i {
    margin-right: 6px;
    color: #c41e3a;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #c41e3a;
    box-shadow: 0 0 0 3px rgba(196, 30, 58, 0.1);
}

textarea.form-control {
    min-height: 150px;
    resize: vertical;
}

.type-selector {
    display: flex;
    gap: 15px;
    margin-top: 8px;
}

.type-option {
    flex: 1;
    padding: 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    transition: all 0.3s ease;
}

.type-option:hover {
    border-color: #c41e3a;
    background: rgba(196, 30, 58, 0.05);
}

.type-option.active {
    border-color: #c41e3a;
    background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
    color: white;
}

.type-option i {
    font-size: 24px;
    display: block;
    margin-bottom: 8px;
}

.submit-btn {
    background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
    color: white;
    border: none;
    padding: 14px 40px;
    border-radius: 25px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(196, 30, 58, 0.32);
    width: 100%;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(196, 30, 58, 0.4);
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.feedback-history {
    margin-top: 30px;
}

.feedback-item {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.feedback-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.feedback-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.feedback-item-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.feedback-item-meta {
    display: flex;
    gap: 10px;
    align-items: center;
}

.feedback-type-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.feedback-type-badge.bug {
    background: #fee;
    color: #c00;
}

.feedback-type-badge.suggestion {
    background: #e7f3ff;
    color: #0066cc;
}

.feedback-status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.feedback-status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.feedback-status-badge.processing {
    background: #cfe2ff;
    color: #084298;
}

.feedback-status-badge.resolved {
    background: #d1e7dd;
    color: #0f5132;
}

.feedback-status-badge.closed {
    background: #e2e3e5;
    color: #41464b;
}

.feedback-item-content {
    color: #666;
    line-height: 1.6;
    margin-bottom: 12px;
}

.feedback-item-time {
    font-size: 12px;
    color: #999;
}

.feedback-reply {
    margin-top: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-left: 3px solid #c41e3a;
    border-radius: 4px;
}

.feedback-reply-label {
    font-weight: 600;
    color: #c41e3a;
    margin-bottom: 6px;
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

.back-btn {
    background: white;
    border: 2px solid #c41e3a;
    color: #c41e3a;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.back-btn:hover {
    background: #c41e3a;
    color: white;
    transform: translateX(-3px);
}

.back-btn i {
    margin-right: 6px;
}

.datetime-wrapper {
    display: flex;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.datetime-wrapper:focus-within {
    border-color: #c41e3a;
    box-shadow: 0 0 0 3px rgba(196, 30, 58, 0.1);
}

.datetime-wrapper input {
    border: none;
    padding: 12px;
    font-size: 14px;
    flex: 1;
}

.datetime-wrapper input:focus {
    outline: none;
    box-shadow: none;
}

.datetime-wrapper .date-input {
    border-right: 1px solid #e0e0e0;
}
</style>

<div class="feedback-container">
    <!-- 返回按钮 -->
    <div style="margin-bottom: 20px;">
        <button onclick="history.back()" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i> 返回
        </button>
    </div>
    
    <!-- 提交表单 -->
    <div class="feedback-form-card">
        <div class="feedback-form-header">
            <h2><i class="fa-solid fa-comment-dots"></i> Bug/建议反馈</h2>
            <p>您的反馈对我们非常重要，帮助我们不断改进系统</p>
        </div>
        <div class="feedback-form-body">
            <form id="feedbackForm">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fa-solid fa-tag"></i> 反馈类型
                    </label>
                    <div class="type-selector">
                        <div class="type-option active" data-type="bug">
                            <i class="fa-solid fa-bug"></i>
                            <div>Bug反馈</div>
                        </div>
                        <div class="type-option" data-type="suggestion">
                            <i class="fa-solid fa-lightbulb"></i>
                            <div>功能建议</div>
                        </div>
                    </div>
                    <input type="hidden" name="type" id="feedbackType" value="bug">
                </div>

                <div class="form-group">
                    <label class="form-label" for="title">
                        <i class="fa-solid fa-heading"></i> 标题
                    </label>
                    <input type="text" class="form-control" id="title" name="title" 
                           placeholder="请简要描述问题或建议" required maxlength="200">
                </div>

                <div class="form-group">
                    <label class="form-label" for="content">
                        <i class="fa-solid fa-align-left"></i> 详细描述
                    </label>
                    <textarea class="form-control" id="content" name="content" 
                              placeholder="请详细描述您遇到的问题或建议的功能..." required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="contact">
                        <i class="fa-solid fa-address-book"></i> 联系方式（可选）
                    </label>
                    <input type="text" class="form-control" id="contact" name="contact" 
                           placeholder="QQ、微信、邮箱等，方便我们与您联系" maxlength="100">
                </div>

                <div class="form-group" id="bugFields" style="display: none;">
                    <label class="form-label" for="device">
                        <i class="fa-solid fa-mobile-screen"></i> 设备类型（可选）
                    </label>
                    <select class="form-control" id="device" name="device">
                        <option value="">请选择设备类型</option>
                        <option value="安卓">安卓</option>
                        <option value="IOS">IOS</option>
                        <option value="鸿蒙">鸿蒙</option>
                        <option value="Windows">Windows</option>
                        <option value="Mac">Mac</option>
                    </select>
                </div>

                <div class="form-group" id="deviceModelField" style="display: none;">
                    <label class="form-label" for="device_model">
                        <i class="fa-solid fa-laptop"></i> 设备机型（可选）
                    </label>
                    <input type="text" class="form-control" id="device_model" name="device_model" 
                           placeholder="例如：iPhone 15 Pro、Windows 11、MacBook Pro等" maxlength="100">
                </div>

                <div class="form-group" id="bugTimeField" style="display: none;">
                    <label class="form-label" for="bug_time">
                        <i class="fa-solid fa-clock"></i> Bug出现时间（可选）
                    </label>
                    <input type="datetime-local" class="form-control" id="bug_time" name="bug_time">
                </div>

                <div class="form-group" id="screenshotField" style="display: none;">
                    <label class="form-label" for="screenshot">
                        <i class="fa-solid fa-image"></i> 截图上传（可选）
                    </label>
                    <input type="file" class="form-control" id="screenshot" name="screenshot" 
                           accept="image/*">
                    <small style="color: #999; display: block; margin-top: 5px;">
                        支持 JPG、PNG、GIF 格式，最大 5MB
                    </small>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fa-solid fa-paper-plane"></i> 提交反馈
                </button>
            </form>
        </div>
    </div>

    <!-- 历史反馈 -->
    <div class="card feedback-history">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-history"></i> 我的反馈记录</h3>
        </div>
        <div class="card-body">
            <?php if (empty($feedbackList)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <p>暂无反馈记录</p>
                </div>
            <?php else: ?>
                <?php foreach ($feedbackList as $feedback): ?>
                    <div class="feedback-item">
                        <div class="feedback-item-header">
                            <div class="feedback-item-title"><?php echo e($feedback['title']); ?></div>
                            <div class="feedback-item-meta">
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
                            </div>
                        </div>
                        <div class="feedback-item-content">
                            <?php echo nl2br(e($feedback['content'])); ?>
                        </div>
                        <?php if ($feedback['admin_reply']): ?>
                            <div class="feedback-reply">
                                <div class="feedback-reply-label">
                                    <i class="fa-solid fa-reply"></i> 管理员回复：
                                </div>
                                <div><?php echo nl2br(e($feedback['admin_reply'])); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="feedback-item-time">
                            <i class="fa-solid fa-clock"></i> 
                            提交时间：<?php echo date('Y-m-d H:i:s', strtotime($feedback['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// 类型选择 - 显示/隐藏Bug相关字段
function toggleBugFields(type) {
    const bugFields = ['bugFields', 'deviceModelField', 'bugTimeField', 'screenshotField'];
    const display = type === 'bug' ? 'block' : 'none';
    bugFields.forEach(id => {
        const field = document.getElementById(id);
        if (field) field.style.display = display;
    });
}

// 初始化显示Bug字段
toggleBugFields('bug');

document.querySelectorAll('.type-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('active'));
        this.classList.add('active');
        const type = this.dataset.type;
        document.getElementById('feedbackType').value = type;
        toggleBugFields(type);
    });
});

// 表单提交
document.getElementById('feedbackForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('.submit-btn');
    const originalText = submitBtn.innerHTML;
    
    // 创建FormData对象以支持文件上传
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
    formData.append('type', document.getElementById('feedbackType').value);
    formData.append('title', document.getElementById('title').value.trim());
    formData.append('content', document.getElementById('content').value.trim());
    formData.append('contact', document.getElementById('contact').value.trim());
    
    // 如果是Bug反馈，添加额外字段
    if (document.getElementById('feedbackType').value === 'bug') {
        const device = document.getElementById('device').value;
        const deviceModel = document.getElementById('device_model').value.trim();
        const bugTime = document.getElementById('bug_time').value.trim();
        const screenshot = document.getElementById('screenshot').files[0];
        
        if (device) formData.append('device', device);
        if (deviceModel) formData.append('device_model', deviceModel);
        if (bugTime) formData.append('bug_time', bugTime);
        if (screenshot) {
            // 验证文件大小
            if (screenshot.size > 5 * 1024 * 1024) {
                Toast.error('图片大小不能超过5MB');
                return;
            }
            formData.append('screenshot', screenshot);
        }
    }
    
    // 验证
    if (!formData.get('title')) {
        Toast.error('请输入标题');
        return;
    }
    
    if (!formData.get('content')) {
        Toast.error('请输入详细描述');
        return;
    }
    
    // 禁用按钮
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 提交中...';
    
    try {
        const response = await fetch('/api/feedback/submit.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Toast.success('提交成功！感谢您的反馈');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            Toast.error(result.message || '提交失败');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    } catch (error) {
        Toast.error('网络错误，请稍后重试');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
