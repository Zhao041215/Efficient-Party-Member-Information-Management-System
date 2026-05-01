<?php
/**
 * 学生首次完善信息 API
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
$data['ethnicity'] = Validator::cleanString($data['ethnicity'] ?? '', 50);
$data['id_card'] = Validator::cleanString($data['id_card'] ?? '', 18);
$data['address'] = Validator::cleanString($data['address'] ?? '', 200);
$data['phone'] = Validator::cleanString($data['phone'] ?? '', 11);
$data['email'] = Validator::cleanString($data['email'] ?? '', 100);
$data['political_status'] = Validator::cleanString($data['political_status'] ?? '', 50);
$data['graduation_year'] = Validator::cleanString($data['graduation_year'] ?? '', 10);
$data['join_league_date'] = Validator::cleanString($data['join_league_date'] ?? '', 10);
$data['apply_party_date'] = Validator::cleanString($data['apply_party_date'] ?? '', 10);
$data['activist_date'] = Validator::cleanString($data['activist_date'] ?? '', 10);

$db = Database::getInstance();
$userId = (int) $_SESSION['user_id'];

$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) {
    jsonResponse(['success' => false, 'message' => '用户不存在']);
}

$existingInfo = $db->fetchOne("SELECT * FROM student_info WHERE user_id = ?", [$userId]);
$requiredFields = ['ethnicity', 'id_card', 'address', 'phone', 'email', 'political_status', 'graduation_year', 'join_league_date', 'apply_party_date', 'activist_date'];

foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        jsonResponse(['success' => false, 'message' => getFieldLabel($field) . '不能为空']);
    }
}

if (!validateIdCard($data['id_card'])) {
    jsonResponse(['success' => false, 'message' => '身份证号格式不正确']);
}

if (!validatePhone($data['phone'])) {
    jsonResponse(['success' => false, 'message' => '手机号格式不正确']);
}

if (!validateEmail($data['email'])) {
    jsonResponse(['success' => false, 'message' => '邮箱格式不正确']);
}

$politicalStatuses = getSystemOptions('political_status');
$ethnicities = getSystemOptions('ethnicity');

if (!in_array($data['political_status'], $politicalStatuses, true)) {
    jsonResponse(['success' => false, 'message' => '无效的政治面貌选项']);
}

if (!in_array($data['ethnicity'], $ethnicities, true)) {
    jsonResponse(['success' => false, 'message' => '无效的民族选项']);
}

$age = calculateAgeFromIdCard($data['id_card']);
$birthDate = getBirthDateFromIdCard($data['id_card']);
$logChanges = [];

foreach ($requiredFields as $field) {
    $oldValue = $existingInfo[$field] ?? null;
    $newValue = $data[$field];
    if ($oldValue !== $newValue) {
        $logChanges[] = createLogFieldChange($field, getFieldLabel($field), $oldValue, $newValue);
    }
}

foreach (['age' => $age, 'birth_date' => $birthDate] as $field => $newValue) {
    $oldValue = $existingInfo[$field] ?? null;
    if ($oldValue !== $newValue) {
        $logChanges[] = createLogFieldChange($field, getFieldLabel($field), $oldValue, $newValue);
    }
}

try {
    $db->beginTransaction();

    if ($existingInfo) {
        $db->execute(
            "UPDATE student_info SET
                ethnicity = ?, id_card = ?, address = ?, phone = ?, email = ?,
                political_status = ?, graduation_year = ?, join_league_date = ?,
                apply_party_date = ?, activist_date = ?, age = ?, birth_date = ?,
                info_completed = 1, updated_at = NOW()
             WHERE user_id = ?",
            [
                $data['ethnicity'],
                $data['id_card'],
                $data['address'],
                $data['phone'],
                $data['email'],
                $data['political_status'],
                $data['graduation_year'],
                $data['join_league_date'],
                $data['apply_party_date'],
                $data['activist_date'],
                $age,
                $birthDate,
                $userId
            ]
        );
    } else {
        $db->execute(
            "INSERT INTO student_info (
                user_id, student_no, name, gender, ethnicity,
                id_card, address, phone, email, political_status, graduation_year,
                join_league_date, apply_party_date, activist_date, age, birth_date, info_completed
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $userId,
                $user['username'],
                $user['name'],
                null,
                $data['ethnicity'],
                $data['id_card'],
                $data['address'],
                $data['phone'],
                $data['email'],
                $data['political_status'],
                $data['graduation_year'],
                $data['join_league_date'],
                $data['apply_party_date'],
                $data['activist_date'],
                $age,
                $birthDate
            ]
        );
    }

    logOperation('fill_info', 'student_info', $userId, '学生完善个人信息', [
        'student_no' => $user['username'],
        'profile_initialized' => !$existingInfo,
        'changes' => $logChanges
    ]);

    $db->commit();

    jsonResponse(['success' => true, 'message' => '信息提交成功']);
} catch (Exception $e) {
    $db->rollBack();
    logError('学生信息填写失败', ['error' => $e->getMessage(), 'user_id' => $userId]);
    jsonResponse(['success' => false, 'message' => '系统错误，请稍后重试']);
}
