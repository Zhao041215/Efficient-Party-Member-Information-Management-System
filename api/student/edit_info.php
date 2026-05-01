<?php
/**
 * 学生修改信息 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/validator.php';

header('Content-Type: application/json; charset=utf-8');

requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不允许']);
}

Security::requireCSRFToken();

$data = getPostData();

foreach ($data as $key => $value) {
    if (!is_string($value)) {
        continue;
    }

    if ($key === 'address') {
        $data[$key] = Validator::cleanString($value, 200);
    } elseif (in_array($key, ['ethnicity', 'political_status'], true)) {
        $data[$key] = Validator::cleanString($value, 50);
    } elseif ($key === 'id_card') {
        $data[$key] = Validator::cleanString($value, 18);
    } elseif ($key === 'phone') {
        $data[$key] = Validator::cleanString($value, 11);
    } elseif ($key === 'email') {
        $data[$key] = Validator::cleanString($value, 100);
    } elseif ($key === 'email_code') {
        $data[$key] = Validator::cleanString($value, 10);
    } else {
        $data[$key] = Validator::cleanString($value, 100);
    }
}

$db = Database::getInstance();
$userId = (int) $_SESSION['user_id'];
$studentInfo = $db->fetchOne("SELECT * FROM student_info WHERE user_id = ?", [$userId]);

if (!$studentInfo) {
    jsonResponse(['success' => false, 'message' => '学生信息不存在']);
}

$pendingCount = $db->fetchOne(
    "SELECT COUNT(*) AS cnt FROM info_change_requests WHERE user_id = ? AND status = 'pending'",
    [$userId]
);

if ($pendingCount && (int) $pendingCount['cnt'] > 0) {
    jsonResponse([
        'success' => false,
        'message' => '当前有未审核的信息，请先处理',
        'redirect' => '/pages/student/pending_list.php'
    ]);
}

$allowedFields = [
    'ethnicity', 'id_card', 'address', 'phone', 'email', 'political_status',
    'graduation_year', 'join_league_date', 'apply_party_date', 'activist_date',
    'probationary_date', 'full_member_date'
];

$changes = [];

foreach ($allowedFields as $field) {
    if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === $studentInfo[$field]) {
        continue;
    }

    if ($field === 'id_card' && !validateIdCard($data[$field])) {
        jsonResponse(['success' => false, 'message' => '身份证号格式不正确']);
    }

    if ($field === 'phone' && !validatePhone($data[$field])) {
        jsonResponse(['success' => false, 'message' => '手机号格式不正确']);
    }

    if ($field === 'email' && !validateEmail($data[$field])) {
        jsonResponse(['success' => false, 'message' => '邮箱格式不正确']);
    }

    $changes[] = [
        'field_name' => $field,
        'field_label' => getFieldLabel($field),
        'old_value' => $studentInfo[$field],
        'new_value' => $data[$field]
    ];
}

if (isset($data['email']) && $data['email'] !== '' && $data['email'] !== $studentInfo['email']) {
    if (empty($data['email_code'])) {
        jsonResponse(['success' => false, 'message' => '请输入邮箱验证码']);
    }

    $verification = $db->fetchOne(
        "SELECT * FROM password_reset_codes
         WHERE user_id = ? AND email = ? AND code = ? AND used = 0 AND expires_at > NOW()
         ORDER BY created_at DESC LIMIT 1",
        [$userId, $data['email'], $data['email_code']]
    );

    if (!$verification) {
        jsonResponse(['success' => false, 'message' => '验证码错误或已过期']);
    }

    $db->execute("UPDATE password_reset_codes SET used = 1 WHERE id = ?", [$verification['id']]);

    $emailUpdated = $db->execute("UPDATE student_info SET email = ? WHERE user_id = ?", [$data['email'], $userId]);
    if (!$emailUpdated) {
        jsonResponse(['success' => false, 'message' => '邮箱更新失败']);
    }

    logOperation('update_email', 'student_info', $userId, '学生直接修改邮箱', [
        'student_no' => $studentInfo['student_no'],
        'changes' => [
            createLogFieldChange('email', getFieldLabel('email'), $studentInfo['email'], $data['email'])
        ]
    ]);

    $emailAddress = $data['email'];
    $changes = array_values(array_filter($changes, function ($change) {
        return $change['field_name'] !== 'email';
    }));

    if (empty($changes)) {
        jsonResponse(['success' => true, 'message' => '邮箱修改成功']);
    }
}

if (empty($changes)) {
    jsonResponse(['success' => false, 'message' => '没有检测到任何修改']);
}

try {
    $db->beginTransaction();
    $batchId = generateBatchId();

    foreach ($changes as $change) {
        $field = $change['field_name'];

        if ($field === 'ethnicity') {
            $options = getSystemOptions('ethnicity');
            if (!in_array($change['new_value'], $options, true)) {
                $db->rollBack();
                jsonResponse(['success' => false, 'message' => '无效的民族选项']);
            }
        }

        if ($field === 'political_status') {
            $options = getSystemOptions('political_status');
            if (!in_array($change['new_value'], $options, true)) {
                $db->rollBack();
                jsonResponse(['success' => false, 'message' => '无效的政治面貌选项']);
            }
        }

        $db->execute(
            "INSERT INTO info_change_requests
                (user_id, student_no, field_name, field_label, old_value, new_value, batch_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $studentInfo['student_no'],
                $change['field_name'],
                $change['field_label'],
                $change['old_value'],
                $change['new_value'],
                $batchId
            ]
        );
    }

    logOperation('submit_change_request', 'change_request', $userId, '提交信息修改申请', [
        'student_no' => $studentInfo['student_no'],
        'batch_id' => $batchId,
        'change_count' => count($changes),
        'changes' => array_map(function ($change) {
            return createLogFieldChange(
                $change['field_name'],
                $change['field_label'],
                $change['old_value'],
                $change['new_value']
            );
        }, $changes)
    ]);

    $db->commit();

    $message = isset($emailAddress)
        ? '邮箱已立即生效，其他信息已提交审核，等待管理员审核！'
        : '已提交审核，等待管理员审核！';

    jsonResponse(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    $db->rollBack();
    logError('提交信息修改申请失败', ['error' => $e->getMessage(), 'user_id' => $userId]);
    jsonResponse(['success' => false, 'message' => '系统错误，请稍后重试']);
}
