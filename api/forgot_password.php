<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../includes/database.php';
    require_once '../includes/email.php';
    require_once '../includes/security.php';
    require_once '../includes/validator.php';

    Security::requireCSRFToken();

    // 防止暴力请求
    if (!isset($_SESSION['last_code_time'])) {
        $_SESSION['last_code_time'] = 0;
    }

    if (time() - $_SESSION['last_code_time'] < 60) {
        echo json_encode([
            'success' => false,
            'message' => '请求过于频繁,请稍后再试'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => '请求方法错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $username = Validator::cleanString($data['username'] ?? '', 50);
    $email = Validator::cleanString($data['email'] ?? '', 100);

    if (empty($username) || empty($email)) {
        echo json_encode(['success' => false, 'message' => '用户名和邮箱不能为空'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 验证邮箱格式
    if (!Validator::validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 使用单例模式获取数据库连接
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // 根据用户角色从不同表获取邮箱
    $sql = "
        SELECT u.id, u.username, u.role,
               CASE 
                   WHEN u.role = 'student' THEN si.email
                   ELSE u.email
               END as email
        FROM users u
        LEFT JOIN student_info si ON u.id = si.user_id AND u.role = 'student'
        WHERE u.username = ? AND u.is_active = 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户不存在或已被禁用'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 检查邮箱是否存在
    if (empty($user['email'])) {
        $role_text = $user['role'] === 'student' ? '学生' : 
                    ($user['role'] === 'teacher' ? '教师' : '管理员');
        echo json_encode(['success' => false, 'message' => "该{$role_text}账号未绑定邮箱,无法使用邮箱找回密码"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 检查邮箱是否匹配
    if ($user['email'] !== $email) {
        echo json_encode(['success' => false, 'message' => '邮箱地址不匹配'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 生成6位数字验证码
    $code = sprintf("%06d", mt_rand(0, 999999));
    $expires_at = date('Y-m-d H:i:s', time() + 600); // 10分钟有效期
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    // 删除该用户之前未使用的验证码
    $deleteSql = "DELETE FROM password_reset_codes WHERE user_id = ? AND used = 0";
    $db->execute($deleteSql, [$user['id']]);

    // 插入新验证码
    $insertSql = "INSERT INTO password_reset_codes (user_id, email, code, expires_at, ip_address) VALUES (?, ?, ?, ?, ?)";
    $insertResult = $db->execute($insertSql, [$user['id'], $user['email'], $code, $expires_at, $ip_address]);

    if (!$insertResult) {
        echo json_encode(['success' => false, 'message' => '验证码生成失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 发送邮件（使用新的安全功能：频率限制、日志记录、重试机制）
    $emailSender = new EmailSender();
    $sendResult = $emailSender->sendVerificationCode($user['email'], $code, $user['username'], $user['id']);

    if ($sendResult['success']) {
        $_SESSION['last_code_time'] = time();

        // 记录操作日志
        require_once '../includes/functions.php';
        logOperation('forgot_password', 'user', $user['id'], '请求找回密码，发送验证码到：' . $user['email']);

        echo json_encode([
            'success' => true,
            'message' => '验证码已发送到您的邮箱,请查收'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $sendResult['message'] ?? '邮件发送失败,请稍后重试'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '系统错误，请稍后重试'
    ], JSON_UNESCAPED_UNICODE);
}
?>