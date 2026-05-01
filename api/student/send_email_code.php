<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/validator.php';

header('Content-Type: application/json');

requireRole('student');
Security::requireCSRFToken();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$email = Validator::cleanString($input['email'] ?? '', 100);

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => '邮箱不能为空']);
    exit;
}

if (!Validator::validateEmail($email)) {
    echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
    exit;
}

// 检查邮箱是否已被其他用户使用
$existingUser = $db->fetchOne("SELECT user_id FROM student_info WHERE email = ? AND user_id != ?", [$email, $userId]);
if ($existingUser) {
    echo json_encode(['success' => false, 'message' => '该邮箱已被其他用户使用']);
    exit;
}

// 频率限制已在 EmailSender 类中统一处理，此处移除重复检查

// 生成6位验证码
$code = sprintf('%06d', mt_rand(0, 999999));
$expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

// 先删除该用户该邮箱的所有旧验证码
$db->execute("DELETE FROM password_reset_codes WHERE user_id = ? AND email = ?", [$userId, $email]);

// 插入新的验证码
$insertResult = $db->execute("
    INSERT INTO password_reset_codes (user_id, email, code, expires_at, used, ip_address) 
    VALUES (?, ?, ?, ?, 0, ?)
", [$userId, $email, $code, $expiresAt, $ipAddress]);

if (!$insertResult) {
    echo json_encode(['success' => false, 'message' => '系统错误，请稍后重试']);
    exit;
}

// 发送邮件（使用新的安全功能：频率限制、日志记录、重试机制）
$mailer = new EmailSender();

try {
    $sendResult = $mailer->sendBindingCode($email, $code, $userId);

    if ($sendResult['success']) {
        // 记录操作日志
        logOperation('send_email_code', 'email', $userId, '发送邮箱验证码：' . $email);

        echo json_encode([
            'success' => true,
            'message' => '验证码已发送，请检查邮箱（包括垃圾箱）'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $sendResult['message'] ?? '验证码发送失败，请稍后重试'
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '邮件发送失败，请稍后重试']);
}