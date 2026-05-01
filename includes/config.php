<?php
/**
 * 生化学院党员信息管理系统 - 配置文件
 */

// 加载环境变量
function loadEnv($path) {
    if (!file_exists($path)) {
        die('.env文件不存在，请复制.env.example并配置');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // 跳过注释
        if (strpos(trim($line), '#') === 0) continue;

        // 解析 KEY=VALUE
        if (strpos($line, '=') === false) continue;

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // 设置环境变量
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../.env');

// 数据库配置
define('DB_HOST', $_ENV['DB_HOST'] ?? '');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// 系统配置
define('SITE_NAME', '生化学院党员信息管理系统');
define('SITE_URL', $_ENV['SITE_URL'] ?? '');
define('SESSION_EXPIRE', (int)($_ENV['SESSION_EXPIRE'] ?? 3600));
$rememberExpire = (int)($_ENV['REMEMBER_EXPIRE'] ?? 604800);
define('REMEMBER_EXPIRE', $rememberExpire > 0 ? $rememberExpire : 604800);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告（生产环境应设置为0）
error_reporting(0);
ini_set('display_errors', 0);

// Session安全配置
ini_set('session.cookie_lifetime', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', (string)max((int)ini_get('session.gc_maxlifetime'), REMEMBER_EXPIRE));

// 开启session
if (session_status() === PHP_SESSION_NONE) {
    session_start();

    // Session劫持防护
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    } elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_unset();
        session_destroy();
        session_start();
    }

    // Session固定攻击防护
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

// 角色常量定义
define('ROLE_STUDENT', 'student');
define('ROLE_TEACHER', 'teacher');
define('ROLE_ADMIN', 'admin');
define('ROLE_SUPERADMIN', 'superadmin');

// 角色权限映射
define('ROLE_NAMES', [
    'student' => '学生',
    'teacher' => '教师',
    'admin' => '管理员',
    'superadmin' => '系统管理员'
]);
