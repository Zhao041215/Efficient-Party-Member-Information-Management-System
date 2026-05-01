<?php
/**
 * 系统管理员端数据展板
 */
$pageTitle = '数据展板 - 生化学院党员信息管理系统';
$needChart = true;

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// 检查角色 - 仅系统管理员
requireRole(['superadmin']);

// 重定向到管理员页面（共享相同的数据展板）
header('Location: /pages/admin/index.php');
exit;
?>
