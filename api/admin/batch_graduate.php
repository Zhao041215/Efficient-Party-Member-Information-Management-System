<?php
/**
 * 管理员端 - 批量设为毕业生 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

Security::requireCSRFToken();

$data = requireAdminPasswordConfirmation();
$userIds = $data['user_ids'] ?? [];

if (empty($userIds) || !is_array($userIds)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();
$successCount = 0;
$failCount = 0;
$errors = [];
$graduatedTargets = [];

foreach ($userIds as $userId) {
    $userId = (int) $userId;
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ? AND role = 'student'", [$userId]);

    if (!$user) {
        $failCount++;
        $errors[] = "ID {$userId}: 用户不存在或非学生";
        continue;
    }

    $studentInfo = $db->fetchOne("SELECT * FROM student_info WHERE user_id = ?", [$userId]);

    try {
        $db->beginTransaction();

        $graduationYear = date('Y');

        if ($studentInfo) {
            $db->execute(
                "INSERT INTO graduated_students (
                    student_no, name, gender, college, grade, class,
                    birth_date, ethnicity, id_card, address,
                    phone, email, political_status, age,
                    join_league_date, apply_party_date, activist_date,
                    probationary_date, full_member_date, graduation_year, graduated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $studentInfo['student_no'],
                    $studentInfo['name'],
                    $studentInfo['gender'],
                    $studentInfo['college'],
                    $studentInfo['grade'],
                    $studentInfo['class'],
                    $studentInfo['birth_date'],
                    $studentInfo['ethnicity'],
                    $studentInfo['id_card'],
                    $studentInfo['address'],
                    $studentInfo['phone'],
                    $studentInfo['email'],
                    $studentInfo['political_status'],
                    $studentInfo['age'],
                    $studentInfo['join_league_date'],
                    $studentInfo['apply_party_date'],
                    $studentInfo['activist_date'],
                    $studentInfo['probationary_date'],
                    $studentInfo['full_member_date'],
                    $graduationYear
                ]
            );

            $db->execute("DELETE FROM student_info WHERE user_id = ?", [$userId]);
        }

        $deletedPendingRequests = (int) $db->fetchOne("SELECT COUNT(*) AS count FROM info_change_requests WHERE user_id = ? AND status = 'pending'", [$userId])['count'];
        $db->execute("DELETE FROM info_change_requests WHERE user_id = ? AND status = 'pending'", [$userId]);
        $db->execute("UPDATE users SET is_graduated = 1, is_active = 0, updated_at = NOW() WHERE id = ?", [$userId]);

        $db->commit();

        $successCount++;
        $graduatedTargets[] = [
            'id' => $userId,
            'username' => $user['username'],
            'name' => $user['name'],
            'student_no' => $studentInfo['student_no'] ?? $user['username'],
            'graduation_year' => $graduationYear,
            'archived_student_info' => (bool) $studentInfo,
            'deleted_pending_request_count' => $deletedPendingRequests
        ];
    } catch (Exception $e) {
        $db->rollBack();
        $failCount++;
        $errors[] = "{$user['username']}: " . $e->getMessage();
    }
}

logAdminSensitiveOperation('update', 'user', null, '批量学籍变动-设为毕业', [
    'requested_count' => count($userIds),
    'success_count' => $successCount,
    'fail_count' => $failCount,
    'targets' => limitLogTargets($graduatedTargets),
    'errors' => array_slice($errors, 0, 10)
], [
    'targets' => $graduatedTargets,
    'errors' => $errors
], 'admin_batch_graduate', count($graduatedTargets) + count($errors));

$message = "成功设置 {$successCount} 名学生为毕业生";
if ($failCount > 0) {
    $message .= "，失败 {$failCount} 名";
    if (!empty($errors)) {
        $message .= "。错误详情：" . implode('; ', array_slice($errors, 0, 3));
        if (count($errors) > 3) {
            $message .= '...';
        }
    }
}

echo json_encode([
    'success' => $successCount > 0,
    'message' => $message,
    'data' => [
        'success_count' => $successCount,
        'fail_count' => $failCount,
        'errors' => $errors
    ]
]);
