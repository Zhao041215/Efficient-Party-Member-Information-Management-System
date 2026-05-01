<?php
/**
 * 认证辅助函数
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function isHttpsRequest() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    private function setCookieValue($name, $value, $expires) {
        $options = [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Strict'
        ];

        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, (string)$value, $options);
            return;
        }

        setcookie($name, (string)$value, $expires, '/; samesite=Strict', '', $options['secure'], true);
    }

    private function clearRememberLogin($userId = null) {
        if ($userId) {
            $this->db->execute(
                "UPDATE users SET remember_token = NULL, token_expire = NULL WHERE id = ?",
                [$userId]
            );
        }

        $this->setCookieValue('remember_token', '', time() - 3600);
        $this->setCookieValue('user_id', '', time() - 3600);
    }

    private function clearSessionCookie() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $params = session_get_cookie_params();
        $options = [
            'expires' => time() - 3600,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $this->isHttpsRequest(),
            'httponly' => true,
            'samesite' => $params['samesite'] ?? 'Strict'
        ];

        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', $options);
            return;
        }

        setcookie(session_name(), '', $options['expires'], $options['path'], $options['domain'], $options['secure'], true);
    }

    /**
     * 用户登录
     */
    public function login($username, $password, $role, $remember = false) {
        $sql = "SELECT * FROM users WHERE username = ? AND role = ? AND is_active = 1";
        $user = $this->db->fetchOne($sql, [$username, $role]);

        if (!$user) {
            return ['success' => false, 'message' => '用户名或密码错误'];
        }

        // 学生账号检查是否已毕业
        if ($role === 'student' && $user['is_graduated'] == 1) {
            return ['success' => false, 'message' => '该账号已毕业，无法登录'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => '用户名或密码错误'];
        }

        // 设置session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['is_first_login'] = $user['is_first_login'];
        $_SESSION['force_change_password'] = $user['force_change_password'];
        $_SESSION['logged_in'] = true;
        $_SESSION['remember_login'] = (bool)$remember;

        // 记住登录
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expireAt = time() + REMEMBER_EXPIRE;
            $expire = date('Y-m-d H:i:s', $expireAt);
            
            $this->db->execute(
                "UPDATE users SET remember_token = ?, token_expire = ? WHERE id = ?",
                [$token, $expire, $user['id']]
            );

            $this->setCookieValue('remember_token', $token, $expireAt);
            $this->setCookieValue('user_id', $user['id'], $expireAt);
        } else {
            $this->clearRememberLogin($user['id']);
        }

        // 记录登录日志
        $this->logAction($user['id'], $user['username'], 'login', '用户登录成功');

        return ['success' => true, 'user' => $user];
    }

    /**
     * 检查记住登录
     */
    public function checkRememberToken() {
        if (isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
            $token = $_COOKIE['remember_token'];
            $userId = $_COOKIE['user_id'];

            $sql = "SELECT * FROM users WHERE id = ? AND remember_token = ? AND token_expire > NOW() AND is_active = 1";
            $user = $this->db->fetchOne($sql, [$userId, $token]);

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['is_first_login'] = $user['is_first_login'];
                $_SESSION['force_change_password'] = $user['force_change_password'];
                $_SESSION['logged_in'] = true;
                $_SESSION['remember_login'] = true;
                return true;
            }

            $this->clearRememberLogin();
        }
        return false;
    }

    /**
     * 检查是否登录
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * 获取当前用户
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'name' => $_SESSION['name'],
            'is_first_login' => $_SESSION['is_first_login']
        ];
    }

    /**
     * 检查用户角色
     */
    public function checkRole($allowedRoles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        if (is_string($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        // 系统管理员拥有所有权限
        if ($_SESSION['role'] === 'superadmin') {
            return true;
        }
        return in_array($_SESSION['role'], $allowedRoles);
    }

    /**
     * 退出登录
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logAction($_SESSION['user_id'], $_SESSION['username'], 'logout', '用户退出登录');
            
            // 清除记住登录
            $this->clearRememberLogin($_SESSION['user_id']);
        } else {
            $this->clearRememberLogin();
        }

        // 清除cookie
        // 销毁session
        session_unset();
        $this->clearSessionCookie();
        session_destroy();
    }

    /**
     * 修改密码
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        $sql = "SELECT password FROM users WHERE id = ?";
        $user = $this->db->fetchOne($sql, [$userId]);

        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }

        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => '原密码错误'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->execute(
            "UPDATE users SET password = ?, is_first_login = 0 WHERE id = ?",
            [$hashedPassword, $userId]
        );

        $_SESSION['is_first_login'] = 0;

        $this->logAction($userId, $_SESSION['username'], 'change_password', '修改密码成功');

        return ['success' => true, 'message' => '密码修改成功'];
    }

    /**
     * 强制修改密码(首次登录)
     */
    public function forceChangePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->execute(
            "UPDATE users SET password = ?, is_first_login = 0 WHERE id = ?",
            [$hashedPassword, $userId]
        );

        $_SESSION['is_first_login'] = 0;

        $this->logAction($userId, $_SESSION['username'], 'first_change_password', '首次登录修改密码');

        return ['success' => true, 'message' => '密码修改成功'];
    }

    /**
     * 重置密码(管理员)
     */
    public function resetPassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->execute(
            "UPDATE users SET password = ?, is_first_login = 1 WHERE id = ?",
            [$hashedPassword, $userId]
        );

        $this->logAction($_SESSION['user_id'], $_SESSION['username'], 'reset_password', "重置用户ID:{$userId}的密码");

        return ['success' => true, 'message' => '密码重置成功'];
    }

    /**
     * 记录操作日志
     */
    public function logAction($userId, $username, $action, $description = '', $details = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        writeOperationLog($userId, $username, $action, $description, $details, $ip);
    }

    /**
     * 验证密码强度
     */
    public function validatePassword($password) {
        if (strlen($password) < 6) {
            return ['valid' => false, 'message' => '密码长度至少6位'];
        }
        if (strlen($password) > 20) {
            return ['valid' => false, 'message' => '密码长度不能超过20位'];
        }
        return ['valid' => true];
    }
    
    /**
     * 检查是否为系统管理员
     */
    public function isSuperAdmin() {
        return $this->isLoggedIn() && $_SESSION['role'] === 'superadmin';
    }

    /**
     * 检查是否有管理员权限（包括系统管理员和普通管理员）
     */
    public function isAdmin() {
        return $this->isLoggedIn() && in_array($_SESSION['role'], ['admin', 'superadmin']);
    }

    /**
     * 检查用户是否可以操作目标用户
     * 系统管理员可以操作所有用户
     * 普通管理员只能操作学生
     */
    public function canManageUser($targetRole) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // 系统管理员可以管理所有用户
        if ($_SESSION['role'] === 'superadmin') {
            return true;
        }
        
        // 普通管理员只能管理学生
        if ($_SESSION['role'] === 'admin') {
            return $targetRole === 'student';
        }
        
        return false;
    }
}

/**
 * 辅助函数
 */

// 检查登录状态
function requireLogin() {
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        if (!$auth->checkRememberToken()) {
            header('Location: /index.php');
            exit;
        }
    }
    // 在这里检查强制修改密码，确保在任何输出之前
    checkForceChangePassword();
}

// 检查角色权限
function requireRole($roles) {
    $auth = new Auth();
    requireLogin();
    if (!$auth->checkRole($roles)) {
        header('Location: /index.php');
        exit;
    }
    
    // // 检查是否需要强制修改密码
    // checkForceChangePassword();
}

// 检查是否需要强制修改密码
function checkForceChangePassword() {
    // 如果当前就在修改密码页面，不需要再检查
    $currentScript = $_SERVER['PHP_SELF'];
    if (strpos($currentScript, 'change_password.php') !== false) {
        return;
    }
    
    // 如果是退出登录API，允许访问
    if (strpos($currentScript, 'logout.php') !== false) {
        return;
    }
    
    // 检查数据库中的强制修改密码标记
    if (isset($_SESSION['user_id'])) {
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT force_change_password FROM users WHERE id = ?", [$_SESSION['user_id']]);
        
        if ($user && $user['force_change_password'] == 1) {
            // 需要强制修改密码，重定向到修改密码页面
            $role = $_SESSION['role'] ?? 'student';
            // 系统管理员使用admin页面
            if ($role === 'superadmin') {
                $role = 'admin';
            }
            $changePasswordUrl = "/pages/{$role}/change_password.php";
            
            // 如果不在修改密码页面，则跳转
            if ($currentScript !== $changePasswordUrl) {
                header("Location: {$changePasswordUrl}");
                exit;
            }
        }
    }
}

// 获取当前用户
function getCurrentUser() {
    $auth = new Auth();
    return $auth->getCurrentUser();
}

// 检查是否登录（用于API）
function isLoggedIn() {
    $auth = new Auth();
    return $auth->isLoggedIn();
}

// 安全输出
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// JSON响应
function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 管理员敏感操作二次密码确认
function requireAdminPasswordConfirmation($data = null) {
    if ($data === null) {
        $data = function_exists('getPostData') ? getPostData() : $_POST;
    }

    $adminPassword = trim((string)($data['admin_password'] ?? ''));
    if ($adminPassword === '') {
        jsonResponse(['success' => false, 'message' => '请输入您的密码以确认本次操作']);
    }

    $db = Database::getInstance();
    $currentAdmin = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id'] ?? 0]);

    if (!$currentAdmin || !password_verify($adminPassword, $currentAdmin['password'])) {
        jsonResponse(['success' => false, 'message' => '密码错误，操作已取消']);
    }

    return $data;
}

// 获取客户端IP
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

// 检查是否为系统管理员
function isSuperAdmin() {
    $auth = new Auth();
    return $auth->isSuperAdmin();
}

// 检查是否有管理员权限
function isAdmin() {
    $auth = new Auth();
    return $auth->isAdmin();
}

// 检查是否可以管理目标用户
function canManageUser($targetRole) {
    $auth = new Auth();
    return $auth->canManageUser($targetRole);
}

// 获取角色中文名称
function getRoleName($role) {
    return ROLE_NAMES[$role] ?? '未知角色';
}
