<?php
/**
 * 管理员端毕业生管理
 */
$pageTitle = '毕业生管理 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'superadmin']);

$db = Database::getInstance();

// 获取筛选参数
$filters = [
    'keyword' => $_GET['keyword'] ?? '',
    'graduation_year' => normalizeFilterValues($_GET['graduation_year'] ?? []),
    'political_status' => normalizeFilterValues($_GET['political_status'] ?? [])
];

// 构建查询
$where = ['1=1'];
$params = [];

if (!empty($filters['keyword'])) {
    $where[] = "(student_no LIKE ? OR name LIKE ?)";
    $params[] = '%' . $filters['keyword'] . '%';
    $params[] = '%' . $filters['keyword'] . '%';
}

if (!empty($filters['graduation_year'])) {
    appendMultiSelectFilter($where, $params, 'graduation_year', $filters['graduation_year']);
}

if (!empty($filters['political_status'])) {
    appendPoliticalStatusFilter($where, $params, 'political_status', $filters['political_status']);
}

$whereClause = implode(' AND ', $where);

// 分页
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

$total = $db->fetchOne("SELECT COUNT(*) as count FROM graduated_students WHERE $whereClause", $params)['count'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

// 查询数据
$graduates = $db->fetchAll("
    SELECT * FROM graduated_students 
    WHERE $whereClause 
    ORDER BY graduation_year DESC, student_no 
    LIMIT $perPage OFFSET $offset
", $params);

// 获取毕业年份选项
$graduationYears = $db->fetchAll("
    SELECT DISTINCT graduation_year 
    FROM graduated_students 
    ORDER BY graduation_year DESC
");

$politicalStatuses = getSystemOptions('political_status');

// 政治面貌徽章样式
function getPoliticalBadgeClass($status) {
    $map = [
        '正式党员' => 'danger',
        '预备党员' => 'warning',
        '入党积极分子' => 'info',
        '共青团员' => 'success',
        '群众' => 'secondary'
    ];
    return $map[$status] ?? 'secondary';
}
?>

<div class="card">
    <?php renderFeedbackTipAlert(); ?>
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-graduation-cap"></i> 毕业生档案</h3>
        <div class="card-actions">
            <button class="btn btn-info btn-sm" onclick="exportSelected()">
                <i class="fa-solid fa-check-square"></i> 导出选中
            </button>
            <button class="btn btn-success btn-sm" onclick="exportGraduated()">
                <i class="fa-solid fa-file-excel"></i> 导出全部
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- 筛选表单 -->
        <form class="filter-form" method="get" id="filterForm">
            <div class="filter-row">
                <div class="filter-item">
                    <input type="text" class="form-control" name="keyword" 
                           placeholder="学号/姓名" value="<?php echo e($filters['keyword']); ?>">
                </div>
                <div class="filter-item">
                    <select class="form-control select2 filter-multi-select" name="graduation_year[]" multiple data-placeholder="全部毕业年份">
                        <?php foreach ($graduationYears as $year): ?>
                            <option value="<?php echo e($year['graduation_year']); ?>" 
                                    <?php echo in_array((string) $year['graduation_year'], $filters['graduation_year'], true) ? 'selected' : ''; ?>>
                                <?php echo e($year['graduation_year']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <select class="form-control select2 filter-multi-select" name="political_status[]" multiple data-placeholder="全部政治面貌">
                        <?php foreach ($politicalStatuses as $status): ?>
                            <option value="<?php echo e($status); ?>" 
                                    <?php echo in_array($status, $filters['political_status'], true) ? 'selected' : ''; ?>>
                                <?php echo e($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-search"></i> 查询
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilter()">
                        <i class="fa-solid fa-undo"></i> 重置
                    </button>
                </div>
            </div>
        </form>
        
        <!-- 统计信息 -->
        <div class="result-info">
            共找到 <strong><?php echo $total; ?></strong> 条毕业生记录
        </div>
        
        <!-- 数据表格 -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="checkAll" onchange="toggleAll()"></th>
                        <th>学号</th>
                        <th>姓名</th>
                        <th>性别</th>
                        <th>原班级</th>
                        <th>毕业年份</th>
                        <th>政治面貌</th>
                        <th>联系方式</th>
                        <th>归档时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($graduates)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">暂无毕业生记录</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($graduates as $graduate): ?>
                            <tr>
                                <td><input type="checkbox" class="graduate-check" value="<?php echo $graduate['id']; ?>"></td>
                                <td><?php echo e($graduate['student_no']); ?></td>
                                <td><?php echo e($graduate['name']); ?></td>
                                <td><?php echo e($graduate['gender']); ?></td>
                                <td><?php echo e($graduate['grade']); ?> <?php echo e($graduate['class']); ?></td>
                                <td><?php echo e($graduate['graduation_year']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo getPoliticalBadgeClass($graduate['political_status']); ?>">
                                        <?php echo e($graduate['political_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($graduate['phone']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($graduate['graduated_at'])); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="viewGraduateDetail(<?php echo $graduate['id']; ?>)">
                                        <i class="fa-solid fa-eye"></i> 详情
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrapper">
                <?php echo generatePagination($page, $totalPages, '?' . http_build_query(array_filter($filters)) . '&page='); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 详情模态框 -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-graduation-cap"></i> 毕业生详细信息</h3>
            <button class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailContent">
            <div class="loading-spinner">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDetailModal()">关闭</button>
        </div>
    </div>
</div>

<style>
.filter-form {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.filter-item {
    min-width: 150px;
}

.filter-actions {
    display: flex;
    gap: 8px;
}

.result-info {
    padding: 12px 0;
    color: #666;
    border-bottom: 1px solid #eee;
    margin-bottom: 16px;
}

.loading-spinner {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 200px;
    color: #c41e3a;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.detail-item {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

.detail-item.full-width {
    grid-column: span 2;
}

.detail-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
}

.detail-value {
    font-size: 15px;
    color: #1a1a2e;
    font-weight: 500;
}

.card-actions {
    display: flex;
    gap: 8px;
}

@media (max-width: 768px) {
    .filter-item {
        min-width: 100%;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-item.full-width {
        grid-column: span 1;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.select2').select2({
        width: '100%',
        language: 'zh-CN',
        allowClear: true,
        closeOnSelect: false
    });
});

function resetFilter() {
    window.location.href = '/pages/admin/graduated.php';
}

async function viewGraduateDetail(graduateId) {
    const modal = document.getElementById('detailModal');
    const content = document.getElementById('detailContent');
    
    modal.classList.add('active');
    content.innerHTML = '<div class="loading-spinner"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';
    
    try {
        const response = await Ajax.get('/api/teacher/graduated_detail.php?id=' + graduateId);
        
        if (response.success) {
            const s = response.data;
            content.innerHTML = `
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">学号</div>
                        <div class="detail-value">${s.student_no}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">姓名</div>
                        <div class="detail-value">${s.name}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">性别</div>
                        <div class="detail-value">${s.gender}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">民族</div>
                        <div class="detail-value">${s.ethnicity}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">身份证号</div>
                        <div class="detail-value">${s.id_card}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">出生日期</div>
                        <div class="detail-value">${s.birth_date || '-'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">联系方式</div>
                        <div class="detail-value">${s.phone}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">邮箱</div>
                        <div class="detail-value">${s.email}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">学院</div>
                        <div class="detail-value">${s.college}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">原年级</div>
                        <div class="detail-value">${s.grade}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">原班级</div>
                        <div class="detail-value">${s.class}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">毕业年份</div>
                        <div class="detail-value">${s.graduation_year}</div>
                    </div>
                    <div class="detail-item full-width">
                        <div class="detail-label">家庭住址</div>
                        <div class="detail-value">${s.address}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">政治面貌</div>
                        <div class="detail-value">${s.political_status}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">归档时间</div>
                        <div class="detail-value">${s.graduated_at}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">入团时间</div>
                        <div class="detail-value">${s.join_league_date || '-'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">递交入党申请书时间</div>
                        <div class="detail-value">${s.apply_party_date || '-'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">确定积极分子时间</div>
                        <div class="detail-value">${s.activist_date || '-'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">确定预备党员时间</div>
                        <div class="detail-value">${s.probationary_date || '-'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">转正时间</div>
                        <div class="detail-value">${s.full_member_date || '-'}</div>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = '<div class="text-center text-danger">加载失败：' + response.message + '</div>';
        }
    } catch (error) {
        content.innerHTML = '<div class="text-center text-danger">网络错误，请稍后重试</div>';
    }
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
}

function toggleAll() {
    const checkAll = document.getElementById('checkAll');
    document.querySelectorAll('.graduate-check').forEach(cb => cb.checked = checkAll.checked);
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.graduate-check:checked')).map(cb => cb.value);
}

function exportSelected() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        Toast.warning('请先选择要导出的毕业生');
        return;
    }
    window.location.href = '/api/teacher/export_graduated.php?ids=' + ids.join(',');
}

function exportGraduated() {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    window.location.href = '/api/teacher/export_graduated.php?' + params.toString();
}

// 点击背景关闭模态框
document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetailModal();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
