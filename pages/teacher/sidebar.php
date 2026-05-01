<?php
/**
 * 教师端侧边栏
 */
$sidebarMenu = [
    [
        'title' => '数据展板',
        'icon' => 'fa-solid fa-chart-pie',
        'url' => '/pages/teacher/index.php'
    ],
    [
        'title' => '学生信息',
        'icon' => 'fa-solid fa-users',
        'url' => '/pages/teacher/student_list.php'
    ],
    [
        'title' => '毕业生管理',
        'icon' => 'fa-solid fa-graduation-cap',
        'url' => '/pages/teacher/graduated.php'
    ],
    [
        'title' => '修改密码',
        'icon' => 'fa-solid fa-key',
        'url' => '/pages/teacher/change_password.php'
    ]
];
?>
