<?php
/**
 * 系统管理员侧边栏
 */

require_once __DIR__ . '/../../includes/auth.php';

$db = Database::getInstance();
$pendingAuditCount = (int) $db->fetchOne(
    "SELECT COUNT(DISTINCT batch_id) AS count FROM info_change_requests WHERE status = 'pending'"
)['count'];

$sidebarMenu = [
    [
        'title' => '数据展板',
        'icon' => 'fa-solid fa-chart-pie',
        'url' => '/pages/superadmin/index.php'
    ],
    [
        'title' => '账户管理',
        'icon' => 'fa-solid fa-user-gear',
        'url' => '/pages/admin/accounts.php'
    ],
    [
        'title' => '信息审核',
        'icon' => 'fa-solid fa-clipboard-check',
        'url' => '/pages/admin/audit.php',
        'badge_count' => $pendingAuditCount,
        'badge_id' => 'sidebarPendingAuditBadge',
        'badge_class' => 'warning'
    ],
    [
        'title' => '学生信息',
        'icon' => 'fa-solid fa-users',
        'url' => '/pages/admin/student_list.php'
    ],
    [
        'title' => '毕业生管理',
        'icon' => 'fa-solid fa-graduation-cap',
        'url' => '/pages/admin/graduated.php'
    ],
    [
        'title' => '系统设置',
        'icon' => 'fa-solid fa-cog',
        'url' => '/pages/admin/settings.php'
    ],
    [
        'title' => '操作日志',
        'icon' => 'fa-solid fa-history',
        'url' => '/pages/admin/logs.php'
    ],
    [
        'title' => '修改密码',
        'icon' => 'fa-solid fa-key',
        'url' => '/pages/superadmin/change_password.php'
    ]
];
