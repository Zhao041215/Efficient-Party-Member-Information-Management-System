<?php
/**
 * 管理员端 - 学籍变动 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/validator.php';

requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

Security::requireCSRFToken();

$data = requireAdminPasswordConfirmation();
$userId = Validator::cleanInt($data['user_id'] ?? 0, 1);
$action = Validator::cleanString($data['action'] ?? '', 20);

if (!$userId || !Validator::validateInArray($action, ['graduate', 'transfer'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ? AND role = 'student'", [$userId]);

if (!$user) {
    echo json_encode(['success' => false, 'message' => '学生不存在']);
    exit;
}

$studentInfo = $db->fetchOne("SELECT * FROM student_info WHERE user_id = ?", [$userId]);

try {
    $db->beginTransaction();

    if ($action === 'graduate') {
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

        $logDetails = [
            'username' => $user['username'],
            'name' => $user['name'],
            'student_no' => $studentInfo['student_no'] ?? $user['username'],
            'graduation_year' => $graduationYear,
            'old_status' => 'student',
            'new_status' => 'graduated',
            'archived_student_info' => (bool) $studentInfo,
            'deleted_pending_request_count' => $deletedPendingRequests
        ];
        logAdminSensitiveOperation('update', 'user', $userId, '学籍变动-设为毕业', $logDetails, $logDetails, 'admin_student_graduate', 1);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => '已设为毕业，学生信息已归档'
        ]);
        exit;
    }

    $newCollege = Validator::cleanString($data['college'] ?? '', 100);
    $newGrade = Validator::cleanString($data['grade'] ?? '', 20);
    $newClass = Validator::cleanString($data['class'] ?? '', 50);

    if (empty($newCollege) || empty($newGrade) || empty($newClass)) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => '请填写完整的转入信息']);
        exit;
    }

    if (!$studentInfo) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => '学生信息不存在']);
        exit;
    }

    $db->execute(
        "UPDATE student_info SET college = ?, grade = ?, class = ?, updated_at = NOW() WHERE user_id = ?",
        [$newCollege, $newGrade, $newClass, $userId]
    );

    $logDetails = [
        'username' => $user['username'],
        'name' => $user['name'],
        'student_no' => $studentInfo['student_no'],
        'changes' => [
            createLogFieldChange('college', getFieldLabel('college'), $studentInfo['college'], $newCollege),
            createLogFieldChange('grade', getFieldLabel('grade'), $studentInfo['grade'], $newGrade),
            createLogFieldChange('class', getFieldLabel('class'), $studentInfo['class'], $newClass)
        ]
    ];
    logAdminSensitiveOperation('update', 'user', $userId, '学籍变动-转专业/班级', $logDetails, $logDetails, 'admin_student_transfer', 1);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => '学籍信息已更新'
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
}
