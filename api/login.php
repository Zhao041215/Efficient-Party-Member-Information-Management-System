<?php
/**
 * 登录API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/security_monitor.php';

// 设置安全响应头
Security::setSecurityHeaders();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不允许']);
}

$data = getPostData();

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'student';
$remember = (bool)($data['remember'] ?? false);

// 验证参数
if (empty($username) || empty($password)) {
    jsonResponse(['success' => false, 'message' => '请输入用户名和密码']);
}

// 验证角色
if (!in_array($role, ['student', 'teacher', 'admin', 'superadmin'])) {
    jsonResponse(['success' => false, 'message' => '无效的角色']);
}

// 获取IP地址
$ip = getClientIP();

// 安全监控：检查IP是否被封禁
$monitor = SecurityMonitor::getInstance();
if ($monitor->isIPBlocked($ip)) {
    logSecurity('BLOCKED_IP_LOGIN_ATTEMPT', ['username' => $username, 'ip' => $ip]);
    jsonResponse(['success' => false, 'message' => '您的IP已被封禁，请联系管理员']);
}

// 安全监控：检测SQL注入尝试
$monitor->detectSQLInjection(['username' => $username, 'password' => $password], 'login');

// 频率限制检查
$security = Security::getInstance();
$rateLimitKey = 'login_' . $ip . '_' . $username;

if (!$security->rateLimiter($rateLimitKey, 5, 300)) {
    jsonResponse(['success' => false, 'message' => '登录尝试过于频繁，请5分钟后再试']);
}

// 执行登录
$auth = new Auth();
$result = $auth->login($username, $password, $role, $remember);

if ($result['success']) {
    // 登录成功
    // 安全监控：记录成功的登录
    $monitor->detectAbnormalLogin($username, $ip, true);

    // 清除频率限制
    $security->clearRateLimit($rateLimitKey);

    $user = $result['user'];
    
    // 检查是否需要强制修改密码
    if ($user['force_change_password']) {
        jsonResponse([
            'success' => true,
            'message' => '登录成功',
            'force_change_password' => true,
            'redirect' => "/pages/{$role}/change_password.php"
        ]);
    }
    
    // 确定跳转地址
    if ($role === 'student') {
        $db = Database::getInstance();
        $studentInfo = $db->fetchOne(
            "SELECT info_completed FROM student_info WHERE user_id = ?",
            [$user['id']]
        );
        
        if (!$studentInfo || !$studentInfo['info_completed']) {
            // 学生未填写信息,跳转到填写页面
            $redirect = "/pages/student/fill_info.php";
        } else {
            // 已填写信息,跳转到首页
            $redirect = "/pages/student/index.php";
        }
    } elseif ($role === 'superadmin') {
        // 系统管理员使用admin页面
        $redirect = "/pages/admin/index.php";
    } else {
        $redirect = "/pages/{$role}/index.php";
    }
    
    jsonResponse([
        'success' => true,
        'message' => '登录成功',
        'redirect' => $redirect,
        'is_first_login' => (bool)$user['is_first_login']
    ]);
} else {
    // 登录失败
    // 安全监控：记录失败的登录尝试
    $monitor->detectAbnormalLogin($username, $ip, false);

    jsonResponse([
        'success' => false,
        'message' => $result['message']
    ]);
}
