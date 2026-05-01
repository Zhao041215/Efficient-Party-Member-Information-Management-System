<?php
require_once __DIR__ . '/../includes/version.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘记密码 - 生化学院党员信息管理系统</title>
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

        .forgot-container {
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

        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .forgot-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .forgot-header p {
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
        .btn-send {
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
        .btn-send:hover {
            opacity: 0.9;
        }
        .btn-send:disabled {
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
        .back-login a:hover {
            text-decoration: underline;
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
        .countdown {
            color: #999;
            font-size: 14px;
        }

        @media (max-width: 576px) {
            .password-recovery-page .auth-shell {
                align-items: center !important;
                padding: 18px 14px !important;
            }

            .forgot-container {
                padding: 28px 20px;
            }
        }
    </style>
</head>
<body class="auth-page auth-page-compact password-recovery-page">
    <div class="auth-shell">
        <div class="auth-orbit auth-orbit-one"></div>
        <div class="auth-orbit auth-orbit-two"></div>
        <main class="forgot-container auth-card auth-card-compact">
        <div class="forgot-header">
            <div class="auth-mini-seal">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h2>找回密码</h2>
            <p>请输入您的用户名和注册邮箱,我们将发送验证码到您的邮箱</p>
        </div>
        
        <div id="message" class="message"></div>
        
        <form id="forgotForm">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required placeholder="请输入用户名">
            </div>
            
            <div class="form-group">
                <label for="email">邮箱地址</label>
                <input type="email" id="email" name="email" required placeholder="请输入注册邮箱">
            </div>
            
            <button type="submit" class="btn-send" id="sendBtn">
                <span id="btnText">发送验证码</span>
                <span id="countdown" class="countdown" style="display:none;"></span>
            </button>
        </form>
        
        <div class="back-login">
            <a href="../index.php">← 返回登录</a>
        </div>
        </main>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script>
        let countdownTimer = null;
        
        $('#forgotForm').on('submit', function(e) {
            e.preventDefault();
            
            const username = $('#username').val().trim();
            const email = $('#email').val().trim();
            
            if (!username || !email) {
                showMessage('请填写完整信息', 'error');
                return;
            }
            
            // 禁用按钮
            $('#sendBtn').prop('disabled', true);
            $('#btnText').text('发送中...');
            
            $.ajax({
                url: '../api/forgot_password.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ username, email }),
                success: function(response) {
                    if (response.success) {
                        showMessage(response.message, 'success');
                        startCountdown(60);
                        // 3秒后跳转到重置密码页面
                        setTimeout(function() {
                            window.location.href = 'reset_password.php?username=' + encodeURIComponent(username);
                        }, 3000);
                    } else {
                        showMessage(response.message, 'error');
                        $('#sendBtn').prop('disabled', false);
                        $('#btnText').text('发送验证码');
                    }
                },
                error: function() {
                    showMessage('网络错误,请稍后重试', 'error');
                    $('#sendBtn').prop('disabled', false);
                    $('#btnText').text('发送验证码');
                }
            });
        });
        
        function showMessage(msg, type) {
            const $message = $('#message');
            $message.removeClass('success error').addClass(type);
            $message.text(msg).fadeIn();
            setTimeout(function() {
                $message.fadeOut();
            }, 5000);
        }
        
        function startCountdown(seconds) {
            let remaining = seconds;
            $('#btnText').hide();
            $('#countdown').show().text(remaining + '秒后可重新发送');
            
            countdownTimer = setInterval(function() {
                remaining--;
                if (remaining > 0) {
                    $('#countdown').text(remaining + '秒后可重新发送');
                } else {
                    clearInterval(countdownTimer);
                    $('#countdown').hide();
                    $('#btnText').show().text('重新发送');
                    $('#sendBtn').prop('disabled', false);
                }
            }, 1000);
        }
    </script>
</body>
</html>
