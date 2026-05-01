<?php
/**
 * 管理员端 - 批量导入学生信息API
 * 用于导入已存在学生账户的详细信息
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
requireRole('superadmin');
Security::requireCSRFToken();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

requireAdminPasswordConfirmation(getPostData());

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '请上传文件']);
    exit;
}

$file = $_FILES['file'];

// 1. 文件大小限制（5MB）
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => '文件大小不能超过5MB']);
    exit;
}

// 2. 文件扩展名验证
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    echo json_encode(['success' => false, 'message' => '请上传CSV格式文件']);
    exit;
}

// 3. MIME类型验证
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        echo json_encode(['success' => false, 'message' => '文件类型不正确，只允许CSV文件']);
        exit;
    }
}

// 4. 上传频率限制（5分钟内最多5次）
$uploadKey = 'upload_student_info_' . $_SERVER['REMOTE_ADDR'] . '_' . $_SESSION['user_id'];
if (!Security::rateLimiter($uploadKey, 5, 300)) {
    echo json_encode(['success' => false, 'message' => '上传过于频繁，请5分钟后再试']);
    exit;
}

// 处理文件编码
$content = file_get_contents($file['tmp_name']);

// 移除各种BOM
$boms = [
    "\xEF\xBB\xBF",     // UTF-8
    "\xFF\xFE",         // UTF-16 LE
    "\xFE\xFF",         // UTF-16 BE
];
foreach ($boms as $bom) {
    if (strpos($content, $bom) === 0) {
        $content = substr($content, strlen($bom));
        break;
    }
}

// 尝试转换编码为UTF-8
if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
    $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'GB18030', 'BIG5'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
}

// 5. 验证CSV行数（最多1000行）
$lineCount = substr_count($content, "\n");
if ($lineCount > 1001) { // 表头+1000行数据
    echo json_encode(['success' => false, 'message' => '单次最多导入1000条记录']);
    exit;
}

// 将内容写入临时文件（使用更安全的文件名）
$tempFile = sys_get_temp_dir() . '/csv_' . uniqid() . '_' . bin2hex(random_bytes(8)) . '.tmp';
$written = file_put_contents($tempFile, $content);
if ($written === false) {
    echo json_encode(['success' => false, 'message' => '临时文件写入失败，请检查服务器临时目录权限']);
    exit;
}

$handle = fopen($tempFile, 'r');
if (!$handle) {
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    echo json_encode(['success' => false, 'message' => '文件读取失败']);
    exit;
}

// 读取表头
$header = fgetcsv($handle);
if (!$header) {
    fclose($handle);
    unlink($tempFile);
    echo json_encode(['success' => false, 'message' => '文件格式错误']);
    exit;
}

// 标准化表头
$header = array_map(function($col) {
    $col = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $col);
    $col = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $col);
    return trim($col);
}, $header);

$headerMap = [
    '学号' => 'student_no',
    '姓名' => 'name',
    '性别' => 'gender',
    '学院' => 'college',
    '年级' => 'grade',
    '班级' => 'class',
    '民族' => 'ethnicity',
    '身份证号' => 'id_card',
    '出生日期' => 'birth_date',
    '联系方式' => 'phone',
    '邮箱' => 'email',
    '家庭住址' => 'address',
    '政治面貌' => 'political_status',
    '毕业时间' => 'graduation_year',
    '入团时间' => 'join_league_date',
    '递交入党申请书时间' => 'apply_party_date',
    '确定积极分子时间' => 'activist_date',
    '确定预备党员时间' => 'probationary_date',
    '转正时间' => 'full_member_date',
    // 英文别名
    'student_no' => 'student_no',
    'name' => 'name',
    'gender' => 'gender',
    'college' => 'college',
    'grade' => 'grade',
    'class' => 'class',
    'ethnicity' => 'ethnicity',
    'id_card' => 'id_card',
    'birth_date' => 'birth_date',
    'phone' => 'phone',
    'email' => 'email',
    'address' => 'address',
    'political_status' => 'political_status',
    'graduation_year' => 'graduation_year',
    'join_league_date' => 'join_league_date',
    'apply_party_date' => 'apply_party_date',
    'activist_date' => 'activist_date',
    'probationary_date' => 'probationary_date',
    'full_member_date' => 'full_member_date'
];

$columns = [];
foreach ($header as $col) {
    $columns[] = $headerMap[$col] ?? $col;
}

// 检查必要列
if (!in_array('student_no', $columns)) {
    fclose($handle);
    unlink($tempFile);
    echo json_encode(['success' => false, 'message' => '缺少必要列: 学号']);
    exit;
}

$db = Database::getInstance();
$successCount = 0;
$errorCount = 0;
$errors = [];
$importDetails = [];
$createdCount = 0;
$updatedCount = 0;
$rowNum = 1;

try {
    $db->beginTransaction();
    
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        
        // 跳过空行
        if (empty(array_filter($row, function ($value) {
            return trim((string) $value) !== '';
        }))) {
            continue;
        }
        
        if (count($row) > count($columns)) {
            $errorMessage = "第{$rowNum}行: 列数不匹配";
            $errors[] = $errorMessage;
            $importDetails[] = [
                'row_number' => $rowNum,
                'result' => 'failed',
                'error' => $errorMessage
            ];
            $errorCount++;
            continue;
        }

        if (count($row) < count($columns)) {
            // 补齐列数
            $row = array_pad($row, count($columns), '');
        }
        
        try {
            $data = array_combine($columns, array_map('trim', $row));
        
        // 验证学号
        if (empty($data['student_no'])) {
            $errorMessage = "第{$rowNum}行: 学号不能为空";
            $errors[] = $errorMessage;
            $importDetails[] = [
                'row_number' => $rowNum,
                'result' => 'failed',
                'error' => $errorMessage
            ];
            $errorCount++;
            continue;
        }
        
        // 查找对应的学生账户
        $user = $db->fetchOne("SELECT id, name FROM users WHERE username = ? AND role = 'student'", [$data['student_no']]);
        if (!$user) {
            $errorMessage = "第{$rowNum}行: 学号 '{$data['student_no']}' 对应的学生账户不存在";
            $errors[] = $errorMessage;
            $importDetails[] = [
                'row_number' => $rowNum,
                'result' => 'failed',
                'student_no' => $data['student_no'],
                'error' => $errorMessage
            ];
            $errorCount++;
            continue;
        }
        
        $userId = $user['id'];

        // 验证学院、年级、班级是否在系统设置中
        $validationErrors = [];

        if (!empty($data['college']) && !validateSystemOption('college', $data['college'])) {
            $validationErrors[] = '学院不在系统设置中：' . $data['college'];
        }

        if (!empty($data['grade'])) {
            if (!validateGradeFormat($data['grade'])) {
                $validationErrors[] = '年级格式错误（应为"xxxx级"）：' . $data['grade'];
            } elseif (!validateSystemOption('grade', $data['grade'])) {
                $validationErrors[] = '年级不在系统设置中：' . $data['grade'];
            }
        }

        if (!empty($data['class']) && !validateSystemOption('class', $data['class'])) {
            $validationErrors[] = '班级不在系统设置中：' . $data['class'];
        }

        if (!empty($validationErrors)) {
            $errorMessage = "第{$rowNum}行: " . implode('；', $validationErrors);
            $errors[] = $errorMessage;
            $importDetails[] = [
                'row_number' => $rowNum,
                'result' => 'failed',
                'student_no' => $data['student_no'],
                'error' => $errorMessage
            ];
            $errorCount++;
            continue;
        }

        // 检查是否已有student_info记录
        $studentInfo = $db->fetchOne("SELECT * FROM student_info WHERE user_id = ?", [$userId]);
        
        // 准备数据
        $gender = '';
        if (!empty($data['gender'])) {
            $gender = ($data['gender'] === '男' || $data['gender'] === 'male' || $data['gender'] === 'M') ? '男' : '女';
        }
        
        // 日期字段处理
        $dateFields = ['birth_date', 'join_league_date', 'apply_party_date', 'activist_date', 'probationary_date', 'full_member_date'];
        foreach ($dateFields as $field) {
            if (!empty($data[$field])) {
                // 尝试转换日期格式
                $timestamp = strtotime($data[$field]);
                if ($timestamp) {
                    $data[$field] = date('Y-m-d', $timestamp);
                } else {
                    $data[$field] = null;
                }
            } else {
                $data[$field] = null;
            }
        }
        
        // 计算年龄
        $age = null;
        if (!empty($data['birth_date'])) {
            $birthDate = new DateTime($data['birth_date']);
            $now = new DateTime();
            $age = $now->diff($birthDate)->y;
        }
        
        if ($studentInfo) {
            // 更新已有记录，只更新非空字段
            $updateFields = [];
            $updateParams = [];
            $rowChanges = [];
            
            $fieldList = [
                'name', 'gender', 'college', 'grade', 'class', 'ethnicity', 'id_card',
                'birth_date', 'phone', 'email', 'address', 'political_status', 'graduation_year',
                'join_league_date', 'apply_party_date', 'activist_date', 'probationary_date', 'full_member_date'
            ];
            
            foreach ($fieldList as $field) {
                if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                    $newValue = $field === 'gender' ? $gender : $data[$field];
                    if ($field === 'gender') {
                        $updateFields[] = "$field = ?";
                        $updateParams[] = $gender;
                    } else {
                        $updateFields[] = "$field = ?";
                        $updateParams[] = $data[$field];
                    }
                    $rowChanges[] = createLogFieldChange($field, getFieldLabel($field), $studentInfo[$field] ?? null, $newValue);
                }
            }
            
            if ($age !== null) {
                $updateFields[] = "age = ?";
                $updateParams[] = $age;
                $rowChanges[] = createLogFieldChange('age', getFieldLabel('age'), $studentInfo['age'] ?? null, $age);
            }
            
            // 标记信息已完善
            $updateFields[] = "info_completed = 1";
            $updateFields[] = "updated_at = NOW()";
            
            if (!empty($updateFields)) {
                $updateParams[] = $userId;
                $db->execute("UPDATE student_info SET " . implode(', ', $updateFields) . " WHERE user_id = ?", $updateParams);
            }
            $updatedCount++;
            $importDetails[] = [
                'row_number' => $rowNum,
                'result' => 'success',
                'operation' => 'update_student_info',
                'user_id' => (int) $userId,
                'student_no' => $data['student_no'],
                'name' => $data['name'] ?: $user['name'],
                'change_count' => count($rowChanges),
                'changes' => $rowChanges
            ];
            
        } else {
            // 创建新记录
            $name = !empty($data['name']) ? $data['name'] : $user['name'];
            
            $db->execute("
                INSERT INTO student_info (
                    user_id, student_no, name, gender, college, grade, class,
                    ethnicity, id_card, birth_date, age, phone, email, address,
                    political_status, graduation_year, join_league_date, apply_party_date,
                    activist_date, probationary_date, full_member_date, info_completed, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ", [
                $userId,
                $data['student_no'],
                $name,
                $gender ?: null,
                $data['college'] ?: null,
                $data['grade'] ?: null,
                $data['class'] ?: null,
                $data['ethnicity'] ?: null,
                $data['id_card'] ?: null,
                $data['birth_date'],
                $age,
                $data['phone'] ?: null,
                $data['email'] ?: null,
                $data['address'] ?: null,
                $data['political_status'] ?: '共青团员',
                $data['graduation_year'] ?: null,
                $data['join_league_date'],
                $data['apply_party_date'],
                $data['activist_date'],
                $data['probationary_date'],
                $data['full_member_date']
            ]);
            $createdCount++;
            $importDetails[] = [
                'row_number' => $rowNum,
                'result' => 'success',
                'operation' => 'create_student_info',
                'user_id' => (int) $userId,
                'student_no' => $data['student_no'],
                'name' => $name,
                'created_fields' => array_values(array_filter(array_keys($data), function ($field) use ($data) {
                    return $data[$field] !== '' && $data[$field] !== null;
                }))
            ];
        }
        
            $successCount++;
        } catch (Throwable $e) {
            $errorMessage = "第{$rowNum}行: 导入失败，" . $e->getMessage();
            $errors[] = $errorMessage;
            $importDetails[] = [
                'row_number' => $rowNum,
                'result' => 'failed',
                'student_no' => $data['student_no'] ?? null,
                'error' => $errorMessage
            ];
            $errorCount++;
            continue;
        }
    }
    
    // 记录操作日志
    logAdminSensitiveOperation('import', 'student_info', null, "批量导入学生信息: 成功{$successCount}条, 失败{$errorCount}条", [
        'success_count' => $successCount,
        'fail_count' => $errorCount,
        'created_count' => $createdCount,
        'updated_count' => $updatedCount,
        'errors' => array_slice($errors, 0, 10),
        'targets' => limitLogTargets(array_filter($importDetails, function ($detail) {
            return ($detail['result'] ?? '') === 'success';
        }))
    ], $importDetails, 'admin_import_student_info_rows', count($importDetails));
    
    $db->commit();
    fclose($handle);
    unlink($tempFile);
    
    $message = "导入完成: 成功 {$successCount} 条";
    if ($errorCount > 0) {
        $message .= ", 失败 {$errorCount} 条";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'successCount' => $successCount,
        'errorCount' => $errorCount,
        'errors' => $errors
    ]);
    
} catch (Throwable $e) {
    $db->rollBack();
    fclose($handle);
    unlink($tempFile);
    echo json_encode(['success' => false, 'message' => '导入失败：' . $e->getMessage()]);
}
