<?php
/**
 * 退出登录API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

sendNoCacheHeaders();
header('Content-Type: application/json');

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

// CSRF验证
Security::requireCSRFToken();

$auth = new Auth();
$auth->logout();

echo json_encode(['success' => true, 'message' => '退出成功']);
exit;
