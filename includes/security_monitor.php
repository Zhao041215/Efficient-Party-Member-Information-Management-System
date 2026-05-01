<?php
/**
 * 安全监控类
 * 用于检测和记录异常安全事件
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/email.php';

class SecurityMonitor {
    private $db;
    private static $instance = null;

    // 告警阈值配置
    const FAILED_LOGIN_THRESHOLD = 5;           // 5次失败登录触发告警
    const FAILED_LOGIN_WINDOW = 300;            // 5分钟内
    const SUSPICIOUS_IP_THRESHOLD = 10;         // 同一IP 10次失败触发封禁
    const SUSPICIOUS_IP_WINDOW = 3600;          // 1小时内
    const SQL_INJECTION_PATTERNS = [
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bOR\b.*=.*)/i',
        '/(\bAND\b.*=.*)/i',
        '/(\'|\")(\s*)(OR|AND)(\s*)(\'|\")/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bEXEC\b|\bEXECUTE\b)/i',
        '/(;|\||&).*(\bcat\b|\bls\b|\bwget\b)/i'
    ];

    private function __construct() {
        $this->db = Database::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 检测异常登录行为
     */
    public function detectAbnormalLogin($username, $ip, $success = false) {
        // 记录登录尝试
        $this->recordLoginAttempt($username, $ip, $success);

        if (!$success) {
            // 检查是否触发告警
            $this->checkFailedLoginThreshold($username, $ip);
            $this->checkSuspiciousIP($ip);
        }

        // 检测异常登录特征
        $this->detectAbnormalLoginPatterns($username, $ip);
    }

    /**
     * 记录登录尝试
     */
    private function recordLoginAttempt($username, $ip, $success) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $this->db->execute(
            "INSERT INTO login_attempts (username, ip_address, user_agent, success, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$username, $ip, substr($userAgent, 0, 255), $success ? 1 : 0]
        );
    }

    /**
     * 检查失败登录次数
     */
    private function checkFailedLoginThreshold($username, $ip) {
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM login_attempts
             WHERE username = ? AND success = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$username, self::FAILED_LOGIN_WINDOW]
        );

        if ($count['count'] >= self::FAILED_LOGIN_THRESHOLD) {
            $this->triggerAlert('BRUTE_FORCE_ATTEMPT', [
                'username' => $username,
                'ip' => $ip,
                'failed_count' => $count['count'],
                'time_window' => self::FAILED_LOGIN_WINDOW . '秒'
            ]);
        }
    }

    /**
     * 检查可疑IP
     */
    private function checkSuspiciousIP($ip) {
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM login_attempts
             WHERE ip_address = ? AND success = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$ip, self::SUSPICIOUS_IP_WINDOW]
        );

        if ($count['count'] >= self::SUSPICIOUS_IP_THRESHOLD) {
            // 封禁IP
            $this->blockIP($ip, 'BRUTE_FORCE', 3600); // 封禁1小时

            $this->triggerAlert('IP_BLOCKED', [
                'ip' => $ip,
                'failed_count' => $count['count'],
                'block_duration' => '1小时'
            ]);
        }
    }

    /**
     * 检测异常登录模式
     */
    private function detectAbnormalLoginPatterns($username, $ip) {
        // 1. 检测短时间内多地登录
        $recentIPs = $this->db->fetchAll(
            "SELECT DISTINCT ip_address FROM login_attempts
             WHERE username = ? AND success = 1
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             LIMIT 5",
            [$username]
        );

        if (count($recentIPs) >= 3) {
            $this->triggerAlert('MULTIPLE_LOCATION_LOGIN', [
                'username' => $username,
                'ip_count' => count($recentIPs),
                'ips' => array_column($recentIPs, 'ip_address')
            ]);
        }

        // 2. 检测异常时间登录（凌晨2-5点）
        $hour = (int)date('H');
        if ($hour >= 2 && $hour <= 5) {
            $this->triggerAlert('UNUSUAL_TIME_LOGIN', [
                'username' => $username,
                'ip' => $ip,
                'time' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * 检测SQL注入尝试
     */
    public function detectSQLInjection($input, $source = 'unknown') {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $this->detectSQLInjection($value, $source . '[' . $key . ']');
            }
            return;
        }

        if (!is_string($input)) {
            return;
        }

        foreach (self::SQL_INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $input)) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

                // 记录SQL注入尝试
                logSecurity('SQL_INJECTION_ATTEMPT', [
                    'source' => $source,
                    'pattern' => $pattern,
                    'input' => substr($input, 0, 200)
                ]);

                // 封禁IP
                $this->blockIP($ip, 'SQL_INJECTION', 86400); // 封禁24小时

                $this->triggerAlert('SQL_INJECTION_DETECTED', [
                    'ip' => $ip,
                    'source' => $source,
                    'input_preview' => substr($input, 0, 100)
                ]);

                break;
            }
        }
    }

    /**
     * 检测XSS尝试
     */
    public function detectXSS($input, $source = 'unknown') {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $this->detectXSS($value, $source . '[' . $key . ']');
            }
            return;
        }

        if (!is_string($input)) {
            return;
        }

        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

                logSecurity('XSS_ATTEMPT', [
                    'source' => $source,
                    'input' => substr($input, 0, 200)
                ]);

                $this->triggerAlert('XSS_DETECTED', [
                    'ip' => $ip,
                    'source' => $source,
                    'input_preview' => substr($input, 0, 100)
                ]);

                break;
            }
        }
    }

    /**
     * 封禁IP
     */
    private function blockIP($ip, $reason, $duration) {
        $expiresAt = date('Y-m-d H:i:s', time() + $duration);

        $this->db->execute(
            "INSERT INTO blocked_ips (ip_address, reason, expires_at, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
             reason = VALUES(reason),
             expires_at = VALUES(expires_at),
             updated_at = NOW()",
            [$ip, $reason, $expiresAt]
        );

        logSecurity('IP_BLOCKED', [
            'ip' => $ip,
            'reason' => $reason,
            'duration' => $duration . '秒',
            'expires_at' => $expiresAt
        ]);
    }

    /**
     * 检查IP是否被封禁
     */
    public function isIPBlocked($ip) {
        $result = $this->db->fetchOne(
            "SELECT id FROM blocked_ips
             WHERE ip_address = ?
             AND (expires_at IS NULL OR expires_at > NOW())",
            [$ip]
        );

        return !empty($result);
    }

    /**
     * 触发安全告警
     */
    private function triggerAlert($type, $details) {
        // 记录告警到数据库
        $this->db->execute(
            "INSERT INTO security_alerts (alert_type, details, ip_address, created_at)
             VALUES (?, ?, ?, NOW())",
            [$type, json_encode($details, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? 'unknown']
        );

        // 记录到安全日志
        logSecurity($type, $details);

        // 发送邮件告警（仅高危事件）
        $criticalEvents = ['SQL_INJECTION_DETECTED', 'IP_BLOCKED', 'BRUTE_FORCE_ATTEMPT'];
        if (in_array($type, $criticalEvents)) {
            $this->sendAlertEmail($type, $details);
        }
    }

    /**
     * 发送告警邮件
     */
    private function sendAlertEmail($type, $details) {
        // 获取管理员邮箱
        $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com';

        $subject = '【安全告警】' . $this->getAlertTitle($type);
        $body = $this->formatAlertEmail($type, $details);

        try {
            $emailSender = new EmailSender();
            $emailSender->send($adminEmail, $subject, $body);
        } catch (Exception $e) {
            logError('发送安全告警邮件失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取告警标题
     */
    private function getAlertTitle($type) {
        $titles = [
            'BRUTE_FORCE_ATTEMPT' => '检测到暴力破解尝试',
            'IP_BLOCKED' => 'IP地址已被封禁',
            'SQL_INJECTION_DETECTED' => '检测到SQL注入攻击',
            'XSS_DETECTED' => '检测到XSS攻击尝试',
            'MULTIPLE_LOCATION_LOGIN' => '检测到异地登录',
            'UNUSUAL_TIME_LOGIN' => '检测到异常时间登录'
        ];

        return $titles[$type] ?? '未知安全事件';
    }

    /**
     * 格式化告警邮件
     */
    private function formatAlertEmail($type, $details) {
        $title = $this->getAlertTitle($type);
        $time = date('Y-m-d H:i:s');

        $body = "<h2>安全告警通知</h2>";
        $body .= "<p><strong>告警类型：</strong>{$title}</p>";
        $body .= "<p><strong>发生时间：</strong>{$time}</p>";
        $body .= "<h3>详细信息：</h3>";
        $body .= "<ul>";

        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $body .= "<li><strong>{$key}：</strong>{$value}</li>";
        }

        $body .= "</ul>";
        $body .= "<p>请及时登录系统查看详情并采取必要措施。</p>";

        return $body;
    }

    /**
     * 清理过期的封禁IP
     */
    public function cleanExpiredBlocks() {
        $this->db->execute(
            "DELETE FROM blocked_ips WHERE expires_at IS NOT NULL AND expires_at < NOW()"
        );
    }

    /**
     * 清理旧的登录记录（保留30天）
     */
    public function cleanOldLoginAttempts() {
        $this->db->execute(
            "DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }
}
