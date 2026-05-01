<?php
/**
 * 登录页面
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/version.php';

$auth = new Auth();

// 检查是否已登录
if ($auth->isLoggedIn() || $auth->checkRememberToken()) {
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['student', 'teacher', 'admin', 'superadmin'], true)) {
        $auth->logout();
        header('Location: /index.php');
        exit;
    }

    // 系统管理员使用admin页面
    if ($role === 'superadmin') {
        $role = 'admin';
    }
    header("Location: /pages/{$role}/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 生化学院党员信息管理系统</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23c41e3a%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2240%22>党</text></svg>">
    <script>
        (function() {
            var theme = 'light';
            try {
                var storedTheme = localStorage.getItem('theme');
                if (storedTheme !== 'dark' && storedTheme !== 'light') {
                    var match = document.cookie.match(/(?:^|;\s*)theme=(dark|light)(?:;|$)/);
                    storedTheme = match ? match[1] : '';
                }
                if (storedTheme === 'dark' || storedTheme === 'light') {
                    theme = storedTheme;
                }
            } catch (error) {
                var cookieMatch = document.cookie.match(/(?:^|;\s*)theme=(dark|light)(?:;|$)/);
                if (cookieMatch) {
                    theme = cookieMatch[1];
                }
            }
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme === 'dark' ? 'dark' : 'light';
        })();
    </script>
    <link rel="stylesheet" href="<?php echo getVersionedAsset('/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdn.staticfile.net/font-awesome/6.4.0/css/all.min.css">
    <script src="<?php echo getVersionedAsset('/assets/js/theme.js'); ?>"></script>
    <style>
        /* ==================== 重新设计的登录页面样式 ==================== */

        /* 覆盖默认的登录容器背景 */
        .login-container {
            /* 纯红色背景，添加微妙的纹理效果 */
            background: #c41e3a !important;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.02) 0%, transparent 50%),
                linear-gradient(180deg, rgba(0, 0, 0, 0.02) 0%, transparent 100%) !important;
            position: relative;
            overflow: hidden;
        }

        /* 添加动态光效 */
        .login-container::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(212, 167, 86, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 20s ease-in-out infinite;
        }

        .login-container::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -20%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.04) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 25s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.05); }
        }

        /* 登录框样式优化 */
        .login-box {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow:
                0 20px 60px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
        }

        /* Logo 样式优化 */
        .login-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 12px rgba(196, 30, 58, 0.3));
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        /* 标题样式 */
        .login-title {
            background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .login-subtitle {
            color: #6c757d;
            font-size: 14px;
            font-weight: 400;
        }

        /* 角色选择按钮优化 */
        .role-btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .role-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(196, 30, 58, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .role-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .role-btn.active {
            background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(196, 30, 58, 0.4);
            transform: translateY(-2px);
        }

        /* 输入框优化 */
        .form-control {
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
        }

        .form-control:focus {
            border-color: #c41e3a;
            box-shadow: 0 0 0 4px rgba(196, 30, 58, 0.1);
        }

        /* 登录按钮优化 */
        .btn-primary {
            background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
            border: none;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(196, 30, 58, 0.4);
        }

        /* 备案信息样式 */
        .icp-footer {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            text-align: center;
            padding: 12px 24px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .icp-footer a {
            color: rgba(255, 255, 255, 0.95);
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .icp-footer a:hover {
            color: #ffffff;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
        }

        .icp-footer a::before {
            content: '🔒';
            font-size: 12px;
        }

        /* 更新通知弹窗样式 */
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 0;
            max-width: 450px;
            width: 90%;
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
        }
        
        .update-notice-highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .update-notice-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
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
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray-color);
        }
        .password-wrapper {
            position: relative;
        }
        .login-error {
            background: #fff3f3;
            color: var(--danger-color);
            padding: 12px;
            border-radius: var(--border-radius);
            margin-bottom: 16px;
            display: none;
            font-size: 14px;
            border-left: 4px solid var(--danger-color);
        }
        .login-error.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        /* Keep the login screen inside one viewport without page scrolling. */
        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        .login-container {
            height: 100vh;
            height: 100dvh;
            min-height: 0;
            box-sizing: border-box;
            padding: 12px 20px 54px;
        }

        .login-box {
            max-width: 420px;
            max-height: calc(100vh - 78px);
            max-height: calc(100dvh - 78px);
            padding: clamp(20px, 3vh, 32px) 32px;
            box-sizing: border-box;
        }

        .login-header {
            margin-bottom: clamp(14px, 2.4vh, 22px);
        }

        .login-logo {
            width: clamp(54px, 8vh, 68px);
            height: clamp(54px, 8vh, 68px);
            margin-bottom: clamp(8px, 1.5vh, 14px);
        }

        .login-title {
            font-size: clamp(20px, 2.7vh, 24px);
            margin-bottom: 6px;
            line-height: 1.25;
        }

        .login-subtitle {
            margin: 0;
            font-size: 13px;
        }

        .role-selector {
            gap: 8px;
            margin-bottom: clamp(12px, 2vh, 18px);
        }

        .role-btn {
            padding: clamp(9px, 1.5vh, 12px) 10px;
            font-size: 13px;
        }

        .role-btn i {
            font-size: clamp(18px, 2.7vh, 22px);
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: clamp(10px, 1.8vh, 14px);
        }

        .form-label {
            margin-bottom: 5px;
        }

        .form-control {
            padding: clamp(10px, 1.7vh, 12px) 14px;
        }

        .login-footer {
            margin-top: clamp(10px, 1.7vh, 14px);
            padding-top: clamp(10px, 1.7vh, 14px);
        }

        .btn-lg {
            padding: clamp(11px, 1.8vh, 13px) 28px;
            font-size: 15px;
        }

        .icp-footer {
            bottom: 10px;
            padding: 8px 18px;
        }

        .icp-footer a {
            font-size: 12px;
        }

        @media (max-height: 700px) {
            .login-container {
                padding: 8px 16px 44px;
            }

            .login-box {
                max-height: calc(100vh - 58px);
                max-height: calc(100dvh - 58px);
                padding: 16px 28px;
            }

            .login-logo {
                width: 48px;
                height: 48px;
                margin-bottom: 6px;
            }

            .login-title {
                font-size: 19px;
                margin-bottom: 4px;
            }

            .login-subtitle {
                font-size: 12px;
            }

            .role-btn {
                padding: 8px 10px;
            }

            .form-control {
                padding: 9px 12px;
            }

            .icp-footer {
                bottom: 6px;
                padding: 6px 14px;
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 响应式优化 */
        @media (max-width: 768px) {
            .login-container::before,
            .login-container::after {
                display: none;
            }

            .icp-footer {
                bottom: 10px;
                padding: 8px 16px;
            }

            .icp-footer a {
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 10px 12px 48px;
            }

            .login-box {
                padding: 18px 16px;
                max-width: 100%;
            }

            .role-selector {
                grid-template-columns: repeat(2, 1fr);
            }

            .role-btn {
                padding-left: 6px;
                padding-right: 6px;
            }

            .role-btn span {
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="auth-page">
    <div class="login-container auth-shell">
        <div class="auth-orbit auth-orbit-one"></div>
        <div class="auth-orbit auth-orbit-two"></div>
        <div class="auth-stage">
            <section class="auth-brief" aria-label="系统说明">
                <div class="auth-seal">
                    <a href="https://shxy.bdu.edu.cn/" target="_blank" rel="noopener noreferrer" aria-label="访问生化学院官网">
                        <img src="/assets/images/logo.png" alt="生化学院标识" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23c41e3a%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2240%22>党</text></svg>'">
                    </a>
                </div>
                <p class="auth-kicker">智慧党建 · 信息治理 · 安全协同</p>
                <h2>党建引领 科研强院</h2>
                <p class="auth-copy">统一管理党员发展、学生信息、审核流转与数据统计，让日常工作清晰、稳妥、可追溯。</p>
                <div class="auth-metrics">
                    <span>身份分级</span>
                    <span>实时审核</span>
                    <span>数据看板</span>
                </div>
            </section>

            <div class="login-box auth-card">
            <div class="login-header">
                <a href="https://shxy.bdu.edu.cn/" target="_blank" rel="noopener noreferrer" aria-label="访问生化学院官网">
                    <img src="/assets/images/logo.png" alt="Logo" class="login-logo" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23c41e3a%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2240%22>党</text></svg>'">
                </a>
                <h1 class="login-title">生化学院党员信息管理系统</h1>
                <p class="login-subtitle">欢迎使用，请选择身份登录</p>
            </div>
            
            <div class="login-error" id="loginError"></div>
            
            <form id="loginForm">
                <!-- 角色选择 -->
                <div class="role-selector">
                    <div class="role-btn active" data-role="student">
                        <i class="fa-solid fa-user-graduate"></i>
                        <span>学生</span>
                    </div>
                    <div class="role-btn" data-role="teacher">
                        <i class="fa-solid fa-chalkboard-teacher"></i>
                        <span>教师</span>
                    </div>
                    <div class="role-btn" data-role="admin">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>管理员</span>
                    </div>
                    <div class="role-btn" data-role="superadmin">
                        <i class="fa-solid fa-user-cog"></i>
                        <span>系统管理员</span>
                    </div>
                </div>
                <input type="hidden" name="role" id="roleInput" value="student">
                
                <!-- 用户名 -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fa-solid fa-user"></i> 
                        <span id="usernameLabel">学号</span>
                    </label>
                    <input type="text" class="form-control" name="username" id="username" placeholder="请输入学号" required autocomplete="username">
                </div>
                
                <!-- 密码 -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fa-solid fa-lock"></i> 密码
                    </label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" name="password" id="password" placeholder="请输入密码" required autocomplete="current-password">
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>
                
                <!-- 记住登录 -->
                <div class="login-footer">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="remember" id="remember">
                        <label class="form-check-label" for="remember">保持登录七天</label>
                    </div>
                    <button type="button" class="link-btn" onclick="showForgetPassword()">忘记密码</button>
                    <!--<a href="pages/forgot_password.php" style="color: #667eea; text-decoration: none; font-size: 14px;">忘记密码?</a>-->
                </div>
                
                <!-- 登录按钮 -->
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
                    <i class="fa-solid fa-right-to-bracket"></i> 登 录
                </button>
            </form>
            </div>
        </div>

        <!-- 备案信息 -->
        <div class="icp-footer">
            <a href="https://beian.miit.gov.cn/#/Integrated/index" target="_blank" rel="noopener noreferrer">
                鲁ICP备2025185591号-1
            </a>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <script>
        // 角色选择
        const roleBtns = document.querySelectorAll('.role-btn');
        const roleInput = document.getElementById('roleInput');
        const usernameLabel = document.getElementById('usernameLabel');
        const usernameInput = document.getElementById('username');
        
        const roleLabels = {
            student: { label: '学号', placeholder: '请输入学号' },
            teacher: { label: '教师工号', placeholder: '请输入教师工号' },
            admin: { label: '管理员账号', placeholder: '请输入管理员账号' },
            superadmin: { label: '系统管理员账号', placeholder: '请输入系统管理员账号' }
        };
        
        roleBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                roleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const role = this.dataset.role;
                roleInput.value = role;
                usernameLabel.textContent = roleLabels[role].label;
                usernameInput.placeholder = roleLabels[role].placeholder;
            });
        });
        
        // 密码显示/隐藏
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
        
        // 忘记密码
        function showForgetPassword() {
            // Modal.alert('请联系管理员重置密码');
            window.location.href="pages/forgot_password.php";
        }
        
        // 登录表单提交
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const loginBtn = document.getElementById('loginBtn');
            const loginError = document.getElementById('loginError');
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const role = roleInput.value;
            const remember = document.getElementById('remember').checked;
            
            if (!username || !password) {
                loginError.textContent = '请输入用户名和密码';
                loginError.classList.add('show');
                return;
            }
            
            // 禁用按钮
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 登录中...';
            loginError.classList.remove('show');
            
            try {
                const response = await Ajax.post('/api/login.php', {
                    username,
                    password,
                    role,
                    remember
                });
                
                if (response.success) {
                    if (window.SHXYTheme) {
                        window.SHXYTheme.persist(window.SHXYTheme.get());
                    }
                    const markerKey = 'shxy_ephemeral_login';
                    try {
                        if (remember) {
                            sessionStorage.removeItem(markerKey);
                        } else {
                            sessionStorage.setItem(markerKey, '1');
                        }
                    } catch (error) {
                    }
                    Toast.success('登录成功，正在跳转...');
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 500);
                } else {
                    loginError.textContent = response.message || '登录失败';
                    loginError.classList.add('show');
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> 登 录';
                }
            } catch (error) {
                loginError.textContent = '网络错误，请稍后重试';
                loginError.classList.add('show');
                loginBtn.disabled = false;
                loginBtn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> 登 录';
            }
        });
        
        // 页面加载时显示美化的更新提示
        window.addEventListener('DOMContentLoaded', function() {
            const updateShown = sessionStorage.getItem('update_notice_2025');
            
            if (!updateShown) {
                setTimeout(() => {
                    showUpdateNotice();
                }, 500);
            }
        });
        
        // 显示美化的更新通知
        function showUpdateNotice() {
            const overlay = document.createElement('div');
            overlay.className = 'update-notice-overlay';
            overlay.innerHTML = `
                <div class="update-notice-box">
                    <div class="update-notice-header">
                        <div class="update-notice-icon">🎉</div>
                        <h2 class="update-notice-title">系统更新通知</h2>
                    </div>
                    <div class="update-notice-content">
                        <p><strong>📢 本次更新详情：</strong></p>
                        <p>新增功能：学生用户可以通过邮箱找回密码</p>
                        <div class="update-notice-highlight">
                            <i class="fa-solid fa-envelope"></i> 
                            请在登录后第一时间完善邮箱信息！
                        </div>
                    </div>
                    <div class="update-notice-footer">
                        <button class="update-notice-btn" onclick="closeUpdateNotice()">
                            <i class="fa-solid fa-check"></i> 我知道了
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
            sessionStorage.setItem('update_notice_2025', 'true');
        }
        
        // 关闭更新通知
        function closeUpdateNotice() {
            const overlay = document.querySelector('.update-notice-overlay');
            if (overlay) {
                overlay.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => overlay.remove(), 300);
            }
        }
    </script>
</body>
</html>
