<?php
/**
 * 管理员端 - 添加账户API
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

// CSRF验证
Security::requireCSRFToken();

$data = requireAdminPasswordConfirmation();

// 清理输入数据
$username = Validator::cleanString($data['username'] ?? '', 50);
$name = Validator::cleanString($data['name'] ?? '', 50);
$role = Validator::cleanString($data['role'] ?? '', 20);
$gender = Validator::cleanString($data['gender'] ?? '', 10);
$college = Validator::cleanString($data['college'] ?? '', 100);
$grade = Validator::cleanString($data['grade'] ?? '', 20);
$className = Validator::cleanString($data['class'] ?? '', 50);

// 验证必填字段
if (empty($username) || empty($name) || empty($role)) {
    echo json_encode(['success' => false, 'message' => '请填写必要信息']);
    exit;
}

// 验证角色
$validRoles = ['student', 'teacher', 'admin', 'superadmin'];

// 权限检查：只有系统管理员可以创建教师、管理员和系统管理员账户
if (!canManageUser($role)) {
    echo json_encode(['success' => false, 'message' => '您没有权限创建该角色的账户']);
    exit;
}
if (!in_array($role, $validRoles)) {
    echo json_encode(['success' => false, 'message' => '无效的角色']);
    exit;
}

// 学生角色需要额外信息
if ($role === 'student') {
    if (empty($gender) || empty($college) || empty($grade) || empty($className)) {
        echo json_encode(['success' => false, 'message' => '请填写学生的完整信息']);
        exit;
    }
}

$db = Database::getInstance();

// 检查用户名是否已存在
$existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
if ($existing) {
    echo json_encode(['success' => false, 'message' => '用户名已存在']);
    exit;
}

try {
    $db->beginTransaction();
    
    // 默认密码为用户名
    $defaultPassword = password_hash($username, PASSWORD_DEFAULT);
    
    // 创建用户
    $db->execute("
        INSERT INTO users (username, password, name, role, is_active, is_first_login, created_at) 
        VALUES (?, ?, ?, ?, 1, 1, NOW())
    ", [$username, $defaultPassword, $name, $role]);
    
    $userId = $db->lastInsertId();
    
    // 如果是学生，创建student_info记录
    if ($role === 'student') {
        $genderValue = ($gender === 'male' || $gender === '男') ? '男' : '女';
        $db->execute("
            INSERT INTO student_info (user_id, student_no, name, gender, college, grade, class, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ", [$userId, $username, $name, $genderValue, $college, $grade, $className]);
    }
    
    // 记录操作日志
    $roleNames = ['student' => '学生', 'teacher' => '教师', 'admin' => '管理员', 'superadmin' => '系统管理员'];
    $logDetails = [
        'username' => $username,
        'name' => $name,
        'role' => $role,
        'force_change_password' => true,
        'student_profile' => $role === 'student' ? [
            'gender' => $genderValue,
            'college' => $college,
            'grade' => $grade,
            'class' => $className
        ] : null
    ];
    logAdminSensitiveOperation('create', 'user', $userId, "添加{$roleNames[$role]}账户: {$username}", $logDetails, $logDetails, 'admin_add_user', 1);
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => '账户添加成功，默认密码为用户名'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => '添加失败：' . $e->getMessage()]);
}
