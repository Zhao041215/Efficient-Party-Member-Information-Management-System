<?php
/**
 * 通用头部模板
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/version.php';

sendNoCacheHeaders();

// 在 header.php 的开头或 auth.php 中添加
if (isset($_SESSION['user_id'])) {
    $db = Database::getInstance();
    
    // 检查用户角色
    $user = $db->fetchOne("SELECT role, force_change_password FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // 如果是学生，检查信息填写状态
    if ($user && $user['role'] === 'student') {
        $studentInfo = $db->fetchOne("SELECT info_completed FROM student_info WHERE user_id = ?", [$_SESSION['user_id']]);
        
        $currentPage = basename($_SERVER['PHP_SELF']);
        
        // 检查是否需要强制修改密码
        $needChangePassword = $user['force_change_password'] == 1;
        
        // 检查是否需要填写信息
        $needFillInfo = !$studentInfo || $studentInfo['info_completed'] == 0;
        
        // 定义允许的页面
        $allowedPages = [
            'change_password.php',  // 修改密码页面
            'fill_info.php',        // 填写信息页面
            'api/login.php',        // 登录API（如果有）
            'logout.php',           // 退出页面
            'change_password'       // API中的修改密码（无.php扩展名）
        ];
        
        // 判断是否是API请求（通常是ajax请求）
        $isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
        
        // 策略：先修改密码，再填写信息
        if ($needChangePassword && $currentPage !== 'change_password.php' && !$isApiRequest) {
            // 需要修改密码，重定向到修改密码页面
            header('Location: /pages/student/change_password.php');
            exit();
        } elseif (!$needChangePassword && $needFillInfo && $currentPage !== 'fill_info.php' && !$isApiRequest) {
            // 已经修改过密码，但需要填写信息，重定向到信息填写页面
            header('Location: /pages/student/fill_info.php');
            exit();
        }
        
        // // 如果已经填写过信息，但试图访问修改密码或填写信息页面，重定向到首页
        // if (!$needFillInfo && !$needChangePassword && 
        //     ($currentPage === 'change_password.php' || $currentPage === 'fill_info.php')) {
        //     header('Location: /pages/student/index.php');
        //     exit();
        // }
    }
}

// 检查登录状态
requireLogin();

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo Security::generateCSRFToken(); ?>">
    <title><?php echo e($pageTitle ?? '生化学院党员信息管理系统'); ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23c41e3a%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2240%22>党</text></svg>">
    <script>
        (function() {
            var theme = 'light';
            try {
                var storedTheme = localStorage.getItem('theme');
                if (storedTheme !== 'dark' && storedTheme !== 'light') {
                    var match = document.cookie.match(/(?:^|;\s*)theme=(dark|light)(?:;|$)/);
                    storedTheme = match ? match[1] : '';
                }
                if (storedTheme === 'dark' || storedTheme === 'light') {
                    theme = storedTheme;
                }
            } catch (error) {
                var cookieMatch = document.cookie.match(/(?:^|;\s*)theme=(dark|light)(?:;|$)/);
                if (cookieMatch) {
                    theme = cookieMatch[1];
                }
            }
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme === 'dark' ? 'dark' : 'light';
        })();
    </script>
    <link rel="stylesheet" href="<?php echo getVersionedAsset('/assets/css/style.css'); ?>">
    <!-- Font Awesome 使用 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <!-- Select2 CSS 使用本地 -->
    <link rel="stylesheet" href="<?php echo getVersionedAsset('/assets/css/select2.min.css'); ?>">
    <!-- jQuery 和 Select2 JS 使用本地 -->
    <script src="<?php echo getVersionedAsset('/assets/js/jquery.min.js'); ?>"></script>
    <script src="<?php echo getVersionedAsset('/assets/js/select2.min.js'); ?>"></script>
    <script src="<?php echo getVersionedAsset('/assets/js/select2.zh-CN.js'); ?>"></script>
    <!-- 主题切换和移动端菜单 -->
    <script src="<?php echo getVersionedAsset('/assets/js/theme.js'); ?>"></script>
    <?php if (isset($extraCss)): ?>
        <?php echo $extraCss; ?>
    <?php endif; ?>
</head>
<body class="app-shell role-<?php echo e($currentUser['role']); ?>" data-user-role="<?php echo e($currentUser['role']); ?>" data-remember-login="<?php echo !empty($_SESSION['remember_login']) ? '1' : '0'; ?>">
    <div class="main-layout">
        <!-- 侧边栏 -->
        <aside class="sidebar" aria-label="主导航">
            <div class="sidebar-header">
                <a href="https://shxy.bdu.edu.cn/" target="_blank" rel="noopener noreferrer" class="sidebar-brand">
                    <img src="/assets/images/logo.png" alt="Logo" class="sidebar-logo" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22white%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22%23c41e3a%22 font-size=%2240%22>党</text></svg>'">
                </a>
                <div class="sidebar-brand-copy">
                    <span class="sidebar-eyebrow">智慧党建平台</span>
                    <h2 class="sidebar-title">生化学院<br>党员信息管理系统</h2>
                </div>
            </div>
            <nav class="sidebar-nav">
                <?php if (isset($sidebarMenu) && is_array($sidebarMenu)): ?>
                    <?php 
                    $currentPage = $_SERVER['PHP_SELF'];
                    
                    // 检查是否需要强制修改密码
                    $forcePasswordChange = false;
                    if (isset($_SESSION['user_id'])) {
                        $db = Database::getInstance();
                        $userCheck = $db->fetchOne("SELECT force_change_password FROM users WHERE id = ?", [$_SESSION['user_id']]);
                        $forcePasswordChange = $userCheck && $userCheck['force_change_password'] == 1;
                    }
                    
                    // 新增：检查是否需要填写学生信息
                    $needFillStudentInfo = false;
                    if (isset($_SESSION['user_id'])) {
                        // 获取用户角色
                        $currentUser = getCurrentUser();  // 假设这个函数返回当前用户信息，包括角色
                        
                        // 只有学生角色需要检查
                        if ($currentUser && $currentUser['role'] === 'student') {
                            $studentInfo = $db->fetchOne("SELECT info_completed FROM student_info WHERE user_id = ?", [$_SESSION['user_id']]);
                            
                            // 如果学生信息不存在或者未完成(info_completed=0)
                            if (!$studentInfo || $studentInfo['info_completed'] == 0) {
                                $needFillStudentInfo = true;
                            }
                        }
                    }
                    
                    foreach ($sidebarMenu as $item): 
                        $isActive = strpos($currentPage, $item['url']) !== false ? 'active' : '';
                        $isChangePasswordPage = strpos($item['url'], 'change_password.php') !== false;
                        $isFillInfoPage = strpos($item['url'], 'fill_info.php') !== false;
                        
                        // 如果强制修改密码，只允许访问修改密码页面
                        if ($forcePasswordChange && !$isChangePasswordPage):
                    ?>
                        <a href="javascript:void(0)" class="nav-item disabled" onclick="Toast.warning('请先修改密码后才能访问其他功能')">
                            <i class="<?php echo $item['icon']; ?>"></i>
                            <span><?php echo $item['title']; ?></span>
                        </a>
                    <?php elseif ($needFillStudentInfo && !$isFillInfoPage): ?>
                        <!-- 新增：如果需要填写学生信息，只允许访问信息填写页面 -->
                        <a href="javascript:void(0)" class="nav-item disabled" onclick="Toast.warning('请先完善个人信息后才能访问其他功能')">
                            <i class="<?php echo $item['icon']; ?>"></i>
                            <span><?php echo $item['title']; ?></span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $item['url']; ?>" class="nav-item <?php echo $isActive; ?>">
                            <i class="<?php echo $item['icon']; ?>"></i>
                            <span class="nav-item-label"><?php echo $item['title']; ?></span>
                            <?php if (isset($item['badge_count'])): ?>
                                <span
                                    class="badge badge-<?php echo e($item['badge_class'] ?? 'warning'); ?> nav-item-badge <?php echo (int) $item['badge_count'] > 0 ? '' : 'd-none'; ?>"
                                    <?php if (!empty($item['badge_id'])): ?>id="<?php echo e($item['badge_id']); ?>"<?php endif; ?>
                                ><?php echo (int) $item['badge_count']; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </nav>
        </aside>
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
        
        <!-- 主内容区 -->
        <main class="main-content">
            <!-- 顶部栏 -->
            <header class="topbar">
                <!-- 移动端菜单按钮 -->
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="打开导航菜单">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-heading">
                    <span class="topbar-kicker">Party Affairs Digital Console</span>
                    <h1 class="topbar-title"><?php echo e($pageTitle ?? SITE_NAME); ?></h1>
                </div>
                <div class="topbar-actions">
                    <div class="user-info">
                        <i class="fa-solid fa-user-circle"></i>
                        <span><?php echo e($currentUser['name']); ?></span>
                        <span class="badge badge-primary">
                            <?php
                            $roleNames = ['student' => '学生', 'teacher' => '教师', 'admin' => '管理员', 'superadmin' => '系统管理员'];
                            echo $roleNames[$currentUser['role']] ?? '用户';
                            ?>
                        </span>
                    </div>
                    <button class="logout-btn" onclick="confirmLogout()">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>退出</span>
                    </button>
                </div>
            </header>
            
            <!-- 页面内容 -->
            <div class="page-content">

<script>
window.addEventListener('pageshow', function(event) {
    const navigation = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
    if (event.persisted || (navigation && navigation.type === 'back_forward')) {
        window.location.reload();
    }
});

(function restoreEphemeralLoginMarker() {
    const markerKey = 'shxy_ephemeral_login';
    const rememberLogin = document.body && document.body.dataset.rememberLogin === '1';
    let hasEphemeralMarker = true;

    try {
        hasEphemeralMarker = sessionStorage.getItem(markerKey) === '1';
    } catch (error) {
        hasEphemeralMarker = true;
    }

    if (rememberLogin || hasEphemeralMarker) {
        return;
    }

    try {
        sessionStorage.setItem(markerKey, '1');
    } catch (error) {
        // sessionStorage may be unavailable in some mobile webviews; the server session remains authoritative.
    }
})();

function bootAdminPendingBadgeUpdater() {
    if (window.__adminPendingBadgeUpdaterStarted) {
        return;
    }

    const body = document.body;
    const role = body ? body.dataset.userRole : '';
    const sidebarBadge = document.getElementById('sidebarPendingAuditBadge');
    if (!sidebarBadge || !['admin', 'superadmin'].includes(role)) {
        return;
    }

    window.__adminPendingBadgeUpdaterStarted = true;
    let lastCount = parseInt(sidebarBadge.textContent || '0', 10) || 0;
    let timer = null;

    function bounceBadge(element) {
        if (!element) {
            return;
        }

        element.classList.remove('badge-bounce');
        void element.offsetWidth;
        element.classList.add('badge-bounce');
        setTimeout(() => element.classList.remove('badge-bounce'), 650);
    }

    function applyCount(count) {
        sidebarBadge.textContent = count;
        sidebarBadge.classList.toggle('d-none', count <= 0);

        const pendingTabBadge = document.getElementById('pendingBadge');
        if (pendingTabBadge) {
            pendingTabBadge.textContent = count;
        }

        const pendingCount = document.getElementById('pendingCount');
        if (pendingCount) {
            pendingCount.textContent = count;
        }

        if (count > lastCount) {
            bounceBadge(sidebarBadge);
            if (pendingTabBadge) {
                bounceBadge(pendingTabBadge);
            }
            const pendingCard = document.getElementById('pendingCard');
            if (pendingCard) {
                pendingCard.classList.add('pulse-animation');
                setTimeout(() => pendingCard.classList.remove('pulse-animation'), 1000);
            }
        }

        lastCount = count;
    }

    async function refreshCount() {
        try {
            const response = await fetch('/api/admin/get_updates.php?_t=' + Date.now(), {
                method: 'GET',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                return;
            }

            const result = await response.json();
            if (!result.success || !result.data) {
                return;
            }

            applyCount(parseInt(result.data.pending_count || 0, 10) || 0);
            document.dispatchEvent(new CustomEvent('admin-pending-updated', {
                detail: result.data
            }));
        } catch (error) {
            // ignore polling errors
        }
    }

    function start() {
        if (timer) {
            return;
        }

        refreshCount();
        timer = setInterval(refreshCount, 5000);
    }

    function stop() {
        if (!timer) {
            return;
        }

        clearInterval(timer);
        timer = null;
    }

    start();

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    window.addEventListener('focus', refreshCount);
    window.addEventListener('pageshow', refreshCount);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAdminPendingBadgeUpdater, { once: true });
} else {
    bootAdminPendingBadgeUpdater();
}

// 退出登录确认 - 在header中定义以确保立即可用
async function confirmLogout() {
    if (confirm('确定要退出登录吗？')) {
        try {
            const currentTheme = window.SHXYTheme
                ? window.SHXYTheme.get()
                : (document.documentElement.getAttribute('data-theme') || localStorage.getItem('theme') || 'light');
            if (window.SHXYTheme) {
                window.SHXYTheme.persist(currentTheme);
            }
            sessionStorage.removeItem('shxy_ephemeral_login');
            const response = await Ajax.post('/api/logout.php', {});
            try {
                if (window.SHXYTheme) {
                    window.SHXYTheme.persist(currentTheme);
                } else {
                    localStorage.setItem('theme', currentTheme);
                    document.cookie = `theme=${currentTheme}; path=/; max-age=31536000; SameSite=Lax`;
                }
            } catch (error) {
                // ignore
            }
            if (response.success) {
                window.location.replace('/index.php');
            } else {
                window.location.replace('/index.php');
                // 如果返回错误，仍然尝试跳转到登录页
                window.location.replace('/index.php');
            }
        } catch (error) {
            // 发生错误时也跳转到登录页
            window.location.replace('/index.php');
        }
    }
}
</script>
