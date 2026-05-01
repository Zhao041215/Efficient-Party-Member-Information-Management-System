<?php
require_once __DIR__ . '/../includes/version.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置密码 - 生化学院党员信息管理系统</title>
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
        html,
        body {
            min-height: 100%;
        }

        body.password-recovery-page {
            margin: 0;
            background: #c41e3a;
        }

        .password-recovery-page .auth-shell {
            min-height: 100vh !important;
            min-height: 100dvh !important;
            padding: clamp(24px, 6vw, 72px) 20px !important;
        }

        .reset-container {
            max-width: 450px;
            margin: 0 auto;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 16px 36px rgba(196, 30, 58, 0.12);
            border: 1px solid rgba(196, 30, 58, 0.08);
        }

        .password-recovery-page .auth-card-compact {
            width: min(100%, 450px) !important;
        }

        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .reset-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .reset-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #c41e3a;
            box-shadow: 0 0 0 3px rgba(196, 30, 58, 0.1);
        }
        .btn-reset {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .btn-reset:hover {
            opacity: 0.9;
        }
        .btn-reset:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .back-login {
            text-align: center;
            margin-top: 20px;
        }
        .back-login a {
            color: #c41e3a;
            text-decoration: none;
        }
        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }

        @media (max-width: 576px) {
            .password-recovery-page .auth-shell {
                align-items: center !important;
                padding: 18px 14px !important;
            }

            .reset-container {
                padding: 28px 20px;
            }
        }
    </style>
</head>
<body class="auth-page auth-page-compact password-recovery-page">
    <div class="auth-shell">
        <div class="auth-orbit auth-orbit-one"></div>
        <div class="auth-orbit auth-orbit-two"></div>
        <main class="reset-container auth-card auth-card-compact">
        <div class="reset-header">
            <div class="auth-mini-seal">
                <i class="fa-solid fa-key"></i>
            </div>
            <h2>重置密码</h2>
            <p>请输入邮箱中收到的验证码和新密码</p>
        </div>
        
        <div id="message" class="message"></div>
        
        <form id="resetForm">
            <input type="hidden" id="username" value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>">
            
            <div class="form-group">
                <label for="code">验证码</label>
                <input type="text" id="code" name="code" required placeholder="请输入6位验证码" maxlength="6">
            </div>
            
            <div class="form-group">
                <label for="newPassword">新密码</label>
                <input type="password" id="newPassword" name="newPassword" required placeholder="请输入新密码(至少6位)">
                <div id="passwordStrength" class="password-strength"></div>
            </div>
            
            <div class="form-group">
                <label for="confirmPassword">确认密码</label>
                <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="请再次输入新密码">
            </div>
            
            <button type="submit" class="btn-reset" id="resetBtn">重置密码</button>
        </form>
        
        <div class="back-login">
            <a href="../index.php">← 返回登录</a> | 
            <a href="forgot_password.php">重新获取验证码</a>
        </div>
        </main>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script>
        // 密码强度检测
        $('#newPassword').on('input', function() {
            const password = $(this).val();
            const $strength = $('#passwordStrength');
            
            if (password.length === 0) {
                $strength.text('');
                return;
            }
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                $strength.html('<span class="strength-weak">● 弱</span>');
            } else if (strength <= 3) {
                $strength.html('<span class="strength-medium">●● 中</span>');
            } else {
                $strength.html('<span class="strength-strong">●●● 强</span>');
            }
        });
        
        // 提交表单
        $('#resetForm').on('submit', function(e) {
            e.preventDefault();
            
            const username = $('#username').val();
            const code = $('#code').val().trim();
            const newPassword = $('#newPassword').val();
            const confirmPassword = $('#confirmPassword').val();
            
            if (!username) {
                showMessage('用户名参数错误', 'error');
                return;
            }
            
            if (code.length !== 6) {
                showMessage('请输入6位验证码', 'error');
                return;
            }
            
            if (newPassword.length < 6) {
                showMessage('密码长度至少6位', 'error');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showMessage('两次输入的密码不一致', 'error');
                return;
            }
            
            $('#resetBtn').prop('disabled', true).text('处理中...');
            
            // 先验证验证码
            $.ajax({
                url: '../api/verify_reset_code.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ username, code }),
                success: function(response) {
                    if (response.success) {
                        // 验证码正确,重置密码
                        resetPassword(response.token, newPassword);
                    } else {
                        showMessage(response.message, 'error');
                        $('#resetBtn').prop('disabled', false).text('重置密码');
                    }
                },
                error: function() {
                    showMessage('网络错误,请稍后重试', 'error');
                    $('#resetBtn').prop('disabled', false).text('重置密码');
                }
            });
        });
        
        function resetPassword(token, newPassword) {
            $.ajax({
                url: '../api/reset_password_with_code.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ token, new_password: newPassword }),
                success: function(response) {
                    if (response.success) {
                        showMessage(response.message, 'success');
                        setTimeout(function() {
                            window.location.href = '../index.php';
                        }, 2000);
                    } else {
                        showMessage(response.message, 'error');
                        $('#resetBtn').prop('disabled', false).text('重置密码');
                    }
                },
                error: function() {
                    showMessage('网络错误,请稍后重试', 'error');
                    $('#resetBtn').prop('disabled', false).text('重置密码');
                }
            });
        }
        
        function showMessage(msg, type) {
            const $message = $('#message');
            $message.removeClass('success error').addClass(type);
            $message.text(msg).fadeIn();
            setTimeout(function() {
                $message.fadeOut();
            }, 5000);
        }
    </script>
</body>
</html>
