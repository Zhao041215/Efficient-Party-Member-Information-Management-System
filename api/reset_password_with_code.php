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
$token = trim($data['token'] ?? '');
$newPassword = trim($data['new_password'] ?? '');

// 验证token
if (empty($token) || !isset($_SESSION['reset_token']) || $token !== $_SESSION['reset_token']) {
    echo json_encode(['success' => false, 'message' => '无效的重置令牌'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查token是否过期(15分钟)
if (!isset($_SESSION['reset_token_time']) || time() - $_SESSION['reset_token_time'] > 900) {
    unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_code_id'], $_SESSION['reset_token_time']);
    echo json_encode(['success' => false, 'message' => '重置令牌已过期,请重新获取验证码'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证密码强度
if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => '密码长度至少6位'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 使用单例模式获取数据库连接
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // 开始事务
    $db->beginTransaction();

    // 更新密码
    $userId = $_SESSION['reset_user_id'];
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateSql = "UPDATE users SET password = ? WHERE id = ?";
    $updateResult = $db->execute($updateSql, [$hashedPassword, $userId]);

    if (!$updateResult) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => '密码重置失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 标记验证码为已使用
    $codeId = $_SESSION['reset_code_id'];
    $markUsedSql = "UPDATE password_reset_codes SET used = 1 WHERE id = ?";
    $db->execute($markUsedSql, [$codeId]);

    // 记录操作日志
    require_once '../includes/functions.php';
    logOperation('reset_password_with_code', 'user', $userId, '通过邮箱验证码重置密码');

    // 提交事务
    $db->commit();

    // 清除session
    unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_code_id'], $_SESSION['reset_token_time']);

    echo json_encode([
        'success' => true,
        'message' => '密码重置成功,请使用新密码登录'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $db->rollback();
    echo json_encode([
        'success' => false,
        'message' => '系统错误,请稍后重试'
    ], JSON_UNESCAPED_UNICODE);
}
?>