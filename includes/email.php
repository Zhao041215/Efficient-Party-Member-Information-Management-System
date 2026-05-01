<?php
/**
 * 邮件发送类
 * 使用原生mail函数或SMTP
 * 包含频率限制、日志记录和重试机制
 */
class EmailSender {
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    private $from_email;
    private $from_name;
    private $db;

    // 频率限制配置
    private const RATE_LIMIT_WINDOW = 300; // 5分钟
    private const RATE_LIMIT_MAX_EMAILS = 10; // 5分钟内最多发送10封
    private const RATE_LIMIT_PER_EMAIL = 60; // 同一邮箱60秒内只能发送1次

    // 重试配置
    private const MAX_RETRY_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 1000; // 1秒

    public function __construct() {
        $this->smtp_host = $_ENV['SMTP_HOST'] ?? '';
        $this->smtp_port = (int)($_ENV['SMTP_PORT'] ?? 587);
        $this->smtp_user = $_ENV['SMTP_USER'] ?? '';
        $this->smtp_pass = $_ENV['SMTP_PASS'] ?? '';
        $this->from_email = $_ENV['SMTP_FROM_EMAIL'] ?? '';
        $this->from_name = $_ENV['SMTP_FROM_NAME'] ?? '';

        // 获取数据库实例
        require_once __DIR__ . '/database.php';
        $this->db = Database::getInstance();
    }
    
    /**
     * 检查邮件发送频率限制
     */
    private function checkRateLimit($to_email, $user_id = null) {
        // 检查全局频率限制（5分钟内最多10封）
        $globalCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM email_send_log
             WHERE created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [self::RATE_LIMIT_WINDOW]
        );

        if ($globalCount && $globalCount['count'] >= self::RATE_LIMIT_MAX_EMAILS) {
            return ['success' => false, 'message' => '系统邮件发送过于频繁，请稍后再试'];
        }

        // 检查单个邮箱频率限制（60秒内只能发送1次）
        $emailCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM email_send_log
             WHERE to_email = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$to_email, self::RATE_LIMIT_PER_EMAIL]
        );

        if ($emailCount && $emailCount['count'] > 0) {
            return ['success' => false, 'message' => '发送过于频繁，请60秒后再试'];
        }

        return ['success' => true];
    }

    /**
     * 记录邮件发送日志
     */
    private function logEmailSend($user_id, $to_email, $subject, $status, $error_message = null) {
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $this->db->execute(
                "INSERT INTO email_send_log (user_id, to_email, subject, send_status, error_message, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$user_id, $to_email, $subject, $status ? 1 : 0, $error_message, $ip_address]
            );
        } catch (Exception $e) {
            // 静默处理错误
        }
    }

    /**
     * 发送验证码邮件（带重试机制）
     */
    public function sendVerificationCode($to_email, $code, $username, $user_id = null) {
        // 检查频率限制
        $rateLimitCheck = $this->checkRateLimit($to_email, $user_id);
        if (!$rateLimitCheck['success']) {
            return ['success' => false, 'message' => $rateLimitCheck['message']];
        }

        $subject = '密码重置验证码 - 生化学院党员管理 [' . date('Y-m-d H:i:s') . ']';
        $message = $this->getCodeEmailTemplate($username, $code);

        // 使用重试机制发送
        $result = $this->sendWithRetry($to_email, $subject, $message);

        // 记录日志
        $this->logEmailSend($user_id, $to_email, $subject, $result['success'], $result['error'] ?? null);

        return $result;
    }
    
    /**
     * 发送邮箱绑定验证码（带重试机制）
     */
    public function sendBindingCode($to_email, $code, $user_id = null) {
        // 检查频率限制
        $rateLimitCheck = $this->checkRateLimit($to_email, $user_id);
        if (!$rateLimitCheck['success']) {
            return ['success' => false, 'message' => $rateLimitCheck['message']];
        }

        $subject = '邮箱绑定验证码 - 生化学院党员管理 [' . date('Y-m-d H:i:s') . ']';
        $message = $this->getBindingEmailTemplate($code);

        // 使用重试机制发送
        $result = $this->sendWithRetry($to_email, $subject, $message);

        // 记录日志
        $this->logEmailSend($user_id, $to_email, $subject, $result['success'], $result['error'] ?? null);

        return $result;
    }

    /**
     * 带重试机制的邮件发送
     */
    private function sendWithRetry($to_email, $subject, $message) {
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRY_ATTEMPTS; $attempt++) {
            // 尝试使用SMTP发送
            if (function_exists('fsockopen')) {
                $result = $this->sendViaSMTP($to_email, $subject, $message);
                if ($result === true) {
                    return ['success' => true];
                }
                $lastError = "SMTP发送失败";
            } else {
                // 降级使用mail函数
                $result = $this->sendViaMail($to_email, $subject, $message);
                if ($result === true) {
                    return ['success' => true];
                }
                $lastError = "Mail函数发送失败";
            }

            // 如果不是最后一次尝试，等待后重试
            if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }
        }

        return ['success' => false, 'error' => $lastError, 'message' => '邮件发送失败，请稍后重试'];
    }
    
    /**
     * 获取验证码邮件模板
     */
    private function getCodeEmailTemplate($username, $code) {
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .code-box { background: white; border: 2px dashed #667eea; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; }
        .warning { color: #e74c3c; font-size: 14px; margin-top: 20px; }
        .footer { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>密码重置验证</h1>
        </div>
        <div class='content'>
            <p>尊敬的 <strong>{$username}</strong>,</p>
            <p>您正在进行密码重置操作,您的验证码是:</p>
            <div class='code-box'>
                <div class='code'>{$code}</div>
            </div>
            <p class='warning'>
                ⚠️ 验证码有效期为 <strong>10分钟</strong>,请尽快使用。<br>
                ⚠️ 如果这不是您本人的操作,请忽略此邮件并立即修改密码。
            </p>
            <div class='footer'>
                <p>此邮件由系统自动发送,请勿直接回复</p>
                <p>&copy; 2025 生化学院党员信息管理系统</p>
            </div>
        </div>
    </div>
</body>
</html>
        ";
    }
    
    /**
     * 获取邮箱绑定验证码模板
     */
    private function getBindingEmailTemplate($code) {
        $sendTime = date('Y-m-d H:i:s');
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .code-box { background: white; border: 2px dashed #667eea; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; }
        .warning { color: #e74c3c; font-size: 14px; margin-top: 20px; }
        .footer { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
        .time-info { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 15px 0; font-size: 13px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>📧 邮箱绑定验证</h1>
        </div>
        <div class='content'>
            <p>您好,</p>
            <p>您正在绑定邮箱到生化学院党员信息管理系统,您的验证码是:</p>
            <div class='code-box'>
                <div class='code'>{$code}</div>
            </div>
            <div class='time-info'>
                <strong>📅 发送时间：</strong>{$sendTime}
            </div>
            <p class='warning'>
                ⚠️ 验证码有效期为 <strong>10分钟</strong>,请尽快使用。<br>
                ⚠️ 如果这不是您本人的操作,请忽略此邮件。
            </p>
            <div class='footer'>
                <p>此邮件由系统自动发送,请勿直接回复</p>
                <p>&copy; 2025 生化学院党员信息管理系统</p>
            </div>
        </div>
    </div>
</body>
</html>
        ";
    }
    
    /**
     * 通过SMTP发送邮件
     */
    private function sendViaSMTP($to_email, $subject, $message) {
        try {
            // 连接到SMTP服务器
            $socket = @fsockopen('ssl://' . $this->smtp_host, $this->smtp_port, $errno, $errstr, 30);
            
            if (!$socket) {
                return false;
            }
            
            // 读取欢迎消息
            $this->readSMTPResponse($socket);
            
            // EHLO
            fputs($socket, "EHLO " . $this->smtp_host . "\r\n");
            $this->readSMTPResponse($socket);
            
            // AUTH LOGIN
            fputs($socket, "AUTH LOGIN\r\n");
            $this->readSMTPResponse($socket);
            
            // 发送用户名
            fputs($socket, base64_encode($this->smtp_user) . "\r\n");
            $this->readSMTPResponse($socket);
            
            // 发送密码
            fputs($socket, base64_encode($this->smtp_pass) . "\r\n");
            $response = $this->readSMTPResponse($socket);
            
            if (strpos($response, '235') === false) {
                fclose($socket);
                return false;
            }
            
            // MAIL FROM
            fputs($socket, "MAIL FROM: <{$this->from_email}>\r\n");
            $this->readSMTPResponse($socket);
            
            // RCPT TO
            fputs($socket, "RCPT TO: <{$to_email}>\r\n");
            $this->readSMTPResponse($socket);
            
            // DATA
            fputs($socket, "DATA\r\n");
            $this->readSMTPResponse($socket);
            
            // 邮件头和内容
            $email_content = "From: {$this->from_name} <{$this->from_email}>\r\n";
            $email_content .= "To: <{$to_email}>\r\n";
            $email_content .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $email_content .= "MIME-Version: 1.0\r\n";
            $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
            $email_content .= "Content-Transfer-Encoding: base64\r\n";
            $email_content .= "\r\n";
            $email_content .= chunk_split(base64_encode($message));
            $email_content .= "\r\n.\r\n";
            
            fputs($socket, $email_content);
            $this->readSMTPResponse($socket);
            
            // QUIT
            fputs($socket, "QUIT\r\n");
            $response = $this->readSMTPResponse($socket);
            
            fclose($socket);

            // 添加短暂延迟，确保邮件发送完成
            usleep(100000); // 100毫秒

            return true;

        } catch (Exception $e) {
            if (isset($socket) && is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }
    }
    
    /**
     * 读取SMTP响应
     */
    private function readSMTPResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return $response;
    }
    
    /**
     * 使用mail函数发送(备用方案)
     */
    private function sendViaMail($to_email, $subject, $message) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>\r\n";
        
        return @mail($to_email, $subject, $message, $headers);
    }
}
?>