<?php
/**
 * Admin sidebar
 */

require_once __DIR__ . '/../../includes/auth.php';

if (!function_exists('adminSidebarText')) {
    function adminSidebarText($value) {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }
}

$db = Database::getInstance();
$pendingAuditCount = (int) $db->fetchOne(
    "SELECT COUNT(DISTINCT batch_id) AS count FROM info_change_requests WHERE status = 'pending'"
)['count'];

$sidebarMenu = [
    [
        'title' => adminSidebarText('&#25968;&#25454;&#23637;&#26495;'),
        'icon' => 'fa-solid fa-chart-pie',
        'url' => '/pages/admin/index.php'
    ],
    [
        'title' => adminSidebarText('&#36134;&#25143;&#31649;&#29702;'),
        'icon' => 'fa-solid fa-user-gear',
        'url' => '/pages/admin/accounts.php'
    ],
    [
        'title' => adminSidebarText('&#20449;&#24687;&#23457;&#26680;'),
        'icon' => 'fa-solid fa-clipboard-check',
        'url' => '/pages/admin/audit.php',
        'badge_count' => $pendingAuditCount,
        'badge_id' => 'sidebarPendingAuditBadge',
        'badge_class' => 'warning'
    ],
    [
        'title' => adminSidebarText('&#23398;&#29983;&#20449;&#24687;'),
        'icon' => 'fa-solid fa-users',
        'url' => '/pages/admin/student_list.php'
    ],
    [
        'title' => adminSidebarText('&#27605;&#19994;&#29983;&#31649;&#29702;'),
        'icon' => 'fa-solid fa-graduation-cap',
        'url' => '/pages/admin/graduated.php'
    ],
    [
        'title' => adminSidebarText('&#31995;&#32479;&#35774;&#32622;'),
        'icon' => 'fa-solid fa-cog',
        'url' => '/pages/admin/settings.php'
    ],
];

if (isSuperAdmin()) {
    $sidebarMenu[] = [
        'title' => adminSidebarText('&#21453;&#39304;&#31649;&#29702;'),
        'icon' => 'fa-solid fa-comments',
        'url' => '/pages/admin/feedback.php'
    ];
    $sidebarMenu[] = [
        'title' => adminSidebarText('&#25805;&#20316;&#26085;&#24535;'),
        'icon' => 'fa-solid fa-history',
        'url' => '/pages/admin/logs.php'
    ];
}

$sidebarMenu[] = [
    'title' => adminSidebarText('&#20462;&#25913;&#23494;&#30721;'),
    'icon' => 'fa-solid fa-key',
    'url' => '/pages/admin/change_password.php'
];
