<?php
/**
 * 管理员端 - 批量导入用户预览 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/import_users_validator.php';

requireRole('admin');
Security::requireCSRFToken();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => '请求方法不正确']);
    }

    if (!isset($_FILES['file'])) {
        jsonResponse(userImportFailureResponse('请上传文件', ['请上传文件']));
    }

    $db = Database::getInstance();
    $preview = userImportValidateUploadedFile($_FILES['file'], $db);

    if (!$preview['success']) {
        jsonResponse($preview);
    }

    jsonResponse([
        'success' => true,
        'message' => '预检通过',
        'accounts' => $preview['accounts'],
        'total_count' => $preview['total_count'],
        'error_count' => 0,
        'errors' => [],
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => '预检失败：服务器无法解析该文件',
        'accounts' => [],
        'total_count' => 0,
        'error_count' => 1,
        'errors' => ['文件解析失败：' . $e->getMessage()],
    ]);
}
