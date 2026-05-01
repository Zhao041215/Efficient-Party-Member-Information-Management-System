<?php
/**
 * 学生首次信息填写页面
 */
$pageTitle = '填写信息 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// 检查角色
requireRole('student');

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// 获取用户基本信息
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// 检查是否已有学生信息记录
$studentInfo = $db->fetchOne("SELECT * FROM student_info WHERE user_id = ?", [$userId]);

// 如果已完成信息填写，跳转到个人信息页面
if ($studentInfo && $studentInfo['info_completed']) {
    header('Location: /pages/student/index.php');
    exit;
}

// 获取系统选项
$colleges = getSystemOptions('college');
$grades = getSystemOptions('grade');
$classes = getSystemOptions('class');
$politicalStatuses = getSystemOptions('political_status');
$ethnicities = getSystemOptions('ethnicity');

// 获取发展时间配置
$activistDate = getDevelopmentTime('确定入党积极分子时间');

// 生成毕业年份选项
$currentYear = date('Y');
$graduationYears = [];
for ($i = $currentYear; $i <= $currentYear + 6; $i++) {
    $graduationYears[] = $i . '年';
}
?>

<div class="card">
    <?php renderFeedbackTipAlert(); ?>
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-edit"></i> 完善个人信息</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <strong>首次登录，请完善您的个人信息！</strong> 所有带 <span class="text-danger">*</span> 的字段为必填项。
            <br><small>学号、姓名、性别、学院、年级、班级由管理员设置，如需修改请联系管理员。</small>
        </div>
        
        <form id="fillInfoForm">
            <div class="info-grid form-grid-2">
                <!-- 基本信息（不可修改） -->
                <div class="form-group">
                    <label class="form-label">学号</label>
                    <input type="text" class="form-control" value="<?php echo e($user['username']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">姓名</label>
                    <input type="text" class="form-control" value="<?php echo e($user['name']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">性别</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['gender'] ?? ''); ?>" disabled>
                </div>
                
                <!-- 学院（不可修改） -->
                <div class="form-group">
                    <label class="form-label">学院</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['college'] ?? ''); ?>" disabled>
                </div>
                
                <!-- 年级（不可修改） -->
                <div class="form-group">
                    <label class="form-label">年级</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['grade'] ?? ''); ?>" disabled>
                </div>
                
                <!-- 班级（不可修改） -->
                <div class="form-group">
                    <label class="form-label">班级</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['class'] ?? ''); ?>" disabled>
                </div>
                
                <!-- 民族 -->
                <div class="form-group">
                    <label class="form-label">民族 <span class="text-danger">*</span></label>
                    <select class="form-control select2-search" name="ethnicity" required>
                        <option value="">请选择民族</option>
                        <?php foreach ($ethnicities as $ethnicity): ?>
                            <option value="<?php echo e($ethnicity); ?>"><?php echo e($ethnicity); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- 身份证号 -->
                <div class="form-group">
                    <label class="form-label">身份证号 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="id_card" id="idCard" maxlength="18" placeholder="请输入18位身份证号" required>
                    <small class="text-muted">年龄和出生日期将根据身份证号自动计算</small>
                </div>
                
                <!-- 联系方式 -->
                <div class="form-group">
                    <label class="form-label">联系方式 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="phone" maxlength="11" placeholder="请输入手机号" required>
                </div>
                
                <!-- 邮箱 -->
                <div class="form-group">
                    <label class="form-label">邮箱 <span class="text-danger">*</span></label>
                    <div class="input-action-row">
                        <input type="email" class="form-control" name="email" id="emailInput" placeholder="请输入邮箱" required style="flex: 1;">
                        <button type="button" class="btn btn-outline-primary" id="sendCodeBtn" style="white-space: nowrap;">
                            <i class="fa-solid fa-envelope"></i> 发送验证码
                        </button>
                    </div>
                    <small class="text-muted">请确保邮箱可正常接收邮件</small>
                </div>
                
                <!-- 邮箱验证码 -->
                <div class="form-group" id="codeGroup" style="display: none;">
                    <label class="form-label">邮箱验证码 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="email_code" id="emailCode" maxlength="6" placeholder="请输入6位验证码">
                    <small class="text-success" id="codeStatus"></small>
                </div>
                
                <!-- 家庭住址 -->
                <div class="form-group grid-span-2">
                    <label class="form-label">家庭住址 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="address" placeholder="请详细填写到门牌号" required>
                </div>
                
                <!-- 政治面貌 -->
                <div class="form-group">
                    <label class="form-label">政治面貌 <span class="text-danger">*</span></label>
                    <input type="hidden" name="political_status" value="入党积极分子">
                    <input type="text" class="form-control" value="入党积极分子" readonly>
                </div>
                
                <!-- 毕业时间 -->
                <div class="form-group">
                    <label class="form-label">预计毕业时间 <span class="text-danger">*</span></label>
                    <select class="form-control select2" name="graduation_year" readonly required>
                        <option value="">请选择毕业年份</option>
                        <?php foreach ($graduationYears as $year): 
                        // 根据年级自动计算毕业年份
                        $calculatedYear = '';
                        if (!empty($studentInfo['grade'])) {
                            // 从年级字符串中提取年份数字（如"2024级" → 2024）
                            preg_match('/(\d{4})/', $studentInfo['grade'], $matches);
                            if (!empty($matches[1])) {
                                $calculatedYear = (intval($matches[1]) + 4) . '年';
                            }
                        }
                    ?>
                        <option value="<?php echo e($year); ?>" <?php echo $year === $calculatedYear ? 'selected' : ''; ?>>
                            <?php echo e($year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                </div>
                
                
                <!-- 入团时间 -->
                <div class="form-group">
                    <label class="form-label">入团时间 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="join_league_date" required>
                </div>
                
                <!-- 递交入党申请书时间 -->
                <div class="form-group">
                    <label class="form-label">递交入党申请书时间 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="apply_party_date" required>
                </div>
                
                <!-- 确定积极分子时间 -->
                <div class="form-group">
                    <label class="form-label">确定入党积极分子时间 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="activist_date" value="<?php echo e($activistDate); ?>" readonly required>
                    <small class="text-muted" style="color: #ff6b6b !important;">此时间由系统配置，如需修改请联系超级管理员</small>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                    <i class="fa-solid fa-check"></i> 提交信息
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 初始化Select2
    $('.select2').select2({
        width: '100%',
        language: 'zh-CN'
    });
    
    $('.select2-search').select2({
        width: '100%',
        language: 'zh-CN',
        matcher: function(params, data) {
            if ($.trim(params.term) === '') {
                return data;
            }
            if (data.text.indexOf(params.term) > -1) {
                return data;
            }
            return null;
        }
    });
    
    // 身份证号变化时计算年龄
    document.getElementById('idCard').addEventListener('blur', function() {
        const idCard = this.value.trim();
        if (idCard.length === 18) {
            if (!Validator.validateIdCard(idCard)) {
                Toast.error('身份证号格式不正确');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
                const age = Validator.getAgeFromIdCard(idCard);
                Toast.info(`年龄：${age}岁`);
            }
        }
    });
    
    // 邮箱验证相关变量
    let emailVerified = false;
    let countdown = 0;
    let countdownTimer = null;
    
    const emailInput = document.getElementById('emailInput');
    const sendCodeBtn = document.getElementById('sendCodeBtn');
    const codeGroup = document.getElementById('codeGroup');
    const emailCodeInput = document.getElementById('emailCode');
    const codeStatus = document.getElementById('codeStatus');
    
    // 发送验证码
    sendCodeBtn.addEventListener('click', async function() {
        const email = emailInput.value.trim();
        
        if (!Validator.validateEmail(email)) {
            Toast.error('请输入正确的邮箱地址');
            return;
        }
        
        if (countdown > 0) {
            return;
        }
        
        sendCodeBtn.disabled = true;
        sendCodeBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 发送中...';
        
        try {
            const response = await Ajax.post('/api/student/send_email_code.php', { 
                email: email,
                type: 'verify'
            });
            
            if (response.success) {
                Toast.success('验证码已发送，请查收邮件');
                codeGroup.style.display = 'block';
                emailCodeInput.required = true;
                
                // 开始倒计时
                countdown = 60;
                updateCountdown();
                countdownTimer = setInterval(updateCountdown, 1000);
            } else {
                Toast.error(response.message || '发送失败');
                sendCodeBtn.disabled = false;
                sendCodeBtn.innerHTML = '<i class="fa-solid fa-envelope"></i> 发送验证码';
            }
        } catch (error) {
            Toast.error('网络错误，请稍后重试');
            sendCodeBtn.disabled = false;
            sendCodeBtn.innerHTML = '<i class="fa-solid fa-envelope"></i> 发送验证码';
        }
    });
    
    // 倒计时更新
    function updateCountdown() {
        if (countdown > 0) {
            sendCodeBtn.disabled = true;
            sendCodeBtn.innerHTML = `${countdown}秒后重发`;
            countdown--;
        } else {
            clearInterval(countdownTimer);
            sendCodeBtn.disabled = false;
            sendCodeBtn.innerHTML = '<i class="fa-solid fa-envelope"></i> 发送验证码';
        }
    }
    
    // 验证码输入时实时验证
    emailCodeInput.addEventListener('input', async function() {
        const code = this.value.trim();
        const email = emailInput.value.trim();
        
        if (code.length === 6) {
            try {
                const response = await Ajax.post('/api/student/verify_and_bind_email.php', {
                    email: email,
                    code: code,
                    action: 'verify_only'
                });
                
                if (response.success) {
                    emailVerified = true;
                    codeStatus.textContent = '✓ 验证成功';
                    codeStatus.className = 'text-success';
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else {
                    emailVerified = false;
                    codeStatus.textContent = '✗ 验证码错误';
                    codeStatus.className = 'text-danger';
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            } catch (error) {
                
            }
        }
    });
    
    // 表单提交
    document.getElementById('fillInfoForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        // 验证邮箱验证码
        if (!emailVerified) {
            Toast.error('请先完成邮箱验证');
            return;
        }
        
        // 验证身份证
        if (!Validator.validateIdCard(data.id_card)) {
            Toast.error('身份证号格式不正确');
            return;
        }
        
        // 验证手机号
        if (!Validator.validatePhone(data.phone)) {
            Toast.error('手机号格式不正确');
            return;
        }
        
        // 验证邮箱
        if (!Validator.validateEmail(data.email)) {
            Toast.error('邮箱格式不正确');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 提交中...';
        
        try {
            const response = await Ajax.post('/api/student/fill_info.php', data);
            
            if (response.success) {
                Toast.success('信息提交成功！');
                setTimeout(() => {
                    window.location.href = '/pages/student/index.php';
                }, 1000);
            } else {
                Toast.error(response.message || '提交失败');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> 提交信息';
            }
        } catch (error) {
            Toast.error('网络错误，请稍后重试');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> 提交信息';
        }
    });
});

// 页面加载时显示提示弹窗
Modal.alert('首次登录，请先完善您的个人信息！');
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
