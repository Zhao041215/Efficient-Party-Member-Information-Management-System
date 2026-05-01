<?php
/**
 * 学生修改信息页面
 */
$pageTitle = '修改信息 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// 生成CSRF Token
$csrfToken = Security::generateCSRFToken();

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

if (!$studentInfo || !$studentInfo['info_completed']) {
    header('Location: /pages/student/fill_info.php');
    exit;
}

// 获取系统选项
$colleges = getSystemOptions('college');
$grades = getSystemOptions('grade');
$classes = getSystemOptions('class');
$politicalStatuses = getSystemOptions('political_status');
$ethnicities = getSystemOptions('ethnicity');

// 获取发展时间配置
$developmentTimes = getDevelopmentTimes();
$activistDate = $developmentTimes['确定入党积极分子时间'] ?? '';
$probationaryDate = $developmentTimes['确定中共预备党员时间'] ?? '';
$fullMemberDate = $developmentTimes['确定中共党员时间'] ?? '';

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
        <h3 class="card-title"><i class="fa-solid fa-pen-to-square"></i> 修改个人信息</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fa-solid fa-info-circle"></i>
            <strong>温馨提示：</strong>只需填写需要修改的字段即可，未填写的字段将保持原值。提交后需等待管理员审核通过。
        </div>
        
        <form id="editInfoForm" method="post" action="javascript:void(0);">
            <div class="info-grid form-grid-2">
                <!-- 基本信息（不可修改） -->
                <div class="form-group">
                    <label class="form-label">学号</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['student_no']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">姓名</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['name']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">性别</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['gender']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">学院</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['college']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">年级</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['grade']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">班级</label>
                    <input type="text" class="form-control" value="<?php echo e($studentInfo['class']); ?>" disabled>
                </div>
                
                <!-- 可修改的字段 -->
                <div class="form-group">
                    <label class="form-label">民族</label>
                    <select class="form-control select2-search" name="ethnicity">
                        <option value="">不修改</option>
                        <?php foreach ($ethnicities as $ethnicity): ?>
                            <option value="<?php echo e($ethnicity); ?>"><?php echo e($ethnicity); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">当前：<?php echo e($studentInfo['ethnicity']); ?></small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">身份证号</label>
                    <input type="text" class="form-control" name="id_card" maxlength="18" placeholder="不修改请留空">
                    <small class="text-muted">当前：<?php echo e($studentInfo['id_card']); ?></small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">联系方式</label>
                    <input type="text" class="form-control" name="phone" maxlength="11" placeholder="不修改请留空">
                    <small class="text-muted">当前：<?php echo e($studentInfo['phone']); ?></small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">邮箱</label>
                    <input type="email" class="form-control" name="email" placeholder="不修改请留空">
                    <small class="text-muted">当前：<?php echo e($studentInfo['email']); ?></small>
                </div>
                
                <!-- 邮箱验证码输入 -->
                <div class="form-group" id="emailVerifyGroup" style="display:none;">
                    <label class="form-label">邮箱验证码</label>
                    <div class="input-action-row">
                        <input type="text" class="form-control" name="email_code" id="emailCode" placeholder="请输入6位验证码" maxlength="6">
                        <button type="button" class="btn btn-secondary" id="sendEmailCodeBtn" style="min-width:120px;">
                            <i class="fa-solid fa-paper-plane"></i> 发送验证码
                        </button>
                    </div>
                    <small class="text-muted" id="emailCodeTip"></small>
                </div>
                
                <div class="form-group grid-span-2">
                    <label class="form-label">家庭住址</label>
                    <input type="text" class="form-control" name="address" placeholder="不修改请留空">
                    <small class="text-muted">当前：<?php echo e($studentInfo['address']); ?></small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">政治面貌</label>
                    <select class="form-control select2" name="political_status" id="politicalStatusSelect">
                        <option value="">不修改</option>
                        <?php foreach ($politicalStatuses as $status):
                            // 根据当前政治面貌过滤可选项
                            $currentStatus = $studentInfo['political_status'];

                            // 如果当前是入党积极分子，不能选择中共党员
                            if ($currentStatus === '入党积极分子' && $status === '中共党员') {
                                continue;
                            }

                            // 如果当前是入党积极分子，不能选择正式党员
                            if ($currentStatus === '入党积极分子' && $status === '正式党员') {
                                continue;
                            }
                        ?>
                            <option value="<?php echo e($status); ?>"><?php echo e($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">当前：<?php echo e($studentInfo['political_status']); ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label">预计毕业时间</label>
                    <select class="form-control select2" name="graduation_year">
                        <option value="">不修改</option>
                        <?php foreach ($graduationYears as $year): ?>
                            <option value="<?php echo e($year); ?>"><?php echo e($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">当前：<?php echo e($studentInfo['graduation_year']); ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label">入团时间</label>
                    <input type="date" class="form-control" name="join_league_date">
                    <small class="text-muted">当前：<?php echo e($studentInfo['join_league_date']); ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label">递交入党申请书时间</label>
                    <input type="date" class="form-control" name="apply_party_date">
                    <small class="text-muted">当前：<?php echo e($studentInfo['apply_party_date']); ?></small>
                </div>

                <div class="form-group" id="activistDateGroup">
                    <label class="form-label">确定积极分子时间</label>
                    <input type="date" class="form-control" name="activist_date" id="activistDateInput">
                    <small class="text-muted">当前：<?php echo e($studentInfo['activist_date']); ?></small>
                </div>

                <div class="form-group" id="probationaryDateGroup" style="display: none;">
                    <label class="form-label">确定预备党员时间</label>
                    <input type="date" class="form-control" name="probationary_date" id="probationaryDateInput">
                    <small class="text-muted">当前：<?php echo e($studentInfo['probationary_date']); ?></small>
                </div>

                <div class="form-group" id="fullMemberDateGroup" style="display: none;">
                    <label class="form-label">转正时间</label>
                    <input type="date" class="form-control" name="full_member_date" id="fullMemberDateInput">
                    <small class="text-muted">当前：<?php echo e($studentInfo['full_member_date']); ?></small>
                </div>
            </div>
            
            <div class="mt-4 form-actions-center">
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    <i class="fa-solid fa-eraser"></i> 清除数据
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fa-solid fa-paper-plane"></i> 提交审核
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// 鸿蒙系统兼容 + 防止重复初始化
(function() {
    'use strict';
    
    // 全局标记，防止重复初始化
    if (window.editInfoInitialized) {
        return;
    }
    window.editInfoInitialized = true;
    
    // 
    // 
    
    let isFormInitialized = false;
    let isSubmitting = false;
    let select2Initialized = false;
    
    // 邮箱验证相关变量
    let emailCodeSent = false;
    let emailCodeTimer = null;
    let emailCodeCountdown = 60;
    
    // 初始化邮箱验证功能
    function initEmailVerification() {
        const emailInput = document.querySelector('input[name="email"]');
        const verifyGroup = document.getElementById('emailVerifyGroup');
        const sendCodeBtn = document.getElementById('sendEmailCodeBtn');
        const emailCodeInput = document.getElementById('emailCode');

        if (!emailInput || !verifyGroup || !sendCodeBtn) {
            return;
        }
        
        // 监听邮箱输入
        emailInput.addEventListener('input', function() {
            const emailValue = this.value.trim();
            if (emailValue && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
                verifyGroup.style.display = 'block';
            } else {
                verifyGroup.style.display = 'none';
                emailCodeSent = false;
            }
        });
        
        // 发送验证码按钮点击事件
        sendCodeBtn.addEventListener('click', async function() {
            const email = emailInput.value.trim();
            
            if (!email) {
                (typeof Toast !== 'undefined') ? Toast.warning('请先输入邮箱') : alert('请先输入邮箱');
                return;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                (typeof Toast !== 'undefined') ? Toast.error('邮箱格式不正确') : alert('邮箱格式不正确');
                return;
            }
            
            sendCodeBtn.disabled = true;
            sendCodeBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 发送中...';
            
            try {
                const response = await fetch('/api/student/send_email_code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ email: email })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    (typeof Toast !== 'undefined') ? Toast.success(result.message) : alert(result.message);
                    emailCodeSent = true;
                    startCountdown();
                } else {
                    (typeof Toast !== 'undefined') ? Toast.error(result.message) : alert(result.message);
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 发送验证码';
                }
            } catch (error) {
                (typeof Toast !== 'undefined') ? Toast.error('网络错误') : alert('网络错误');
                sendCodeBtn.disabled = false;
                sendCodeBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 发送验证码';
            }
        });
        
        // 倒计时函数
        function startCountdown() {
            emailCodeCountdown = 60;
            sendCodeBtn.disabled = true;
            
            emailCodeTimer = setInterval(function() {
                emailCodeCountdown--;
                sendCodeBtn.innerHTML = `${emailCodeCountdown}秒后重发`;
                
                if (emailCodeCountdown <= 0) {
                    clearInterval(emailCodeTimer);
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 重新发送';
                }
            }, 1000);
        }
    }
    
    // 初始化 Select2（防止重复初始化）
    function initSelect2Components() {
        if (select2Initialized) {
            return;
        }
        
        if (typeof $ === 'undefined' || !$.fn.select2) {
            return;
        }
        
        try {
            // 销毁已存在的 Select2 实例
            $('.select2, .select2-search').each(function() {
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }
            });
            
            // 重新初始化
            $('.select2, .select2-search').select2({
                width: '100%',
                language: 'zh-CN',
                allowClear: true,
                placeholder: '请选择'
            });
            
            select2Initialized = true;
            // 
        } catch (e) {
            
        }
    }
    
    // 显示提示信息（只显示一次）
    function showTipModal() {
        // 检查是否已经显示过
        // if (sessionStorage.getItem('editInfoTipShown')) {
        //     
        //     return;
        // }
        
        // 
        
        // 方案1: 使用 Modal.alert
        if (typeof Modal !== 'undefined' && typeof Modal.alert === 'function') {
            try {
                Modal.alert('只需填写需要修改的字段即可，未填写的字段将保持原值。', function() {
                    // sessionStorage.setItem('editInfoTipShown', 'true');
                    // Modal.alert('只需填写需要修改的字段即可，未填写的字段将保持原值。');
                });
                // 
                return;
            } catch (e) {
                
            }
        }
        
        // 方案2: 使用原生 alert（降级方案）
        try {
            alert('温馨提示：只需填写需要修改的字段即可，未填写的字段将保持原值。');
            // sessionStorage.setItem('editInfoTipShown', 'true');
            // 
        } catch (e) {
            
        }
    }
    
    // 表单提交处理
    async function handleFormSubmit(event) {
        
        
        // 阻止默认行为
        if (event) {
            if (event.preventDefault) event.preventDefault();
            if (event.stopPropagation) event.stopPropagation();
            if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        }
        
        // 防止重复提交
        if (isSubmitting) {
            
            return false;
        }
        
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('editInfoForm');
        
        if (!form || !submitBtn) {
            
            return false;
        }
        
        isSubmitting = true;
        
        try {
            // 收集表单数据
            const formData = new FormData(form);
            const data = {};
            
            for (const [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            // 过滤空值
            const changedData = {};
            let hasChange = false;
            
            for (const [key, value] of Object.entries(data)) {
                if (value && value.trim() !== '') {
                    changedData[key] = value.trim();
                    hasChange = true;
                }
            }
            
            
            
            // 如果修改了邮箱，必须验证验证码
            if (changedData.email) {
                if (!changedData.email_code) {
                    (typeof Toast !== 'undefined')
                        ? Toast.error('请输入邮箱验证码')
                        : alert('请输入邮箱验证码');
                    isSubmitting = false;
                    return false;
                }
                
                if (!emailCodeSent) {
                    (typeof Toast !== 'undefined')
                        ? Toast.warning('请先发送验证码')
                        : alert('请先发送验证码');
                    isSubmitting = false;
                    return false;
                }
            }
            
            if (!hasChange) {
                (typeof Toast !== 'undefined')
                    ? Toast.warning('请填写需要修改的字段')
                    : alert('请填写需要修改的字段');
                isSubmitting = false;
                return false;
            }
            
            // 验证身份证
            if (changedData.id_card) {
                const isValid = (typeof Validator !== 'undefined' && Validator.validateIdCard)
                    ? Validator.validateIdCard(changedData.id_card)
                    : /^\d{17}[\dXx]$/.test(changedData.id_card);
                
                if (!isValid) {
                    (typeof Toast !== 'undefined') 
                        ? Toast.error('身份证号格式不正确')
                        : alert('身份证号格式不正确');
                    isSubmitting = false;
                    return false;
                }
            }
            
            // 验证手机号
            if (changedData.phone) {
                const isValid = (typeof Validator !== 'undefined' && Validator.validatePhone)
                    ? Validator.validatePhone(changedData.phone)
                    : /^1[3-9]\d{9}$/.test(changedData.phone);
                
                if (!isValid) {
                    (typeof Toast !== 'undefined')
                        ? Toast.error('手机号格式不正确')
                        : alert('手机号格式不正确');
                    isSubmitting = false;
                    return false;
                }
            }
            
            // 验证邮箱
            if (changedData.email) {
                const isValid = (typeof Validator !== 'undefined' && Validator.validateEmail)
                    ? Validator.validateEmail(changedData.email)
                    : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(changedData.email);
                
                if (!isValid) {
                    (typeof Toast !== 'undefined')
                        ? Toast.error('邮箱格式不正确')
                        : alert('邮箱格式不正确');
                    isSubmitting = false;
                    return false;
                }
            }
            
            // 禁用提交按钮
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 提交中...';
            
            
            
            // 添加CSRF Token
            changedData.csrf_token = '<?php echo $csrfToken; ?>';
            
            // 发送请求
            const response = await fetch('/api/student/edit_info.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                },
                body: JSON.stringify(changedData)
            });
            
            
            
            const result = await response.json();
            
            
            if (result.success) {
                (typeof Toast !== 'undefined')
                    ? Toast.success(result.message || '提交成功')
                    : alert(result.message || '提交成功');
                
                // 清除提示已显示的标记
                // sessionStorage.removeItem('editInfoTipShown');
                
                setTimeout(function() {
                    window.location.href = '/pages/student/index.php';
                }, 1500);
            } else {
                if (result.redirect) {
                    const confirmMsg = result.message || '有待审核的信息，是否前往查看？';
                    
                    if (typeof Modal !== 'undefined' && Modal.confirm) {
                        Modal.confirm(confirmMsg, function() {
                            window.location.href = result.redirect;
                        });
                    } else {
                        if (confirm(confirmMsg)) {
                            window.location.href = result.redirect;
                        }
                    }
                } else {
                    (typeof Toast !== 'undefined')
                        ? Toast.error(result.message || '提交失败')
                        : alert(result.message || '提交失败');
                }
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 提交审核';
                isSubmitting = false;
            }
            
        } catch (error) {
            
            
            (typeof Toast !== 'undefined')
                ? Toast.error('网络错误，请稍后重试')
                : alert('网络错误，请稍后重试');
            
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 提交审核';
            }
            
            isSubmitting = false;
        }
        
        return false;
    }
    
    // 初始化表单
    function initForm() {
        if (isFormInitialized) {
            
            return;
        }
        
        // 
        
        const form = document.getElementById('editInfoForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (!form) {
            
            return;
        }
        
        if (!submitBtn) {
            
            return;
        }
        
        // 修改按钮类型为 button（关键修复）
        submitBtn.type = 'button';
        
        // 使用 onsubmit（最佳兼容性）
        form.onsubmit = function(e) {
            
            handleFormSubmit(e);
            return false;
        };
        
        // 按钮点击事件
        submitBtn.onclick = function(e) {
            
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            handleFormSubmit(e);
            return false;
        };
        
        isFormInitialized = true;
        // 
    }
    
    // 页面初始化主函数
    function initPage() {
        

        // 初始化 Select2
        initSelect2Components();

        // 初始化邮箱验证
        initEmailVerification();

        // 初始化政治面貌变更逻辑
        initPoliticalStatusLogic();

        // 延迟显示提示信息
        setTimeout(showTipModal, 500);

        // 初始化表单
        initForm();

        
    }

    // 初始化政治面貌变更逻辑
    function initPoliticalStatusLogic() {
        const politicalStatusSelect = document.getElementById('politicalStatusSelect');
        const activistDateGroup = document.getElementById('activistDateGroup');
        const probationaryDateGroup = document.getElementById('probationaryDateGroup');
        const fullMemberDateGroup = document.getElementById('fullMemberDateGroup');

        const activistDateInput = document.getElementById('activistDateInput');
        const probationaryDateInput = document.getElementById('probationaryDateInput');
        const fullMemberDateInput = document.getElementById('fullMemberDateInput');

        // 从PHP传递的配置
        const systemActivistDate = '<?php echo $activistDate; ?>';
        const systemProbationaryDate = '<?php echo $probationaryDate; ?>';
        const systemFullMemberDate = '<?php echo $fullMemberDate; ?>';

        // 当前政治面貌
        const currentPoliticalStatus = '<?php echo $studentInfo['political_status']; ?>';

        if (!politicalStatusSelect) return;

        // 监听政治面貌选择变化
        $(politicalStatusSelect).on('change', function() {
            const selectedStatus = this.value;

            // 重置所有字段
            activistDateGroup.style.display = 'block';
            probationaryDateGroup.style.display = 'none';
            fullMemberDateGroup.style.display = 'none';

            activistDateInput.removeAttribute('readonly');
            probationaryDateInput.removeAttribute('readonly');
            fullMemberDateInput.removeAttribute('readonly');

            activistDateInput.value = '';
            probationaryDateInput.value = '';
            fullMemberDateInput.value = '';

            if (!selectedStatus) {
                return;
            }

            // 根据当前政治面貌和目标政治面貌处理
            if (currentPoliticalStatus === '入党积极分子') {
                if (selectedStatus === '预备党员' || selectedStatus === '中共预备党员') {
                    // 积极分子 -> 预备党员
                    probationaryDateGroup.style.display = 'block';
                    probationaryDateInput.value = systemProbationaryDate;
                    probationaryDateInput.setAttribute('readonly', 'readonly');

                    fullMemberDateGroup.style.display = 'none';
                }
            } else if (currentPoliticalStatus === '预备党员' || currentPoliticalStatus === '中共预备党员') {
                if (selectedStatus === '中共党员' || selectedStatus === '正式党员') {
                    // 预备党员 -> 正式党员
                    probationaryDateGroup.style.display = 'block';
                    fullMemberDateGroup.style.display = 'block';

                    fullMemberDateInput.value = systemFullMemberDate;
                    fullMemberDateInput.setAttribute('readonly', 'readonly');
                }
            } else if (currentPoliticalStatus === '中共党员' || currentPoliticalStatus === '正式党员') {
                // 已经是正式党员，所有时间都可以修改
                probationaryDateGroup.style.display = 'block';
                fullMemberDateGroup.style.display = 'block';
            }
        });
    }
    
    // 确保页面加载完成后执行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            
            setTimeout(initPage, 100);
        });
    } else {
        
        setTimeout(initPage, 100);
    }
    
})();

// 清除表单数据（全局函数）
function clearForm() {
    
    
    const form = document.getElementById('editInfoForm');
    if (!form) {
        
        return;
    }
    
    const inputs = form.querySelectorAll('input:not([disabled]), select:not([disabled])');
    
    inputs.forEach(function(input) {
        if (input.tagName === 'SELECT') {
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $(input).val(null).trigger('change');
            } else {
                input.value = '';
            }
        } else {
            input.value = '';
        }
    });
    
    (typeof Toast !== 'undefined')
        ? Toast.success('已清除填写的数据')
        : alert('已清除填写的数据');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
