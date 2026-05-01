<?php
/**
 * 提交反馈API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/validator.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不允许']);
}

// CSRF验证 - 对于multipart/form-data，CSRF token在$_POST中
if (isset($_FILES['screenshot']) && !empty($_FILES['screenshot']['name'])) {
    // 文件上传时，使用$_POST
    Security::requireCSRFToken($_POST);
    $data = $_POST;
} else {
    // 普通提交
    Security::requireCSRFToken();
    $data = getPostData();
}

// 清理和验证输入数据
$type = Validator::cleanString($data['type'] ?? '', 20);
$title = Validator::cleanString($data['title'] ?? '', 200);
$content = Validator::cleanString($data['content'] ?? '', 5000);
$contact = Validator::cleanString($data['contact'] ?? '', 100);

// Bug相关字段（可选）
$device = Validator::cleanString($data['device'] ?? '', 50);
$deviceModel = Validator::cleanString($data['device_model'] ?? '', 100);
$bugTime = !empty($data['bug_time']) ? $data['bug_time'] : null;

// 验证必填字段
if (empty($type) || !in_array($type, ['bug', 'suggestion'])) {
    jsonResponse(['success' => false, 'message' => '请选择反馈类型']);
}

if (empty($title)) {
    jsonResponse(['success' => false, 'message' => '请输入标题']);
}

if (empty($content)) {
    jsonResponse(['success' => false, 'message' => '请输入详细描述']);
}

// 处理截图上传
$screenshotPath = null;
if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['screenshot'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // 获取文件扩展名
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // 验证文件扩展名
    if (!in_array($extension, $allowedExtensions)) {
        jsonResponse(['success' => false, 'message' => '只支持 JPG、PNG、GIF 格式的图片']);
    }
    
    // 验证MIME类型（更宽松的检查）
    if (!in_array($file['type'], $allowedTypes) && !empty($file['type'])) {
        error_log("Unsupported MIME type: " . $file['type']);
        // 不阻止上传，只记录日志
    }
    
    // 验证文件大小
    if ($file['size'] > $maxSize) {
        jsonResponse(['success' => false, 'message' => '图片大小不能超过5MB']);
    }
    
    // 创建上传目录
    $uploadDir = __DIR__ . '/../../uploads/feedback/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 生成唯一文件名
    $filename = 'feedback_' . date('YmdHis') . '_' . uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $screenshotPath = 'uploads/feedback/' . $filename;
    } else {
        error_log("File upload failed. Temp file: " . $file['tmp_name'] . ", Target: " . $uploadPath);
        jsonResponse(['success' => false, 'message' => '文件上传失败，请检查服务器权限']);
    }
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

try {
    // 插入反馈记录
    $sql = "INSERT INTO feedback (user_id, type, title, content, contact, device, device_model, bug_time, screenshot, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $db->getConnection()->prepare($sql);
    $result = $stmt->execute([
        $userId, 
        $type, 
        $title, 
        $content, 
        $contact, 
        $device, 
        $deviceModel, 
        $bugTime, 
        $screenshotPath
    ]);
    
    if ($result) {
        $feedbackId = (int) $db->lastInsertId();
        logOperation('create', 'feedback', $feedbackId, '提交反馈：' . $title, [
            'feedback_type' => $type,
            'title' => $title,
            'has_contact' => $contact !== '',
            'has_screenshot' => $screenshotPath !== null
        ]);

        jsonResponse([
            'success' => true,
            'message' => '提交成功！感谢您的反馈'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => '提交失败，请稍后重试']);
    }
} catch (Exception $e) {
    error_log("Feedback submission error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '系统错误，请稍后重试']);
}
