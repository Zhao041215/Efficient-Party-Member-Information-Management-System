<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/database.php';
require_once '../includes/security.php';

Security::requireCSRFToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法错误'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$code = trim($data['code'] ?? '');

if (empty($username) || empty($code)) {
    echo json_encode(['success' => false, 'message' => '用户名和验证码不能为空'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 使用单例模式获取数据库连接
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // 验证验证码
    $sql = "
        SELECT prc.id, prc.user_id, prc.code, prc.expires_at 
        FROM password_reset_codes prc 
        JOIN users u ON prc.user_id = u.id 
        WHERE u.username = ? AND prc.code = ? AND prc.used = 0 AND u.is_active = 1
        ORDER BY prc.created_at DESC 
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $code]);
    $resetCode = $stmt->fetch();

    if (!$resetCode) {
        echo json_encode(['success' => false, 'message' => '验证码错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 检查是否过期
    if (strtotime($resetCode['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => '验证码已过期'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 验证成功,生成临时token
    $token = bin2hex(random_bytes(32));
    $_SESSION['reset_token'] = $token;
    $_SESSION['reset_user_id'] = $resetCode['user_id'];
    $_SESSION['reset_code_id'] = $resetCode['id'];
    $_SESSION['reset_token_time'] = time();

    echo json_encode([
        'success' => true,
        'message' => '验证成功',
        'token' => $token
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '系统错误,请稍后重试'
    ], JSON_UNESCAPED_UNICODE);
}
?>