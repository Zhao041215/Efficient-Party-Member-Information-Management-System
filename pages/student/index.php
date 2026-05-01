<?php
/**
 * 学生个人信息页面
 */
$pageTitle = '个人信息 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// 检查角色
requireRole('student');

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// 获取学生信息
$studentInfo = $db->fetchOne("
    SELECT si.*, u.username 
    FROM student_info si 
    JOIN users u ON si.user_id = u.id 
    WHERE si.user_id = ?
", [$userId]);

// 如果没有学生信息记录，跳转到填写信息页面
if (!$studentInfo || !$studentInfo['info_completed']) {
    header('Location: /pages/student/fill_info.php');
    exit;
}
?>

<div class="card">
    <?php renderFeedbackTipAlert(); ?>
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-user"></i> 学生个人信息表</h3>
    </div>
    <div class="card-body">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">学号</span>
                <span class="info-value"><?php echo e($studentInfo['student_no']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">姓名</span>
                <span class="info-value"><?php echo e($studentInfo['name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">性别</span>
                <span class="info-value"><?php echo e($studentInfo['gender']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">学院</span>
                <span class="info-value"><?php echo e($studentInfo['college']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">年级</span>
                <span class="info-value"><?php echo e($studentInfo['grade']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">班级</span>
                <span class="info-value"><?php echo e($studentInfo['class']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">出生日期</span>
                <span class="info-value"><?php echo e($studentInfo['birth_date']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">民族</span>
                <span class="info-value"><?php echo e($studentInfo['ethnicity']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">身份证号</span>
                <span class="info-value"><?php echo e($studentInfo['id_card']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">家庭住址</span>
                <span class="info-value"><?php echo e($studentInfo['address']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">联系方式</span>
                <span class="info-value"><?php echo e($studentInfo['phone']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">邮箱</span>
                <span class="info-value"><?php echo e($studentInfo['email']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">政治面貌</span>
                <span class="info-value">
                    <span class="badge badge-primary"><?php echo e($studentInfo['political_status']); ?></span>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">年龄</span>
                <span class="info-value"><?php echo calculateAgeFromIdCard($studentInfo['id_card']); ?> 岁</span>
            </div>
            <div class="info-item">
                <span class="info-label">入团时间</span>
                <span class="info-value"><?php echo e($studentInfo['join_league_date']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">递交入党申请书时间</span>
                <span class="info-value"><?php echo e($studentInfo['apply_party_date']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">确定积极分子时间</span>
                <span class="info-value"><?php echo e($studentInfo['activist_date']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">确定预备党员时间</span>
                <span class="info-value"><?php echo e($studentInfo['probationary_date']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">转正时间</span>
                <span class="info-value"><?php echo e($studentInfo['full_member_date']); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- 邮箱验证弹窗 -->
<div class="update-notice-overlay" id="emailVerifyOverlay" style="display: none;">
    <div class="update-notice-box" style="max-width: 500px;">
        <div class="update-notice-header">
            <div class="update-notice-icon">📧</div>
            <h2 class="update-notice-title">完善邮箱信息</h2>
        </div>
        <div class="update-notice-content">
            <p><strong>📢 重要提示：</strong></p>
            <p>检测到您尚未绑定邮箱，为了确保账号安全和找回密码功能的正常使用，请立即完善邮箱信息。</p>
            <div class="form-group" style="margin-top: 20px;">
                <label class="form-label">
                    <i class="fa-solid fa-envelope"></i> 邮箱地址
                </label>
                <input type="email" class="form-control" id="emailInput" placeholder="请输入常用邮箱" autocomplete="off">
            </div>
            <div class="form-group" id="verifyCodeGroup" style="display: none;">
                <label class="form-label">
                    <i class="fa-solid fa-key"></i> 验证码
                </label>
                <div class="input-action-row">
                    <input type="text" class="form-control" id="verifyCodeInput" placeholder="请输入6位验证码" maxlength="6" autocomplete="off">
                    <button type="button" class="btn btn-secondary" id="sendCodeBtn" style="white-space: nowrap;">
                        发送验证码
                    </button>
                </div>
            </div>
            <div class="update-notice-highlight" style="margin-top: 16px;">
                <i class="fa-solid fa-shield-alt"></i> 
                邮箱将用于密码找回，请确保填写正确
            </div>
        </div>
        <div class="update-notice-footer">
            <button class="update-notice-btn" id="verifyEmailBtn">
                <i class="fa-solid fa-paper-plane"></i> 发送验证码
            </button>
        </div>
    </div>
</div>

<style>
/* 邮箱验证弹窗样式 */
.update-notice-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.update-notice-box {
    background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
    border-radius: 16px;
    padding: 0;
    max-width: 450px;
    width: 90%;
    max-height: calc(100vh - 32px);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.4s ease;
    overflow: hidden;
}

.update-notice-header {
    background: rgba(255, 255, 255, 0.1);
    padding: 24px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.update-notice-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

.update-notice-title {
    color: white;
    font-size: 24px;
    font-weight: bold;
    margin: 0;
}

.update-notice-content {
    padding: 30px 24px;
    background: white;
    color: #333;
    line-height: 1.8;
    font-size: 15px;
    overflow-y: auto;
}

.update-notice-highlight {
    background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
    color: white;
    padding: 16px;
    border-radius: 8px;
    margin-top: 16px;
    font-weight: 500;
    text-align: center;
}

.update-notice-footer {
    padding: 20px 24px;
    background: white;
    text-align: center;
}

.update-notice-btn {
    background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
    color: white;
    border: none;
    padding: 12px 40px;
    border-radius: 25px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(196, 30, 58, 0.32);
}

.update-notice-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(196, 30, 58, 0.4);
}

.update-notice-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

@media (max-width: 576px) {
    .update-notice-box {
        width: calc(100% - 24px);
        max-height: calc(100vh - 24px);
    }

    .update-notice-header,
    .update-notice-content,
    .update-notice-footer {
        padding-left: 18px;
        padding-right: 18px;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>

<script>
// 检查邮箱是否已绑定
const studentEmail = <?php echo json_encode($studentInfo['email'] ?? ''); ?>;
let countdown = 60;
let countdownTimer = null;

if (!studentEmail || studentEmail.trim() === '') {
    document.getElementById('emailVerifyOverlay').style.display = 'flex';
}

// 发送验证码按钮点击
document.getElementById('verifyEmailBtn').addEventListener('click', function() {
    const email = document.getElementById('emailInput').value.trim();
    
    if (!email) {
        Toast.error('请输入邮箱地址');
        return;
    }
    
    if (!isValidEmail(email)) {
        Toast.error('请输入正确的邮箱格式');
        return;
    }
    
    sendVerificationCode(email);
});

// 重新发送验证码
document.getElementById('sendCodeBtn').addEventListener('click', function() {
    const email = document.getElementById('emailInput').value.trim();
    if (email && isValidEmail(email)) {
        sendVerificationCode(email);
    }
});

// 验证码输入完成后自动验证
document.getElementById('verifyCodeInput').addEventListener('input', function(e) {
    const code = e.target.value.trim();
    if (code.length === 6) {
        verifyAndBindEmail();
    }
});

// 发送验证码函数
async function sendVerificationCode(email) {
    const btn = document.getElementById('verifyEmailBtn');
    const sendBtn = document.getElementById('sendCodeBtn');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 发送中...';
    
    try {
        const response = await Ajax.post('/api/student/send_email_code.php', { email });
        
        if (response.success) {
            Toast.success('验证码已发送，请查收邮件');
            document.getElementById('verifyCodeGroup').style.display = 'block';
            btn.style.display = 'none';
            document.getElementById('emailInput').readOnly = true;
            
            // 开始倒计时
            startCountdown(sendBtn);
        } else {
            Toast.error(response.message || '发送失败');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 发送验证码';
        }
    } catch (error) {
        Toast.error('网络错误，请稍后重试');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 发送验证码';
    }
}

// 验证并绑定邮箱
async function verifyAndBindEmail() {
    const email = document.getElementById('emailInput').value.trim();
    const code = document.getElementById('verifyCodeInput').value.trim();
    
    if (!code || code.length !== 6) {
        Toast.error('请输入6位验证码');
        return;
    }
    
    try {
        const response = await Ajax.post('/api/student/verify_and_bind_email.php', { email, code });
        
        if (response.success) {
            Toast.success('邮箱绑定成功！');
            setTimeout(() => {
                document.getElementById('emailVerifyOverlay').style.display = 'none';
                location.reload();
            }, 1000);
        } else {
            Toast.error(response.message || '验证失败');
            document.getElementById('verifyCodeInput').value = '';
        }
    } catch (error) {
        Toast.error('网络错误，请稍后重试');
    }
}

// 倒计时
function startCountdown(btn) {
    countdown = 60;
    btn.disabled = true;
    btn.textContent = `${countdown}秒后重发`;
    
    countdownTimer = setInterval(() => {
        countdown--;
        if (countdown <= 0) {
            clearInterval(countdownTimer);
            btn.disabled = false;
            btn.textContent = '重新发送';
        } else {
            btn.textContent = `${countdown}秒后重发`;
        }
    }, 1000);
}

// 邮箱格式验证
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
