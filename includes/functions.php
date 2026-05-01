<?php
/**
 * 通用函数库
 */

require_once __DIR__ . '/database.php';

function sendNoCacheHeaders() {
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

/**
 * 获取系统选项
 */
function getSystemOptions($type) {
    $db = Database::getInstance();
    $sql = "SELECT value FROM system_options WHERE type = ? AND is_active = 1 ORDER BY sort_order ASC";
    $results = $db->fetchAll($sql, [$type]);
    return array_column($results, 'value');
}

/**
 * 验证值是否在系统选项中
 * @param string $type 选项类型 (college, grade, class, etc.)
 * @param string $value 要验证的值
 * @return bool 如果有效返回true，否则返回false
 */
function validateSystemOption($type, $value) {
    if (empty($value)) {
        return true; // 空值由必填字段验证处理
    }

    $validOptions = getSystemOptions($type);
    return in_array($value, $validOptions, true);
}

/**
 * 验证年级格式（必须是"xxxx级"）
 * @param string $grade 年级值
 * @return bool 如果格式有效返回true，否则返回false
 */
function validateGradeFormat($grade) {
    if (empty($grade)) {
        return true; // 空值由必填字段验证处理
    }

    // 必须匹配模式：4位数字 + "级" (例如："2024级")
    return preg_match('/^\d{4}级$/', $grade) === 1;
}

/**
 * 获取政治面貌的兼容值列表
 */
function getPoliticalStatusVariants($status) {
    $status = trim((string) $status);
    if ($status === '') {
        return [];
    }

    $aliases = [
        '预备党员' => ['预备党员', '中共预备党员'],
        '中共预备党员' => ['预备党员', '中共预备党员'],
        '正式党员' => ['正式党员', '中共党员'],
        '中共党员' => ['正式党员', '中共党员'],
    ];

    return $aliases[$status] ?? [$status];
}

/**
 * 将筛选参数统一为去空后的数组，兼容旧的单选查询参数。
 */
function normalizeFilterValues($value) {
    if (!is_array($value)) {
        $value = [$value];
    }

    $values = [];
    foreach ($value as $item) {
        $item = trim((string) $item);
        if ($item === '' || $item === 'all') {
            continue;
        }
        $values[] = $item;
    }

    return array_values(array_unique($values));
}

/**
 * 为查询追加普通多选筛选。
 */
function appendMultiSelectFilter(array &$where, array &$params, $column, $values) {
    $values = normalizeFilterValues($values);
    if (empty($values)) {
        return;
    }

    if (count($values) === 1) {
        $where[] = "{$column} = ?";
        $params[] = $values[0];
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($values), '?'));
    $where[] = "{$column} IN ({$placeholders})";
    array_push($params, ...$values);
}

/**
 * 为查询追加政治面貌筛选，兼容同义状态值
 */
function appendPoliticalStatusFilter(array &$where, array &$params, $column, $status) {
    $variants = [];
    foreach (normalizeFilterValues($status) as $item) {
        $variants = array_merge($variants, getPoliticalStatusVariants($item));
    }
    $variants = array_values(array_unique($variants));

    if (empty($variants)) {
        return;
    }

    if (count($variants) === 1) {
        $where[] = "{$column} = ?";
        $params[] = $variants[0];
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($variants), '?'));
    $where[] = "{$column} IN ({$placeholders})";
    array_push($params, ...$variants);
}

/**
 * 获取发展时间配置
 * 返回格式：['确定入党积极分子时间' => '2024-03-15', ...]
 */
function getDevelopmentTimes() {
    $db = Database::getInstance();
    $sql = "SELECT value FROM system_options WHERE type = 'development_time' AND is_active = 1 ORDER BY sort_order ASC";
    $results = $db->fetchAll($sql);

    $times = [];
    foreach ($results as $row) {
        $parts = explode('|', $row['value']);
        if (count($parts) === 2) {
            $times[$parts[0]] = $parts[1];
        }
    }
    return $times;
}

/**
 * 获取特定发展时间配置
 * @param string $timeName 时间名称，如：'确定入党积极分子时间'
 * @return string|null 返回日期字符串或null
 */
function getDevelopmentTime($timeName) {
    $times = getDevelopmentTimes();
    return $times[$timeName] ?? null;
}

/**
 * 验证身份证号
 */
function validateIdCard($idCard) {
    if (strlen($idCard) !== 18) {
        return false;
    }
    
    // 加权因子
    $weight = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
    // 校验码
    $checkCode = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
    
    $sum = 0;
    for ($i = 0; $i < 17; $i++) {
        if (!is_numeric($idCard[$i])) {
            return false;
        }
        $sum += intval($idCard[$i]) * $weight[$i];
    }
    
    $mod = $sum % 11;
    return strtoupper($idCard[17]) === $checkCode[$mod];
}

/**
 * 从身份证号计算年龄
 */
function calculateAgeFromIdCard($idCard) {
    if (strlen($idCard) !== 18) {
        return null;
    }
    
    $birthYear = substr($idCard, 6, 4);
    $birthMonth = substr($idCard, 10, 2);
    $birthDay = substr($idCard, 12, 2);
    
    $birthDate = new DateTime("{$birthYear}-{$birthMonth}-{$birthDay}");
    $today = new DateTime();
    
    return $today->diff($birthDate)->y;
}

/**
 * 从身份证号获取出生日期
 */
function getBirthDateFromIdCard($idCard) {
    if (strlen($idCard) !== 18) {
        return null;
    }
    
    $birthYear = substr($idCard, 6, 4);
    $birthMonth = substr($idCard, 10, 2);
    $birthDay = substr($idCard, 12, 2);
    
    return "{$birthYear}-{$birthMonth}-{$birthDay}";
}

/**
 * 验证手机号
 */
function validatePhone($phone) {
    return preg_match('/^1[3-9]\d{9}$/', $phone);
}

/**
 * 验证邮箱
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 生成唯一批次ID
 */
function generateBatchId() {
    return date('YmdHis') . '_' . bin2hex(random_bytes(4));
}

/**
 * 格式化日期
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return '';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '';
}

/**
 * 获取所有字段标签映射
 */
function getFieldLabels() {
    return [
        'student_no' => '学号',
        'name' => '姓名',
        'gender' => '性别',
        'college' => '学院',
        'grade' => '年级',
        'class' => '班级',
        'birth_date' => '出生日期',
        'ethnicity' => '民族',
        'id_card' => '身份证号',
        'address' => '家庭住址',
        'phone' => '联系方式',
        'email' => '邮箱',
        'political_status' => '政治面貌',
        'age' => '年龄',
        'join_league_date' => '入团时间',
        'apply_party_date' => '递交入党申请书时间',
        'activist_date' => '确定积极分子时间',
        'probationary_date' => '确定预备党员时间',
        'full_member_date' => '转正时间',
        'graduation_year' => '毕业时间'
    ];
}

/**
 * 字段名映射
 */
function getFieldLabel($fieldName) {
    $labels = getFieldLabels();
    return $labels[$fieldName] ?? $fieldName;
}

/**
 * 分页计算
 */
function paginate($total, $page, $perPage) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages
    ];
}

/**
 * 生成分页HTML
 */
function generatePaginationHtml($pagination, $baseUrl = '') {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<nav class="pagination-nav"><ul class="pagination">';
    
    // 上一页
    if ($pagination['has_prev']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($pagination['current_page'] - 1) . '">上一页</a></li>';
    }
    
    // 页码
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $pagination['total_pages'] . '">' . $pagination['total_pages'] . '</a></li>';
    }
    
    // 下一页
    if ($pagination['has_next']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($pagination['current_page'] + 1) . '">下一页</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * 清理输入数据
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return trim(strip_tags($data));
}

/**
 * 检查是否为AJAX请求
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * 获取请求方法
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * 获取POST数据
 */
function getPostData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }
    
    return $_POST;
}

/**
 * 记录操作日志
 */
function logOperation($action, $targetType, $targetId, $description = '', $details = null) {
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'system';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    return writeOperationLog(
        $userId,
        $username,
        $action,
        $description,
        buildOperationLogDetails($targetType, $targetId, $details),
        $ip
    );
}

/**
 * 敏感信息脱敏
 */
function maskSensitiveValueByField($fieldName, $value) {
    $fieldName = strtolower((string) $fieldName);

    if (!is_string($value)) {
        return $value;
    }

    if (isSensitiveLogField($fieldName)) {
        return '***MASKED***';
    }

    if ($fieldName === 'id_card' && strlen($value) === 18) {
        return substr($value, 0, 6) . '********' . substr($value, -4);
    }

    if ($fieldName === 'phone' && strlen($value) === 11) {
        return substr($value, 0, 3) . '****' . substr($value, -4);
    }

    if ($fieldName === 'email' && strpos($value, '@') !== false) {
        $parts = explode('@', $value, 2);
        return substr($parts[0], 0, 2) . '***@' . $parts[1];
    }

    return $value;
}

function maskSensitiveDataLegacy($data) {
    if (is_array($data)) {
        $masked = [];
        $fieldContext = strtolower((string) ($data['field'] ?? $data['field_name'] ?? ''));
        foreach ($data as $key => $value) {
            // 敏感字段列表
            $normalizedKey = strtolower((string) $key);

            if (isSensitiveLogField($normalizedKey)) {
                $masked[$key] = is_bool($value) ? $value : '***MASKED***';
            } elseif (isSensitivePersonalLogField($normalizedKey)) {
                $masked[$key] = maskSensitiveValueByField(resolveSensitivePersonalLogField($normalizedKey), $value);
            } elseif (is_array($value)) {
                $masked[$key] = maskSensitiveData($value);
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }
    return $data;
}

function maskSensitiveData($data) {
    if (!is_array($data)) {
        return $data;
    }

    $masked = [];
    $fieldContext = strtolower((string) ($data['field'] ?? $data['field_name'] ?? ''));
    foreach ($data as $key => $value) {
        $normalizedKey = strtolower((string) $key);

        if (isSensitiveLogField($normalizedKey)) {
            $masked[$key] = is_bool($value) ? $value : '***MASKED***';
        } elseif (in_array($normalizedKey, ['old', 'new', 'old_value', 'new_value', 'from', 'to'], true) && $fieldContext !== '') {
            $masked[$key] = maskSensitiveValueByField($fieldContext, $value);
        } elseif (isSensitivePersonalLogField($normalizedKey)) {
            $masked[$key] = maskSensitiveValueByField(resolveSensitivePersonalLogField($normalizedKey), $value);
        } elseif (is_array($value)) {
            $masked[$key] = maskSensitiveData($value);
        } else {
            $masked[$key] = $value;
        }
    }

    return $masked;
}

function isSensitiveLogField($fieldName) {
    $fieldName = strtolower((string) $fieldName);
    $fieldName = str_replace(['-', ' '], '_', $fieldName);

    if (in_array($fieldName, ['password', 'passwd', 'pwd', 'admin_password', 'token', 'secret', 'code', 'api_key', 'apikey', 'auth'], true)) {
        return true;
    }

    return strpos($fieldName, 'password') !== false
        || strpos($fieldName, 'token') !== false
        || strpos($fieldName, 'secret') !== false
        || strpos($fieldName, 'code') !== false;
}

function isSensitivePersonalLogField($fieldName) {
    $fieldName = strtolower((string) $fieldName);
    return $fieldName === 'id_card'
        || strpos($fieldName, 'id_card') !== false
        || strpos($fieldName, 'phone') !== false
        || strpos($fieldName, 'email') !== false;
}

function resolveSensitivePersonalLogField($fieldName) {
    $fieldName = strtolower((string) $fieldName);
    if (strpos($fieldName, 'id_card') !== false) {
        return 'id_card';
    }
    if (strpos($fieldName, 'phone') !== false) {
        return 'phone';
    }
    if (strpos($fieldName, 'email') !== false) {
        return 'email';
    }
    return $fieldName;
}

function operationLogHasDetailsColumn() {
    static $hasDetailsColumn = null;

    if ($hasDetailsColumn !== null) {
        return $hasDetailsColumn;
    }

    try {
        $db = Database::getInstance();
        $hasDetailsColumn = (bool) $db->fetchOne("SHOW COLUMNS FROM operation_logs LIKE 'details'");
    } catch (Exception $e) {
        $hasDetailsColumn = false;
    }

    return $hasDetailsColumn;
}

function writeOperationLog($userId, $username, $action, $description = '', $details = null, $ip = null) {
    $db = Database::getInstance();
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    if (operationLogHasDetailsColumn()) {
        $db->execute(
            "INSERT INTO operation_logs (user_id, username, action, description, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $username, $action, $description, normalizeLogDetails($details), $ip]
        );
        return (int) $db->lastInsertId();
    }

    $db->execute(
        "INSERT INTO operation_logs (user_id, username, action, description, ip_address) VALUES (?, ?, ?, ?, ?)",
        [$userId, $username, $action, $description, $ip]
    );

    return (int) $db->lastInsertId();
}

function buildOperationLogDetails($targetType, $targetId, $details = null) {
    if ($details === null || $details === '' || $details === []) {
        return null;
    }

    if (!is_array($details)) {
        return $details;
    }

    if (!isset($details['target']) && ($targetType !== null || $targetId !== null)) {
        $details['target'] = [
            'type' => $targetType,
            'id' => $targetId
        ];
    }

    return $details;
}

function normalizeLogDetails($details) {
    if ($details === null || $details === '' || $details === []) {
        return null;
    }

    if (is_string($details)) {
        return $details;
    }

    $maskedDetails = maskSensitiveData($details);
    if ($maskedDetails === [] || $maskedDetails === null) {
        return null;
    }

    return json_encode($maskedDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function operationLogHasFullDetailsTable() {
    static $hasFullDetailsTable = null;

    if ($hasFullDetailsTable !== null) {
        return $hasFullDetailsTable;
    }

    try {
        $db = Database::getInstance();
        $hasFullDetailsTable = (bool) $db->fetchOne("SHOW TABLES LIKE 'operation_log_full_details'");
    } catch (Exception $e) {
        $hasFullDetailsTable = false;
    }

    return $hasFullDetailsTable;
}

function logOperationFullDetails($operationLogId, $detailScope, $details, $detailCount = null) {
    if (!$operationLogId || !operationLogHasFullDetailsTable()) {
        return null;
    }

    $normalized = normalizeLogDetails($details);
    if ($normalized === null) {
        return null;
    }

    if ($detailCount === null) {
        $detailCount = is_array($details) ? count($details) : 1;
    }

    $db = Database::getInstance();
    $db->execute(
        "INSERT INTO operation_log_full_details (operation_log_id, detail_scope, detail_count, details_json) VALUES (?, ?, ?, ?)",
        [(int) $operationLogId, $detailScope, (int) $detailCount, $normalized]
    );

    return (int) $db->lastInsertId();
}

function logAdminSensitiveOperation($action, $targetType, $targetId, $description, array $summary = [], $fullDetails = null, $detailScope = 'admin_sensitive_operation', $detailCount = null) {
    $summary['admin_password_confirmed'] = true;
    $canWriteFullDetails = $fullDetails !== null && operationLogHasFullDetailsTable();

    if ($canWriteFullDetails) {
        $summary['has_full_details'] = true;
        if ($detailCount === null && is_array($fullDetails)) {
            $detailCount = count($fullDetails);
        }
        if ($detailCount !== null) {
            $summary['full_detail_count'] = (int) $detailCount;
        }
        $summary['full_detail_scope'] = $detailScope;
    } elseif ($fullDetails !== null) {
        $summary['has_full_details'] = false;
        $summary['full_details_missing_reason'] = 'operation_log_full_details table not found';
    }

    $operationLogId = logOperation($action, $targetType, $targetId, $description, $summary);

    if ($canWriteFullDetails) {
        logOperationFullDetails($operationLogId, $detailScope, $fullDetails, $detailCount);
    }

    return $operationLogId;
}

function limitLogTargets(array $targets, $limit = 20) {
    return array_slice(array_values($targets), 0, $limit);
}

function createLogFieldChange($field, $label, $oldValue, $newValue) {
    return [
        'field' => $field,
        'label' => $label,
        'old' => $oldValue,
        'new' => $newValue
    ];
}

function summarizeLogText($text, $maxLength = 120) {
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }
        return mb_substr($text, 0, $maxLength, 'UTF-8') . '...';
    }

    if (strlen($text) <= $maxLength) {
        return $text;
    }

    return substr($text, 0, $maxLength) . '...';
}

function decodeLogDetails($details) {
    if ($details === null || $details === '') {
        return null;
    }

    $decoded = json_decode($details, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    return $details;
}

function renderLogDetailsHtml($details) {
    if ($details === null || $details === '') {
        return '-';
    }

    $decoded = decodeLogDetails($details);
    if (!is_array($decoded)) {
        return '<div class="log-detail-text">' . htmlspecialchars((string) $decoded, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    return renderLogDetailsNode($decoded);
}

function renderLogDetailsNode($node) {
    if (!is_array($node)) {
        return '<span>' . htmlspecialchars(formatLogDetailValue($node), ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $isList = array_keys($node) === range(0, count($node) - 1);
    $html = '<ul class="log-detail-list">';

    foreach ($node as $key => $value) {
        if ($isList) {
            $html .= '<li>' . renderLogDetailItemValue($value) . '</li>';
            continue;
        }

        $html .= '<li><strong>' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . ':</strong> ' . renderLogDetailItemValue($value) . '</li>';
    }

    $html .= '</ul>';

    return $html;
}

function renderLogDetailItemValue($value) {
    if (is_array($value)) {
        return renderLogDetailsNode($value);
    }

    return '<span>' . htmlspecialchars(formatLogDetailValue($value), ENT_QUOTES, 'UTF-8') . '</span>';
}

function formatLogDetailValue($value) {
    if ($value === null || $value === '') {
        return '-';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

/**
 * 获取日志文件路径（支持日志轮转）
 */
function getLogFilePath($type = 'error') {
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0700, true);
        // 创建 .htaccess 保护日志目录
        $htaccess = $logDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Require all denied\n");
            chmod($htaccess, 0600);
        }
    }

    // 按日期轮转日志
    $date = date('Y-m-d');
    $logFile = $logDir . '/' . $type . '_' . $date . '.log';

    // 确保日志文件权限安全
    if (file_exists($logFile)) {
        chmod($logFile, 0600);
    }

    return $logFile;
}

/**
 * 清理旧日志文件（保留最近30天）
 */
function cleanOldLogs() {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        return;
    }

    $files = glob($logDir . '/*.log');
    $cutoffTime = time() - (30 * 24 * 60 * 60); // 30天前

    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            unlink($file);
        }
    }
}

/**
 * 记录错误日志（增强版）
 */
function logError($message, $context = []) {
    try {
        $logFile = getLogFilePath('error');

        // 脱敏处理
        $maskedContext = maskSensitiveData($context);

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? 'guest';

        $logData = [
            'timestamp' => $timestamp,
            'level' => 'ERROR',
            'message' => $message,
            'context' => $maskedContext,
            'ip' => $ip,
            'user_id' => $userId,
            'user_agent' => substr($userAgent, 0, 200) // 限制长度
        ];

        $logMessage = json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n";

        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // 每100次调用清理一次旧日志（概率触发）
        if (rand(1, 100) === 1) {
            cleanOldLogs();
        }
    } catch (Exception $e) {
        // 静默处理日志记录失败
    }
}

/**
 * 记录安全日志
 */
function logSecurity($event, $details = []) {
    try {
        $logFile = getLogFilePath('security');

        // 脱敏处理
        $maskedDetails = maskSensitiveData($details);

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? 'guest';
        $username = $_SESSION['username'] ?? 'guest';

        $logData = [
            'timestamp' => $timestamp,
            'level' => 'SECURITY',
            'event' => $event,
            'details' => $maskedDetails,
            'ip' => $ip,
            'user_id' => $userId,
            'username' => $username,
            'user_agent' => substr($userAgent, 0, 200)
        ];

        $logMessage = json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n";

        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // 静默处理安全日志记录失败
    }
}

/**
 * 生成分页HTML
 */
function renderFeedbackTipAlert($messageHtml = null, array $options = []) {
    $defaultMessage = 'Bug/&#24314;&#35758;&#35831;&#20351;&#29992;&#21491;&#20391;&#20449;&#24687;&#21453;&#39304;&#20837;&#21475;';
    $defaultStatus = '&#23454;&#26102;&#21047;&#26032;&#20013;';
    $feedbackHref = $options['feedback_href'] ?? '/pages/feedback.php';
    $extraStatusHtml = $options['extra_status_html'] ?? '';

    if ($messageHtml === null || $messageHtml === '') {
        $messageHtml = $defaultMessage;
    }

    $statusHtml = $options['status_html'] ?? $defaultStatus;

    echo '<div class="alert alert-info tip-alert">';
    echo '<div class="tip-alert-main">';
    echo '<i class="fa-solid fa-info-circle"></i>';
    echo '<div class="tip-alert-main-content">';
    echo '<strong>&#28201;&#39336;&#25552;&#31034;&#65306;</strong>' . $messageHtml;
    echo '</div>';
    echo '</div>';
    echo '<div class="tip-alert-side">';
    echo '<a href="' . htmlspecialchars($feedbackHref, ENT_QUOTES, 'UTF-8') . '" class="tip-alert-link">';
    echo '<i class="fa-solid fa-comments"></i><span>&#20449;&#24687;&#21453;&#39304;</span>';
    echo '</a>';
    echo '<span class="realtime-status tip-alert-feedback-status">';
    echo '<i class="fa-solid fa-circle-dot pulse-dot"></i><span>' . $statusHtml . '</span>';
    echo '</span>';
    echo $extraStatusHtml;
    echo '</div>';
    echo '</div>';
}
function generatePagination($currentPage, $totalPages, $baseUrl = '?page=') {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav class="pagination-nav"><ul class="pagination">';
    
    // 上一页
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . ($currentPage - 1) . '">上一页</a></li>';
    }
    
    // 页码
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // 下一页
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . ($currentPage + 1) . '">下一页</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}
