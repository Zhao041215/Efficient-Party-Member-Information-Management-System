<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/validator.php';

header('Content-Type: application/json');

requireRole('student');
Security::requireCSRFToken();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$email = Validator::cleanString($input['email'] ?? '', 100);
$code = Validator::cleanString($input['code'] ?? '', 10);

if (empty($email) || empty($code)) {
    echo json_encode(['success' => false, 'message' => '邮箱和验证码不能为空']);
    exit;
}

// 验证验证码
$verification = $db->fetchOne("
    SELECT * FROM password_reset_codes 
    WHERE user_id = ? AND email = ? AND code = ? AND used = 0 AND expires_at > NOW()
    ORDER BY created_at DESC LIMIT 1
", [$userId, $email, $code]);

if (!$verification) {
    echo json_encode(['success' => false, 'message' => '验证码错误或已过期']);
    exit;
}

// 更新学生邮箱
$result = $db->execute("UPDATE student_info SET email = ? WHERE user_id = ?", [$email, $userId]);

if ($result) {
    // 标记验证码为已使用
    $db->execute("UPDATE password_reset_codes SET used = 1 WHERE id = ?", [$verification['id']]);

    // 记录操作日志
    logOperation('bind_email', 'student_info', $userId, '绑定邮箱：' . $email);

    echo json_encode(['success' => true, 'message' => '邮箱绑定成功']);
} else {
    echo json_encode(['success' => false, 'message' => '邮箱绑定失败']);
}