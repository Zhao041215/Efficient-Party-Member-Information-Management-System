<?php
/**
 * 管理员回复反馈API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/validator.php';

header('Content-Type: application/json; charset=utf-8');

requireRole(['admin', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不允许']);
}

Security::requireCSRFToken();

$data = requireAdminPasswordConfirmation(getPostData());

$feedbackId = intval($data['feedback_id'] ?? 0);
$reply = Validator::cleanString($data['reply'] ?? '', 5000);

if ($feedbackId <= 0) {
    jsonResponse(['success' => false, 'message' => '无效的反馈ID']);
}

if (empty($reply)) {
    jsonResponse(['success' => false, 'message' => '请输入回复内容']);
}

$db = Database::getInstance();
$feedback = $db->fetchOne("SELECT id, title, status FROM feedback WHERE id = ?", [$feedbackId]);

if (!$feedback) {
    jsonResponse(['success' => false, 'message' => '反馈不存在']);
}

try {
    // 更新回复内容和状态
    $sql = "UPDATE feedback SET admin_reply = ?, status = 'processing', updated_at = NOW() WHERE id = ?";
    $stmt = $db->getConnection()->prepare($sql);
    $result = $stmt->execute([$reply, $feedbackId]);
    
    if ($result) {
        $logDetails = [
            'title' => $feedback['title'],
            'old_status' => $feedback['status'],
            'new_status' => 'processing',
            'reply_summary' => summarizeLogText($reply)
        ];
        logAdminSensitiveOperation('feedback_reply', 'feedback', $feedbackId, '回复反馈：' . $feedback['title'], $logDetails, $logDetails, 'admin_feedback_reply', 1);

        jsonResponse([
            'success' => true,
            'message' => '回复成功'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => '回复失败']);
    }
} catch (Exception $e) {
    error_log("Feedback reply error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '系统错误']);
}
