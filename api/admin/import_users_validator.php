<?php
/**
 * Shared validation helpers for account CSV imports.
 */

function userImportFailureResponse($message, $errors = []) {
    return [
        'success' => false,
        'message' => $message,
        'accounts' => [],
        'total_count' => 0,
        'successCount' => 0,
        'errorCount' => count($errors),
        'error_count' => count($errors),
        'errors' => $errors,
    ];
}

function userImportColumnLabels() {
    return [
        'username' => '用户名',
        'name' => '姓名',
        'role' => '角色',
        'gender' => '性别',
        'college' => '学院',
        'grade' => '年级',
        'class' => '班级',
    ];
}

function userImportHeaderMap() {
    return [
        '用户名' => 'username',
        '姓名' => 'name',
        '角色' => 'role',
        '性别' => 'gender',
        '学院' => 'college',
        '年级' => 'grade',
        '班级' => 'class',
        'username' => 'username',
        'name' => 'name',
        'role' => 'role',
        'gender' => 'gender',
        'college' => 'college',
        'grade' => 'grade',
        'class' => 'class',
    ];
}

function userImportRoleMap() {
    return [
        '学生' => 'student',
        '教师' => 'teacher',
        '管理员' => 'admin',
        '系统管理员' => 'superadmin',
        'student' => 'student',
        'teacher' => 'teacher',
        'admin' => 'admin',
        'superadmin' => 'superadmin',
    ];
}

function userImportCell($value) {
    $value = (string) $value;
    $cleaned = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $value);
    if ($cleaned === null) {
        $cleaned = $value;
    }

    $cleaned = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $cleaned);
    if ($cleaned === null) {
        $cleaned = $value;
    }

    return trim($cleaned);
}

function userImportLength($value) {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    if (preg_match_all('/./us', $value, $matches) !== false) {
        return count($matches[0]);
    }

    return strlen($value);
}

function userImportNormalizeContent($content) {
    $boms = [
        "\xEF\xBB\xBF",
        "\xFF\xFE",
        "\xFE\xFF",
        "\x00\x00\xFE\xFF",
        "\xFF\xFE\x00\x00",
    ];

    foreach ($boms as $bom) {
        if (strpos($content, $bom) === 0) {
            $content = substr($content, strlen($bom));
            break;
        }
    }

    if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'GB18030', 'BIG5'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
    }

    return $content;
}

function userImportValidateUpload($file) {
    if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return '请上传文件';
    }

    $maxSize = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        return '文件大小不能超过5MB';
    }

    if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
        return '文件读取失败';
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        return '请上传 CSV 格式文件';
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'];
        if ($mimeType && !in_array($mimeType, $allowedMimes, true)) {
            return '文件类型不正确，只允许 CSV 文件';
        }
    }

    return null;
}

function userImportNormalizeHeader($header) {
    $headerMap = userImportHeaderMap();
    $labels = userImportColumnLabels();
    $columns = [];
    $seen = [];
    $duplicateLabels = [];
    $unsupportedLabels = [];
    $hasEmptyHeader = false;

    foreach ($header as $index => $rawColumn) {
        $columnName = userImportCell($rawColumn);
        if ($columnName === '') {
            $hasEmptyHeader = true;
            $columns[] = '';
            continue;
        }

        if (!isset($headerMap[$columnName])) {
            $unsupportedLabels[] = $columnName;
        }

        $column = $headerMap[$columnName] ?? $columnName;
        $columns[] = $column;

        if (isset($seen[$column])) {
            $duplicateLabels[$column] = $labels[$column] ?? $columnName;
        } else {
            $seen[$column] = $index + 1;
        }
    }

    $reasons = [];
    if ($hasEmptyHeader) {
        $reasons[] = '表头存在空列名';
    }

    if (!empty($duplicateLabels)) {
        $reasons[] = '列名重复：' . implode('、', array_values($duplicateLabels));
    }

    if (!empty($unsupportedLabels)) {
        $reasons[] = '存在不支持的列：' . implode('、', $unsupportedLabels);
    }

    $missingLabels = [];
    foreach (['username', 'name', 'role'] as $requiredColumn) {
        if (!in_array($requiredColumn, $columns, true)) {
            $missingLabels[] = $labels[$requiredColumn];
        }
    }

    if (!empty($missingLabels)) {
        $reasons[] = '缺少必要列：' . implode('、', $missingLabels);
    }

    if (!empty($reasons)) {
        return [
            'columns' => $columns,
            'errors' => ['第 1 行: ' . implode('；', $reasons)],
        ];
    }

    return [
        'columns' => $columns,
        'errors' => [],
    ];
}

function userImportNormalizeRole($value) {
    $value = userImportCell($value);
    $roleMap = userImportRoleMap();
    if (isset($roleMap[$value])) {
        return $roleMap[$value];
    }

    $lowerValue = strtolower($value);
    return $roleMap[$lowerValue] ?? null;
}

function userImportNormalizeGender($value) {
    $value = userImportCell($value);
    $lowerValue = strtolower($value);

    if ($value === '男' || $lowerValue === 'male' || $lowerValue === 'm') {
        return '男';
    }

    if ($value === '女' || $lowerValue === 'female' || $lowerValue === 'f') {
        return '女';
    }

    return null;
}

function userImportValidateRow($row, $columns, $rowNum, $db, &$seenUsernames) {
    $expectedCount = count($columns);
    $actualCount = count($row);
    if ($actualCount !== $expectedCount) {
        return [
            'errors' => ["第 {$rowNum} 行: 列数不匹配，应为 {$expectedCount} 列，实际 {$actualCount} 列"],
            'row' => null,
            'account' => null,
        ];
    }

    $values = array_map('userImportCell', $row);
    $data = array_combine($columns, $values);
    if ($data === false) {
        return [
            'errors' => ["第 {$rowNum} 行: 文件列格式错误"],
            'row' => null,
            'account' => null,
        ];
    }

    $labels = userImportColumnLabels();
    $reasons = [];

    foreach (['username', 'name', 'role'] as $requiredColumn) {
        if (($data[$requiredColumn] ?? '') === '') {
            $reasons[] = $labels[$requiredColumn] . '不能为空';
        }
    }

    if (($data['username'] ?? '') !== '') {
        $username = $data['username'];
        if (userImportLength($username) > 50) {
            $reasons[] = '用户名不能超过50个字符';
        }

        if (isset($seenUsernames[$username])) {
            $reasons[] = "用户名与第 {$seenUsernames[$username]} 行重复：{$username}";
        } else {
            $seenUsernames[$username] = $rowNum;
        }

        if (userImportLength($username) <= 50) {
            $existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
            if ($existing) {
                $reasons[] = "用户名已存在：{$username}";
            }
        }
    }

    if (($data['name'] ?? '') !== '' && userImportLength($data['name']) > 50) {
        $reasons[] = '姓名不能超过50个字符';
    }

    foreach (['college' => 100, 'grade' => 50, 'class' => 50] as $column => $maxLength) {
        if (($data[$column] ?? '') !== '' && userImportLength($data[$column]) > $maxLength) {
            $reasons[] = $labels[$column] . "不能超过{$maxLength}个字符";
        }
    }

    $role = null;
    if (($data['role'] ?? '') !== '') {
        $role = userImportNormalizeRole($data['role']);
        if (!$role) {
            $reasons[] = '角色无效：' . $data['role'];
        } elseif (!canManageUser($role)) {
            $reasons[] = '无权限导入该角色：' . $data['role'];
        }
    }

    if ($role === 'student') {
        $missingStudentFields = [];
        foreach (['gender', 'college', 'grade', 'class'] as $studentColumn) {
            if (($data[$studentColumn] ?? '') === '') {
                $missingStudentFields[] = $labels[$studentColumn];
            }
        }

        if (!empty($missingStudentFields)) {
            $reasons[] = '学生账户缺少' . implode('/', $missingStudentFields);
        }

        if (($data['gender'] ?? '') !== '') {
            $gender = userImportNormalizeGender($data['gender']);
            if ($gender === null) {
                $reasons[] = '性别无效：' . $data['gender'];
            } else {
                $data['gender'] = $gender;
            }
        }
    }

    if (!empty($reasons)) {
        return [
            'errors' => ['第 ' . $rowNum . ' 行: ' . implode('；', $reasons)],
            'row' => null,
            'account' => null,
        ];
    }

    return [
        'errors' => [],
        'row' => [
            'row_num' => $rowNum,
            'data' => $data,
            'role' => $role,
        ],
        'account' => [
            'username' => $data['username'],
            'name' => $data['name'],
            'role' => $role,
        ],
    ];
}

function userImportValidateUploadedFile($file, $db) {
    $uploadError = userImportValidateUpload($file);
    if ($uploadError !== null) {
        return userImportFailureResponse($uploadError, [$uploadError]);
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        return userImportFailureResponse('文件读取失败', ['文件读取失败']);
    }

    $content = userImportNormalizeContent($content);
    if (trim($content) === '') {
        return userImportFailureResponse('预检失败：文件内容为空', ['第 1 行: 文件内容为空']);
    }

    $handle = fopen('php://temp', 'r+');
    if (!$handle) {
        return userImportFailureResponse('文件读取失败', ['文件读取失败']);
    }

    fwrite($handle, $content);
    rewind($handle);

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return userImportFailureResponse('预检失败：文件格式错误', ['第 1 行: 文件表头为空或格式错误']);
    }

    $headerResult = userImportNormalizeHeader($header);
    if (!empty($headerResult['errors'])) {
        fclose($handle);
        return userImportFailureResponse(
            '预检失败：发现 ' . count($headerResult['errors']) . ' 行不符合要求',
            $headerResult['errors']
        );
    }

    $columns = $headerResult['columns'];
    $errors = [];
    $rows = [];
    $accounts = [];
    $seenUsernames = [];
    $dataRowCount = 0;
    $rowNum = 1;

    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;

        if (empty(array_filter($row, function ($value) {
            return userImportCell($value) !== '';
        }))) {
            continue;
        }

        $dataRowCount++;
        $rowResult = userImportValidateRow($row, $columns, $rowNum, $db, $seenUsernames);
        if ($dataRowCount > 1000) {
            $limitError = "第 {$rowNum} 行: 单次最多导入1000条记录";
            if (!empty($rowResult['errors'])) {
                $lineReason = preg_replace('/^第\s+' . preg_quote((string) $rowNum, '/') . '\s+行:\s*/u', '', $rowResult['errors'][0]);
                $limitError .= '；' . $lineReason;
            }
            $errors[] = $limitError;
            continue;
        }

        if (!empty($rowResult['errors'])) {
            $errors = array_merge($errors, $rowResult['errors']);
            continue;
        }

        $rows[] = $rowResult['row'];
        $accounts[] = $rowResult['account'];
    }

    fclose($handle);

    if ($dataRowCount === 0) {
        $errors[] = '第 2 行: 请至少填写一条账户数据';
    }

    if (!empty($errors)) {
        return userImportFailureResponse('预检失败：发现 ' . count($errors) . ' 行不符合要求', $errors);
    }

    return [
        'success' => true,
        'message' => '预检通过',
        'accounts' => $accounts,
        'total_count' => count($accounts),
        'error_count' => 0,
        'errors' => [],
        'rows' => $rows,
    ];
}
