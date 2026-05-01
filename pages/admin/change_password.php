<?php
/**
 * 管理员端修改密码页面
 */
$pageTitle = '修改密码 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'superadmin']);

// 检查是否需要强制修改密码
$db = Database::getInstance();
$user = $db->fetchOne("SELECT force_change_password FROM users WHERE id = ?", [$_SESSION['user_id']]);
$forceChange = $user && $user['force_change_password'] == 1;
?>

<?php if ($forceChange): ?>
<div class="alert alert-warning" style="margin-bottom: 20px;">
    <i class="fa-solid fa-exclamation-triangle"></i>
    <strong>首次登录或密码已被重置</strong>
    <p style="margin: 8px 0 0 0;">为了您的账户安全，请立即修改密码。修改密码后需要重新登录。</p>
</div>
<?php endif; ?>

<div class="card" style="max-width: 500px; margin: 0 auto;">
    <?php renderFeedbackTipAlert(); ?>
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-key"></i> 修改密码</h3>
    </div>
    <div class="card-body">
        <form id="changePasswordForm">
            <div class="form-group">
                <label class="form-label required">当前密码</label>
                <div class="password-wrapper">
                    <input type="password" class="form-control" name="current_password" id="currentPassword" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label required">新密码</label>
                <div class="password-wrapper">
                    <input type="password" class="form-control" name="new_password" id="newPassword" 
                           required minlength="6" placeholder="至少6位字符">
                    <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label required">确认新密码</label>
                <div class="password-wrapper">
                    <input type="password" class="form-control" name="confirm_password" id="confirmPassword" 
                           required minlength="6">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                    <i class="fa-solid fa-check"></i> 确认修改
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.password-wrapper {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    padding: 4px;
}

.password-toggle:hover {
    color: #333;
}

.btn-block {
    width: 100%;
}
</style>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const submitBtn = document.getElementById('submitBtn');
    
    // 验证
    if (newPassword.length < 6) {
        Toast.error('新密码至少需要6位字符');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        Toast.error('两次输入的新密码不一致');
        return;
    }
    
    if (currentPassword === newPassword) {
        Toast.error('新密码不能与当前密码相同');
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 处理中...';
    
    try {
        const response = await Ajax.post('/api/admin/change_password.php', {
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        });
        
        if (response.success) {
            Toast.success(response.message || '密码修改成功，请重新登录');
            setTimeout(() => {
                window.location.href = '/index.php';
            }, 1500);
        } else {
            Toast.error(response.message || '修改失败');
        }
    } catch (error) {
        Toast.error('网络错误，请稍后重试');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> 确认修改';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
