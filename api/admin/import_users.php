<?php
/**
 * 管理员端 - 批量导入用户API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/import_users_validator.php';

requireRole('admin');
Security::requireCSRFToken();

header('Content-Type: application/json; charset=utf-8');

function importUsersJson($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        importUsersJson(['success' => false, 'message' => '请求方法不正确']);
    }

    if (!isset($_FILES['file'])) {
        importUsersJson(userImportFailureResponse('请上传文件', ['请上传文件']));
    }

    $db = Database::getInstance();
    $preview = userImportValidateUploadedFile($_FILES['file'], $db);
    if (!$preview['success']) {
        importUsersJson($preview);
    }

    requireAdminPasswordConfirmation(getPostData());

    $uploadKey = 'upload_users_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '_' . ($_SESSION['user_id'] ?? 0);
    $security = Security::getInstance();
    if (!$security->rateLimiter($uploadKey, 5, 300)) {
        importUsersJson(['success' => false, 'message' => '上传过于频繁，请5分钟后再试']);
    }

    $successCount = 0;
    $importDetails = [];
    $roleCounts = [];
    $transactionStarted = false;

    try {
        $db->beginTransaction();
        $transactionStarted = true;

        foreach ($preview['rows'] as $importRow) {
            $data = $importRow['data'];
            $role = $importRow['role'];
            $defaultPassword = password_hash($data['username'], PASSWORD_DEFAULT);

            $db->execute(
                "INSERT INTO users (username, password, name, role, is_active, is_first_login, created_at)
                 VALUES (?, ?, ?, ?, 1, 1, NOW())",
                [$data['username'], $defaultPassword, $data['name'], $role]
            );

            $userId = $db->lastInsertId();

            if ($role === 'student') {
                $db->execute(
                    "INSERT INTO student_info (user_id, student_no, name, gender, college, grade, class, info_completed, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())",
                    [
                        $userId,
                        $data['username'],
                        $data['name'],
                        $data['gender'],
                        $data['college'],
                        $data['grade'],
                        $data['class'],
                    ]
                );
            }

            $successCount++;
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
            $importDetails[] = [
                'row_number' => $importRow['row_number'] ?? null,
                'result' => 'success',
                'operation' => 'create_user',
                'user_id' => (int) $userId,
                'username' => $data['username'],
                'name' => $data['name'],
                'role' => $role,
                'student_profile_created' => $role === 'student',
                'force_change_password' => true,
                'reset_to_username' => true
            ];
        }

        logAdminSensitiveOperation('import', 'user', null, "批量导入用户: 成功{$successCount}条, 失败0条", [
            'success_count' => $successCount,
            'fail_count' => 0,
            'role_counts' => $roleCounts,
            'targets' => limitLogTargets($importDetails)
        ], $importDetails, 'admin_import_users_rows', count($importDetails));

        $db->commit();
        $transactionStarted = false;

        importUsersJson([
            'success' => true,
            'message' => "导入完成: 成功 {$successCount} 条",
            'successCount' => $successCount,
            'errorCount' => 0,
            'errors' => [],
        ]);
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $db->rollback();
        }

        importUsersJson([
            'success' => false,
            'message' => '导入失败：服务器写入数据时发生错误，未导入任何账户',
            'successCount' => 0,
            'errorCount' => 1,
            'errors' => ['导入失败：' . $e->getMessage()],
        ]);
    }
} catch (Throwable $e) {
    importUsersJson([
        'success' => false,
        'message' => '导入失败：服务器无法解析该文件',
        'successCount' => 0,
        'errorCount' => 1,
        'errors' => ['文件解析失败：' . $e->getMessage()],
    ]);
}
