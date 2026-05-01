<?php
/**
 * 学生端侧边栏
 */
$sidebarMenu = [
    [
        'title' => '个人信息',
        'icon' => 'fa-solid fa-user',
        'url' => '/pages/student/index.php'
    ],
    [
        'title' => '修改信息',
        'icon' => 'fa-solid fa-pen-to-square',
        'url' => '/pages/student/edit_info.php'
    ],
    [
        'title' => '待审核列表',
        'icon' => 'fa-solid fa-clock',
        'url' => '/pages/student/pending_list.php'
    ],
    [
        'title' => '修改密码',
        'icon' => 'fa-solid fa-key',
        'url' => '/pages/student/change_password.php'
    ]
];
?>
