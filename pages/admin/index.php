<?php
/**
 * 管理员端数据展板 - 优化版 + 实时更新
 */
$pageTitle = '数据展板 - 生化学院党员信息管理系统';
$needChart = true;

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// 检查角色
requireRole(['admin', 'superadmin']);

$db = Database::getInstance();

// ===== 获取筛选参数 =====
$filterGrade = normalizeFilterValues($_GET['grade'] ?? []);
$filterClass = normalizeFilterValues($_GET['class'] ?? []);
$filterPolitical = normalizeFilterValues($_GET['political'] ?? []);
$filterGender = normalizeFilterValues($_GET['gender'] ?? []);

// 生成缓存键
$cacheKey = 'dashboard_data_' . md5(json_encode([$filterGrade, $filterClass, $filterPolitical, $filterGender]));
$cacheTime = 300; // 缓存5分钟

// 尝试从缓存获取数据
$cachedData = null;
if (function_exists('apcu_fetch')) {
    $cachedData = apcu_fetch($cacheKey);
}

if ($cachedData !== false && $cachedData !== null) {
    extract($cachedData);
} else {
    $whereConditions = ["info_completed = 1"];
    $params = [];

    if (!empty($filterGrade)) {
        appendMultiSelectFilter($whereConditions, $params, 'grade', $filterGrade);
    }
    if (!empty($filterClass)) {
        appendMultiSelectFilter($whereConditions, $params, 'class', $filterClass);
    }
    if (!empty($filterPolitical)) {
        appendPoliticalStatusFilter($whereConditions, $params, 'political_status', $filterPolitical);
    }
    if (!empty($filterGender)) {
        appendMultiSelectFilter($whereConditions, $params, 'gender', $filterGender);
    }

    $whereClause = implode(" AND ", $whereConditions);

    $statsQuery = "
        SELECT 
            COUNT(*) as total_students,
            COUNT(CASE WHEN political_status IN ('正式党员', '中共党员') THEN 1 END) as party_members,
            COUNT(CASE WHEN political_status IN ('预备党员', '中共预备党员') THEN 1 END) as probationary_members,
            COUNT(CASE WHEN political_status = '入党积极分子' THEN 1 END) as activists,
            COUNT(CASE WHEN gender = '男' THEN 1 END) as male_count,
            COUNT(CASE WHEN gender = '女' THEN 1 END) as female_count
        FROM student_info 
        WHERE $whereClause
    ";
    
    $stats = empty($params) ? 
        $db->fetchOne($statsQuery) : 
        $db->fetchOne($statsQuery, $params);

    $totalStudents = $stats['total_students'];
    $partyMembers = $stats['party_members'];
    $probationaryMembers = $stats['probationary_members'];
    $activists = $stats['activists'];

    $genderStats = [];
    if ($stats['male_count'] > 0) {
        $genderStats[] = ['gender' => '男', 'count' => $stats['male_count']];
    }
    if ($stats['female_count'] > 0) {
        $genderStats[] = ['gender' => '女', 'count' => $stats['female_count']];
    }

    $politicalStats = empty($params) ?
        $db->fetchAll("SELECT political_status, COUNT(*) as count FROM student_info WHERE $whereClause GROUP BY political_status ORDER BY count DESC") :
        $db->fetchAll("SELECT political_status, COUNT(*) as count FROM student_info WHERE $whereClause GROUP BY political_status ORDER BY count DESC", $params);

    $gradeStats = empty($params) ?
        $db->fetchAll("SELECT grade, COUNT(*) as count FROM student_info WHERE $whereClause GROUP BY grade ORDER BY grade") :
        $db->fetchAll("SELECT grade, COUNT(*) as count FROM student_info WHERE $whereClause GROUP BY grade ORDER BY grade", $params);

    $classStats = empty($params) ?
        $db->fetchAll("SELECT class, COUNT(*) as count FROM student_info WHERE $whereClause AND class IS NOT NULL GROUP BY class ORDER BY class") :
        $db->fetchAll("SELECT class, COUNT(*) as count FROM student_info WHERE $whereClause AND class IS NOT NULL GROUP BY class ORDER BY class", $params);

    $partyDevelopment = empty($params) ?
        $db->fetchAll("SELECT YEAR(probationary_date) as year, COUNT(*) as count FROM student_info WHERE $whereClause AND probationary_date IS NOT NULL GROUP BY YEAR(probationary_date) ORDER BY year") :
        $db->fetchAll("SELECT YEAR(probationary_date) as year, COUNT(*) as count FROM student_info WHERE $whereClause AND probationary_date IS NOT NULL GROUP BY YEAR(probationary_date) ORDER BY year", $params);

    $ethnicityStats = empty($params) ?
        $db->fetchAll("SELECT ethnicity, COUNT(*) as count FROM student_info WHERE $whereClause GROUP BY ethnicity ORDER BY count DESC LIMIT 10") :
        $db->fetchAll("SELECT ethnicity, COUNT(*) as count FROM student_info WHERE $whereClause GROUP BY ethnicity ORDER BY count DESC LIMIT 10", $params);

    $politicalLabels = array_column($politicalStats, 'political_status');
    $politicalData = array_column($politicalStats, 'count');
    $gradeLabels = array_column($gradeStats, 'grade');
    $gradeData = array_column($gradeStats, 'count');
    $genderLabels = array_column($genderStats, 'gender');
    $genderData = array_column($genderStats, 'count');
    $classLabels = array_column($classStats, 'class');
    $classData = array_column($classStats, 'count');
    $developmentLabels = array_column($partyDevelopment, 'year');
    $developmentData = array_column($partyDevelopment, 'count');
    $ethnicityLabels = array_column($ethnicityStats, 'ethnicity');
    $ethnicityData = array_column($ethnicityStats, 'count');

    $dataToCache = compact(
        'totalStudents', 'partyMembers', 'probationaryMembers', 'activists',
        'politicalStats', 'gradeStats', 'genderStats', 'classStats', 
        'partyDevelopment', 'ethnicityStats',
        'politicalLabels', 'politicalData', 'gradeLabels', 'gradeData',
        'genderLabels', 'genderData', 'classLabels', 'classData',
        'developmentLabels', 'developmentData', 'ethnicityLabels', 'ethnicityData'
    );
    
    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, $dataToCache, $cacheTime);
    }
}

$totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'];
$pendingCount = $db->fetchOne("SELECT COUNT(DISTINCT batch_id) as count FROM info_change_requests WHERE status = 'pending'")['count'];
$graduatedCount = $db->fetchOne("SELECT COUNT(*) as count FROM graduated_students")['count'];

// 获取各角色用户数（仅系统管理员）
$roleStats = [];
if (isSuperAdmin()) {
    $roleStats = [
        'student' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1")['count'],
        'teacher' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND is_active = 1")['count'],
        'admin' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1")['count'],
        'superadmin' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'superadmin' AND is_active = 1")['count']
    ];
}

$allGrades = $db->fetchAll("SELECT DISTINCT grade FROM student_info WHERE info_completed = 1 AND grade IS NOT NULL ORDER BY grade");
$allClasses = $db->fetchAll("SELECT DISTINCT class FROM student_info WHERE info_completed = 1 AND class IS NOT NULL ORDER BY class");
$allPoliticalStatus = $db->fetchAll("SELECT DISTINCT political_status FROM student_info WHERE info_completed = 1 AND political_status IS NOT NULL ORDER BY political_status");
?>

<!--<script src="/assets/js/chart.min.js"></script>-->
<script src="/assets/js/realtime.js"></script>

<div class="dashboard">
    <?php renderFeedbackTipAlert(); ?>
    
    <div class="filter-section card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-filter"></i> 数据筛选</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="grade">年级：</label>
                        <select name="grade[]" id="grade" class="form-control filter-multi-select" multiple data-placeholder="全部年级">
                            <?php foreach ($allGrades as $grade): ?>
                                <option value="<?php echo htmlspecialchars($grade['grade']); ?>" 
                                    <?php echo in_array($grade['grade'], $filterGrade, true) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label for="class">班级：</label>
                        <select name="class[]" id="class" class="form-control filter-multi-select" multiple data-placeholder="全部班级">
                            <?php foreach ($allClasses as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['class']); ?>" 
                                    <?php echo in_array($class['class'], $filterClass, true) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label for="political">政治面貌：</label>
                        <select name="political[]" id="political" class="form-control filter-multi-select" multiple data-placeholder="全部">
                            <?php foreach ($allPoliticalStatus as $status): ?>
                                <option value="<?php echo htmlspecialchars($status['political_status']); ?>" 
                                    <?php echo in_array($status['political_status'], $filterPolitical, true) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status['political_status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label for="gender">性别：</label>
                        <select name="gender[]" id="gender" class="form-control filter-multi-select" multiple data-placeholder="全部">
                            <option value="男" <?php echo in_array('男', $filterGender, true) ? 'selected' : ''; ?>>男</option>
                            <option value="女" <?php echo in_array('女', $filterGender, true) ? 'selected' : ''; ?>>女</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary" id="filterBtn">
                            <i class="fa-solid fa-search"></i> 筛选
                        </button>
                        <a href="?" class="btn btn-secondary">
                            <i class="fa-solid fa-rotate-right"></i> 重置
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div id="loadingIndicator" style="display: none;">
        <div class="loading-spinner">
            <i class="fa-solid fa-spinner fa-spin"></i> 正在加载数据...
        </div>
    </div>
    
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="totalStudents"><?php echo $totalStudents; ?></div>
                <div class="stat-label">学生总数</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #d4475c 0%, #c41e3a 100%);">
                <i class="fa-solid fa-flag"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $partyMembers; ?></div>
                <div class="stat-label">正式党员</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #d4a756 0%, #b45309 100%);">
                <i class="fa-solid fa-user-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $probationaryMembers; ?></div>
                <div class="stat-label">预备党员</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);">
                <i class="fa-solid fa-star"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $activists; ?></div>
                <div class="stat-label">入党积极分子</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-highlight" id="pendingCard">
            <div class="stat-icon" style="background: linear-gradient(135deg, #e07a5f 0%, #c41e3a 100%);">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="pendingCount"><?php echo $pendingCount; ?></div>
                <div class="stat-label">待审核申请</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #a63d40 0%, #6d071a 100%);">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="totalUsers"><?php echo $totalUsers; ?></div>
                <div class="stat-label">系统用户</div>
            </div>
        </div>
    </div>
    
    <?php if (isSuperAdmin()): ?>
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-users-cog"></i> 用户角色统计</h3>
        </div>
        <div class="card-body">
            <div class="role-stats-grid">
                <div class="role-stat-item">
                    <div class="role-stat-icon" style="background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%);">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <div class="role-stat-info">
                        <div class="role-stat-value"><?php echo $roleStats['student']; ?></div>
                        <div class="role-stat-label">学生账户</div>
                    </div>
                </div>
                
                <div class="role-stat-item">
                    <div class="role-stat-icon" style="background: linear-gradient(135deg, #d4475c 0%, #c41e3a 100%);">
                        <i class="fa-solid fa-chalkboard-teacher"></i>
                    </div>
                    <div class="role-stat-info">
                        <div class="role-stat-value"><?php echo $roleStats['teacher']; ?></div>
                        <div class="role-stat-label">教师账户</div>
                    </div>
                </div>
                
                <div class="role-stat-item">
                    <div class="role-stat-icon" style="background: linear-gradient(135deg, #d4a756 0%, #b45309 100%);">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div class="role-stat-info">
                        <div class="role-stat-value"><?php echo $roleStats['admin']; ?></div>
                        <div class="role-stat-label">管理员账户</div>
                    </div>
                </div>
                
                <div class="role-stat-item">
                    <div class="role-stat-icon" style="background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);">
                        <i class="fa-solid fa-user-cog"></i>
                    </div>
                    <div class="role-stat-info">
                        <div class="role-stat-value"><?php echo $roleStats['superadmin']; ?></div>
                        <div class="role-stat-label">系统管理员</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div id="chartLoadingTip" class="alert alert-info" style="text-align: center; margin-bottom: 20px;">
        <i class="fa-solid fa-chart-line fa-spin"></i> 
        <span>正在加载图表数据...</span>
    </div>
    <div class="charts-grid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-chart-pie"></i> 政治面貌分布</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="politicalChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-chart-bar"></i> 年级分布</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-users-rectangle"></i> 班级分布</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="classChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-venus-mars"></i> 性别分布</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-globe"></i> 民族分布（前10）</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="ethnicityChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-chart-line"></i> 党员发展趋势</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="developmentChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard { padding: 0; }
.loading-spinner { text-align: center; padding: 40px; font-size: 18px; color: #c41e3a; }
.filter-section { margin-bottom: 24px; }
.filter-form { padding: 0; }
.filter-row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
.filter-item { flex: 1; min-width: 180px; }
.filter-item label { display: block; margin-bottom: 6px; font-weight: 500; color: #333; font-size: 14px; }
.filter-item .form-control { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; transition: border-color 0.2s; }
.filter-item .form-control:focus { outline: none; border-color: #c41e3a; box-shadow: 0 0 0 3px rgba(196, 30, 58, 0.12); }
.filter-actions { display: flex; gap: 10px; }
.filter-actions .btn { padding: 8px 20px; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
.btn-primary { background: linear-gradient(135deg, #c41e3a 0%, #9e1830 100%); color: #fff; }
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(196, 30, 58, 0.35); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #f5f5f5; color: #666; }
.btn-secondary:hover { background: #e8e8e8; }
.stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
.stat-card { background: #fff; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
.stat-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff; }
.stat-info { flex: 1; }
.stat-value { font-size: 28px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.stat-label { font-size: 14px; color: #666; margin-top: 4px; }
.charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
@media (max-width: 1200px) { 
    .charts-grid { grid-template-columns: 1fr; }
    .filter-row { flex-direction: column; }
    .filter-item { width: 100%; }
}
.chart-container { position: relative; height: 280px; }

/* 实时更新样式 */
.counter-update {
    animation: counterPulse 0.6s;
}
@keyframes counterPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); color: #c41e3a; }
}
.pulse-animation {
    animation: cardPulse 1s;
}
@keyframes cardPulse {
    0%, 100% { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    50% { box-shadow: 0 0 20px rgba(196, 30, 58, 0.45); }
}

/* 角色统计样式 */
.role-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}
.role-stat-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    transition: all 0.2s;
}
.role-stat-item:hover {
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}
.role-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #fff;
}
.role-stat-info {
    flex: 1;
}
.role-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #1a1a2e;
    line-height: 1.2;
}
.role-stat-label {
    font-size: 13px;
    color: #666;
    margin-top: 4px;
}
/* 图表加载骨架屏 */
.chart-container {
    position: relative;
    height: 280px;
    background: #f8f9fa;
    border-radius: 8px;
}

.chart-container canvas {
    position: relative;
    z-index: 1;
}

.chart-container::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 40px;
    height: 40px;
    border: 3px solid #e0e0e0;
    border-top-color: #c41e3a;
    border-radius: 50%;
    animation: chartLoading 0.8s linear infinite;
    z-index: 0;
}

@keyframes chartLoading {
    to { transform: translate(-50%, -50%) rotate(360deg); }
}

/* 图表加载完成后隐藏加载动画 */
.chart-loaded::before {
    display: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.filter-multi-select').select2({
        width: '100%',
        language: 'zh-CN',
        allowClear: true,
        closeOnSelect: false
    });

    const colors = ['#c41e3a', '#9e1830', '#d4475c', '#b91c1c', '#d4a756', '#b45309', '#e07a5f', '#a63d40', '#7f1d1d', '#d97706', '#be123c', '#881337'];
    const bgColors = colors.map(c => c + 'dd');
    
    // 通用图表配置
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 800, // 减少动画时间
            easing: 'easeOutQuart'
        }
    };
    
    // 延迟加载函数
    function loadChartWithDelay(chartId, config, delay) {
        setTimeout(() => {
            const canvas = document.getElementById(chartId);
            if (canvas) {
                new Chart(canvas, config);
                canvas.parentElement.classList.add('chart-loaded'); // 添加这一行
            }
        }, delay);
    }
    
    // 立即加载前3个图表
    loadChartWithDelay('politicalChart', {
        type: 'doughnut',
        data: { 
            labels: <?php echo json_encode($politicalLabels); ?>, 
            datasets: [{ 
                data: <?php echo json_encode($politicalData); ?>, 
                backgroundColor: bgColors, 
                borderWidth: 2, 
                borderColor: '#fff' 
            }] 
        },
        options: { 
            ...commonOptions,
            plugins: { 
                legend: { 
                    position: 'right', 
                    labels: { padding: 16, usePointStyle: true } 
                } 
            } 
        }
    }, 0);
    
    loadChartWithDelay('gradeChart', {
        type: 'bar',
        data: { 
            labels: <?php echo json_encode($gradeLabels); ?>, 
            datasets: [{ 
                label: '人数', 
                data: <?php echo json_encode($gradeData); ?>, 
                backgroundColor: '#c41e3a', 
                borderRadius: 6 
            }] 
        },
        options: { 
            ...commonOptions,
            plugins: { legend: { display: false } }, 
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } 
        }
    }, 100);
    
    loadChartWithDelay('genderChart', {
        type: 'pie',
        data: { 
            labels: <?php echo json_encode($genderLabels); ?>, 
            datasets: [{ 
                data: <?php echo json_encode($genderData); ?>, 
                backgroundColor: ['#c41e3a', '#d4a756'], 
                borderWidth: 2, 
                borderColor: '#fff' 
            }] 
        },
        options: { 
            ...commonOptions,
            plugins: { 
                legend: { 
                    position: 'bottom', 
                    labels: { padding: 20, usePointStyle: true } 
                } 
            } 
        }
    }, 200);
    
    // 延迟加载后3个图表（视口可见时加载）
    const lazyCharts = [
        {
            id: 'classChart',
            config: {
                type: 'bar',
                data: { 
                    labels: <?php echo json_encode($classLabels); ?>, 
                    datasets: [{ 
                        label: '人数', 
                        data: <?php echo json_encode($classData); ?>, 
                        backgroundColor: bgColors, 
                        borderRadius: 6 
                    }] 
                },
                options: { 
                    ...commonOptions,
                    plugins: { legend: { display: false } }, 
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } 
                }
            }
        },
        {
            id: 'ethnicityChart',
            config: {
                type: 'bar',
                data: { 
                    labels: <?php echo json_encode($ethnicityLabels); ?>, 
                    datasets: [{ 
                        label: '人数', 
                        data: <?php echo json_encode($ethnicityData); ?>, 
                        backgroundColor: bgColors, 
                        borderRadius: 6 
                    }] 
                },
                options: { 
                    ...commonOptions,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } }, 
                    scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } 
                }
            }
        },
        {
            id: 'developmentChart',
            config: {
                type: 'line',
                data: { 
                    labels: <?php echo json_encode($developmentLabels); ?>, 
                    datasets: [{ 
                        label: '入党人数', 
                        data: <?php echo json_encode($developmentData); ?>, 
                        borderColor: '#c41e3a', 
                        backgroundColor: 'rgba(196, 30, 58, 0.12)', 
                        fill: true, 
                        tension: 0.4, 
                        pointRadius: 6, 
                        pointBackgroundColor: '#c41e3a', 
                        pointBorderColor: '#fff', 
                        pointBorderWidth: 2 
                    }] 
                },
                options: { 
                    ...commonOptions,
                    plugins: { legend: { display: false } }, 
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } 
                }
            }
        }
    ];
    
    // 使用 Intersection Observer 实现懒加载
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const chartData = lazyCharts.find(c => c.id === entry.target.id);
                    if (chartData) {
                        new Chart(entry.target, chartData.config);
                        entry.target.parentElement.classList.add('chart-loaded'); // 添加这一行
                        observer.unobserve(entry.target);
                    }
                }
            });
        }, { rootMargin: '50px' });
        
        lazyCharts.forEach(chart => {
            const canvas = document.getElementById(chart.id);
            if (canvas) observer.observe(canvas);
        });
    } else {
        // 降级方案：延迟加载
        lazyCharts.forEach((chart, index) => {
            setTimeout(() => {
                const canvas = document.getElementById(chart.id);
                if (canvas) {
                    new Chart(canvas, chart.config);
                    canvas.parentElement.classList.add('chart-loaded'); // 添加这一行
                }
            }, 300 + (index * 150));
        });
    }
    
    // 筛选表单优化
    const filterForm = document.getElementById('filterForm');
    const filterBtn = document.getElementById('filterBtn');
    const loadingIndicator = document.getElementById('loadingIndicator');
    
    if (filterForm && filterBtn) {
        filterForm.addEventListener('submit', function() {
            filterBtn.disabled = true;
            filterBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 加载中...';
            if (loadingIndicator) loadingIndicator.style.display = 'block';
        });
    }
    
    // 初始化实时更新
    if (typeof RealtimeUpdater !== 'undefined') {
        const updater = new RealtimeUpdater({
            interval: 5000,
            onUpdate: function(data) {
                updateCounter('pendingCount', data.pending_count);
                updateCounter('totalUsers', data.total_users);
                updateCounter('totalStudents', data.total_students);
            },
            onNewPending: function(data) {
                pulseElement('pendingCard');
            }
        });
        
        updater.requestNotificationPermission();
        updater.start();
        
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                updater.stop();
            } else {
                updater.start();
            }
        });
    }
});

// 所有图表加载完成后隐藏提示
setTimeout(() => {
    const tip = document.getElementById('chartLoadingTip');
    if (tip) {
        tip.style.transition = 'opacity 0.3s';
        tip.style.opacity = '0';
        setTimeout(() => tip.remove(), 300);
    }
}, 1500);

// 辅助函数
function updateCounter(id, value) {
    const el = document.getElementById(id);
    if (el && el.textContent != value) {
        el.textContent = value;
        el.classList.add('counter-update');
        setTimeout(() => el.classList.remove('counter-update'), 600);
    }
}

function pulseElement(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('pulse-animation');
        setTimeout(() => el.classList.remove('pulse-animation'), 1000);
    }
}


</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
