<?php
/**
 * 管理员端 - 批量导入学生信息预览 API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

requireRole('admin');
Security::requireCSRFToken();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '请求方法不正确']);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => '请上传文件']);
}

$file = $_FILES['file'];
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    jsonResponse(['success' => false, 'message' => '文件大小不能超过5MB']);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    jsonResponse(['success' => false, 'message' => '请上传 CSV 格式文件']);
}

if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        jsonResponse(['success' => false, 'message' => '文件类型不正确，只允许 CSV 文件']);
    }
}

$content = file_get_contents($file['tmp_name']);
if ($content === false) {
    jsonResponse(['success' => false, 'message' => '文件读取失败']);
}

$boms = ["\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF"];
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

if (substr_count($content, "\n") > 1001) {
    jsonResponse(['success' => false, 'message' => '单次最多导入1000条记录']);
}

$tempFile = sys_get_temp_dir() . '/preview_student_info_' . uniqid('', true) . '.tmp';
$written = file_put_contents($tempFile, $content);
if ($written === false) {
    jsonResponse(['success' => false, 'message' => '临时文件写入失败，请检查服务器临时目录权限']);
}
$handle = fopen($tempFile, 'r');

if (!$handle) {
    @unlink($tempFile);
    jsonResponse(['success' => false, 'message' => '文件读取失败']);
}

$header = fgetcsv($handle);
if (!$header) {
    fclose($handle);
    @unlink($tempFile);
    jsonResponse(['success' => false, 'message' => '文件格式错误']);
}

$header = array_map(function ($col) {
    $col = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $col);
    $col = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $col);
    return trim($col);
}, $header);

$headerMap = [
    '学号' => 'student_no',
    '姓名' => 'name',
    'student_no' => 'student_no',
    'name' => 'name',
];

$columns = [];
foreach ($header as $col) {
    $columns[] = $headerMap[$col] ?? $col;
}

if (!in_array('student_no', $columns, true)) {
    fclose($handle);
    @unlink($tempFile);
    jsonResponse(['success' => false, 'message' => '缺少必要列：student_no']);
}

$db = Database::getInstance();
$accounts = [];
$errors = [];
$errorCount = 0;
$rowNum = 1;

while (($row = fgetcsv($handle)) !== false) {
    $rowNum++;

    if (empty(array_filter($row, function ($value) {
        return trim((string) $value) !== '';
    }))) {
        continue;
    }

    if (count($row) > count($columns)) {
        $errors[] = "第 {$rowNum} 行列数不匹配";
        $errorCount++;
        continue;
    }

    if (count($row) < count($columns)) {
        $row = array_pad($row, count($columns), '');
    }

    $data = array_combine($columns, array_map('trim', $row));

    if (empty($data['student_no'])) {
        $errors[] = "第 {$rowNum} 行学号不能为空";
        $errorCount++;
        continue;
    }

    $user = $db->fetchOne("SELECT id, name FROM users WHERE username = ? AND role = 'student'", [$data['student_no']]);
    if (!$user) {
        $errors[] = "第 {$rowNum} 行学号 {$data['student_no']} 对应的学生账户不存在";
        $errorCount++;
        continue;
    }

    $accounts[] = [
        'username' => $data['student_no'],
        'name' => $data['name'] ?: $user['name'],
    ];
}

fclose($handle);
@unlink($tempFile);

jsonResponse([
    'success' => true,
    'message' => '预览生成成功',
    'accounts' => $accounts,
    'total_count' => count($accounts),
    'error_count' => $errorCount,
    'errors' => $errors,
]);
