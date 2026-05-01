<?php
/**
 * 安全配置文件
 * 包含CSRF保护、请求频率限制、安全响应头等
 */

class Security {
    private static $instance = null;
    private $db;

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
     * 生成CSRF Token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * 验证CSRF Token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * 请求频率限制
     */
    public function rateLimiter($key, $maxAttempts = 5, $timeWindow = 300) {
        $cacheFile = __DIR__ . '/../cache/rate_limit_' . md5($key) . '.json';
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $data = [];
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            $data = json_decode($content, true) ?: [];
        }

        $now = time();

        // 清理过期记录
        $data = array_filter($data, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });

        // 检查是否超过限制
        if (count($data) >= $maxAttempts) {
            return false;
        }

        // 记录本次请求
        $data[] = $now;
        file_put_contents($cacheFile, json_encode($data));

        return true;
    }

    /**
     * 设置安全响应头（增强版）
     */
    public static function setSecurityHeaders() {
        // 防止点击劫持
        header('X-Frame-Options: SAMEORIGIN');

        // 防止MIME类型嗅探
        header('X-Content-Type-Options: nosniff');

        // XSS保护（虽然现代浏览器已弃用，但保留以兼容旧浏览器）
        header('X-XSS-Protection: 1; mode=block');

        // Referrer策略
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // 内容安全策略（CSP）- 优化版
        // 注意：生产环境应移除 'unsafe-inline' 和 'unsafe-eval'
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.staticfile.net https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.staticfile.net https://cdn.jsdelivr.net",
            "img-src 'self' data: https:",
            "font-src 'self' data: https://cdn.staticfile.net",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "upgrade-insecure-requests"
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));

        // Permissions-Policy（权限策略）
        $permissions = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()'
        ];
        header('Permissions-Policy: ' . implode(', ', $permissions));

        // Cross-Origin策略
        header('Cross-Origin-Embedder-Policy: require-corp');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');

        // HSTS（仅HTTPS环境）
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // 禁用浏览器功能
        header('X-Permitted-Cross-Domain-Policies: none');
        header('X-Download-Options: noopen');
    }

    /**
     * 验证密码强度
     */
    public static function validatePasswordStrength($password) {
        $length = strlen($password);
        $strength = 0;

        if ($length < 6) {
            return ['valid' => false, 'message' => '密码长度至少6位', 'strength' => 0];
        }

        if ($length >= 8) $strength++;
        if (preg_match('/\d/', $password)) $strength++;
        if (preg_match('/[a-z]/', $password)) $strength++;
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $strength++;

        return ['valid' => true, 'message' => '密码强度：' . ($strength < 2 ? '弱' : ($strength < 4 ? '中' : '强')), 'strength' => $strength];
    }

    /**
     * 清理输入（防止XSS）
     */
    public static function cleanInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'cleanInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 清除频率限制记录
     */
    public function clearRateLimit($key) {
        $cacheFile = __DIR__ . '/../cache/rate_limit_' . md5($key) . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * 验证CSRF Token（用于API）
     * 支持从POST数据或HTTP Header中获取Token
     */
    public static function validateCSRFTokenAPI() {
        $token = null;

        // 1. 尝试从POST数据获取
        if (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }

        // 2. 尝试从JSON body获取
        if (!$token) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (isset($data['csrf_token'])) {
                $token = $data['csrf_token'];
            }
        }

        // 3. 尝试从HTTP Header获取（支持多种格式）
        if (!$token) {
            // 标准格式
            if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
            }
            // 从所有headers中查找（某些服务器配置可能需要）
            elseif (function_exists('getallheaders')) {
                $headers = getallheaders();
                if (isset($headers['X-CSRF-Token'])) {
                    $token = $headers['X-CSRF-Token'];
                } elseif (isset($headers['X-Csrf-Token'])) {
                    $token = $headers['X-Csrf-Token'];
                } elseif (isset($headers['x-csrf-token'])) {
                    $token = $headers['x-csrf-token'];
                }
            }
        }

        if (!$token) {
            return false;
        }

        return self::validateCSRFToken($token);
    }

    /**
     * 要求CSRF验证（用于API）
     * 验证失败直接返回JSON错误并终止
     */
    public static function requireCSRFToken() {
        if (!self::validateCSRFTokenAPI()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'CSRF验证失败，请刷新页面后重试'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
