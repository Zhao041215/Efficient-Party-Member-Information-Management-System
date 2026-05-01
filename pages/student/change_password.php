<?php
/**
 * 学生修改密码页面
 */
$pageTitle = '修改密码 - 生化学院党员信息管理系统';
$currentPage = 'change_password';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// 检查角色
requireRole('student');

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

<div class="card">
    <?php renderFeedbackTipAlert(); ?>
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-key"></i> 修改密码</h3>
    </div>
    <div class="card-body">
        <div class="password-form-container">
            <form id="changePasswordForm">
                <div class="form-group">
                    <label class="form-label required">当前密码</label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-control" name="current_password" id="currentPassword" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('currentPassword')">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">新密码</label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword('newPassword')">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <small class="text-muted">密码长度至少6位</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">确认新密码</label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="password-strength" id="passwordStrength" style="display: none;">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <span class="strength-text" id="strengthText"></span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fa-solid fa-check"></i> 确认修改
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.password-form-container {
    max-width: 400px;
    margin: 0 auto;
    padding: 20px 0;
}

.password-input-wrapper {
    position: relative;
}
.password-input-wrapper input {
    padding-right: 45px;
}
.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 5px;
}
.toggle-password:hover {
    color: #333;
}

.password-strength {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.strength-bar {
    flex: 1;
    height: 6px;
    background: #e0e0e0;
    border-radius: 3px;
    overflow: hidden;
}
.strength-fill {
    height: 100%;
    width: 0;
    transition: all 0.3s ease;
    border-radius: 3px;
}
.strength-fill.weak { width: 33%; background: #dc3545; }
.strength-fill.medium { width: 66%; background: #ffc107; }
.strength-fill.strong { width: 100%; background: #28a745; }
.strength-text {
    font-size: 0.85rem;
    min-width: 60px;
}
.strength-text.weak { color: #dc3545; }
.strength-text.medium { color: #ffc107; }
.strength-text.strong { color: #28a745; }

.form-actions {
    margin-top: 30px;
    text-align: center;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.getElementById('newPassword');
    const strengthContainer = document.getElementById('passwordStrength');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    
    // 密码强度检测
    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        
        if (password.length === 0) {
            strengthContainer.style.display = 'none';
            return;
        }
        
        strengthContainer.style.display = 'flex';
        
        let strength = 0;
        
        // 长度检查
        if (password.length >= 6) strength++;
        if (password.length >= 10) strength++;
        
        // 包含数字
        if (/\d/.test(password)) strength++;
        
        // 包含小写字母
        if (/[a-z]/.test(password)) strength++;
        
        // 包含大写字母
        if (/[A-Z]/.test(password)) strength++;
        
        // 包含特殊字符
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
        
        // 设置强度显示
        strengthFill.className = 'strength-fill';
        strengthText.className = 'strength-text';
        
        if (strength <= 2) {
            strengthFill.classList.add('weak');
            strengthText.classList.add('weak');
            strengthText.textContent = '弱';
        } else if (strength <= 4) {
            strengthFill.classList.add('medium');
            strengthText.classList.add('medium');
            strengthText.textContent = '中';
        } else {
            strengthFill.classList.add('strong');
            strengthText.classList.add('strong');
            strengthText.textContent = '强';
        }
    });
    
    // 表单提交
    document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const submitBtn = document.getElementById('submitBtn');
        
        // 验证
        if (newPassword.length < 6) {
            Toast.error('新密码长度至少6位');
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
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 提交中...';
        
        try {
            const response = await Ajax.post('/api/student/change_password.php', {
                current_password: currentPassword,
                new_password: newPassword
            });
            
            if (response.success) {
                Toast.success(response.message);
                // 跳转到登录页
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
});

// 切换密码显示
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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
