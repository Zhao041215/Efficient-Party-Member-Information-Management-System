<?php
/**
 * 教师端学生信息查询
 */
$pageTitle = '学生信息 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// 检查角色
requireRole('teacher');

$db = Database::getInstance();

// 获取筛选参数
$filters = [
    'keyword' => $_GET['keyword'] ?? '',
    'grade' => normalizeFilterValues($_GET['grade'] ?? []),
    'class' => normalizeFilterValues($_GET['class'] ?? []),
    'political_status' => normalizeFilterValues($_GET['political_status'] ?? []),
    'gender' => normalizeFilterValues($_GET['gender'] ?? [])
];

// 构建查询
$where = ['si.info_completed = 1'];
$params = [];

if (!empty($filters['keyword'])) {
    $where[] = "(si.student_no LIKE ? OR si.name LIKE ?)";
    $params[] = '%' . $filters['keyword'] . '%';
    $params[] = '%' . $filters['keyword'] . '%';
}

if (!empty($filters['grade'])) {
    appendMultiSelectFilter($where, $params, 'si.grade', $filters['grade']);
}

if (!empty($filters['class'])) {
    appendMultiSelectFilter($where, $params, 'si.class', $filters['class']);
}

if (!empty($filters['political_status'])) {
    appendPoliticalStatusFilter($where, $params, 'si.political_status', $filters['political_status']);
}

if (!empty($filters['gender'])) {
    appendMultiSelectFilter($where, $params, 'si.gender', $filters['gender']);
}

$whereClause = implode(' AND ', $where);

// 分页
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

$total = $db->fetchOne("SELECT COUNT(*) as count FROM student_info si WHERE $whereClause", $params)['count'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

// 查询数据
$students = $db->fetchAll("
    SELECT si.* 
    FROM student_info si 
    WHERE $whereClause 
    ORDER BY si.grade, si.class, si.student_no 
    LIMIT $perPage OFFSET $offset
", $params);

// 获取筛选选项
$grades = getSystemOptions('grade');
$classes = getSystemOptions('class');
$politicalStatuses = getSystemOptions('political_status');

// 政治面貌徽章样式
function getPoliticalBadgeClass($status) {
    $map = [
        '中共党员' => 'danger',
        '中共预备党员' => 'warning',
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
        <h3 class="card-title"><i class="fa-solid fa-users"></i> 学生信息查询</h3>
        <div class="card-actions">
            <button class="btn btn-success btn-sm" onclick="exportExcel()">
                <i class="fa-solid fa-file-excel"></i> 导出全部
            </button>
            <button class="btn btn-primary btn-sm" onclick="exportSelected()" id="exportSelectedBtn" style="display: none;">
                <i class="fa-solid fa-file-export"></i> 导出选中 (<span id="selectedCount">0</span>)
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
                    <select class="form-control select2 filter-multi-select" name="grade[]" multiple data-placeholder="全部年级">
                        <?php foreach ($grades as $grade): ?>
                            <option value="<?php echo e($grade); ?>" <?php echo in_array($grade, $filters['grade'], true) ? 'selected' : ''; ?>>
                                <?php echo e($grade); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <select class="form-control select2 filter-multi-select" name="class[]" multiple data-placeholder="全部班级">
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo e($class); ?>" <?php echo in_array($class, $filters['class'], true) ? 'selected' : ''; ?>>
                                <?php echo e($class); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <select class="form-control select2 filter-multi-select" name="political_status[]" multiple data-placeholder="全部政治面貌">
                        <?php foreach ($politicalStatuses as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo in_array($status, $filters['political_status'], true) ? 'selected' : ''; ?>>
                                <?php echo e($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <select class="form-control select2 filter-multi-select" name="gender[]" multiple data-placeholder="全部性别">
                        <option value="男" <?php echo in_array('男', $filters['gender'], true) ? 'selected' : ''; ?>>男</option>
                        <option value="女" <?php echo in_array('女', $filters['gender'], true) ? 'selected' : ''; ?>>女</option>
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
            共找到 <strong><?php echo $total; ?></strong> 条记录
        </div>
        
        <!-- 数据表格 -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        </th>
                        <th>学号</th>
                        <th>姓名</th>
                        <th>性别</th>
                        <th>年级</th>
                        <th>班级</th>
                        <th>政治面貌</th>
                        <th>民族</th>
                        <th>联系方式</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">暂无数据</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="student-checkbox" value="<?php echo $student['id']; ?>" onchange="updateSelectedCount()">
                                </td>
                                <td><?php echo e($student['student_no']); ?></td>
                                <td><?php echo e($student['name']); ?></td>
                                <td><?php echo e($student['gender']); ?></td>
                                <td><?php echo e($student['grade']); ?></td>
                                <td><?php echo e($student['class']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo getPoliticalBadgeClass($student['political_status']); ?>">
                                        <?php echo e($student['political_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($student['ethnicity']); ?></td>
                                <td><?php echo e($student['phone']); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="viewDetail(<?php echo $student['id']; ?>)">
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
            <h3 class="modal-title"><i class="fa-solid fa-user"></i> 学生详细信息</h3>
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
    window.location.href = '/pages/teacher/student_list.php';
}

async function viewDetail(studentId) {
    const modal = document.getElementById('detailModal');
    const content = document.getElementById('detailContent');
    
    modal.classList.add('active');
    content.innerHTML = '<div class="loading-spinner"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';
    
    try {
        const response = await Ajax.get('/api/teacher/student_detail.php?id=' + studentId);
        
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
                        <div class="detail-label">年龄</div>
                        <div class="detail-value">${s.age || '-'}岁</div>
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
                        <div class="detail-label">年级</div>
                        <div class="detail-value">${s.grade}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">班级</div>
                        <div class="detail-value">${s.class}</div>
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
                        <div class="detail-label">预计毕业时间</div>
                        <div class="detail-value">${s.graduation_year}</div>
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

function exportExcel() {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    window.location.href = '/api/teacher/export_students.php?' + params.toString();
}

// 全选/取消全选
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedCount();
}

// 更新选中数量
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count;
    
    const exportBtn = document.getElementById('exportSelectedBtn');
    if (count > 0) {
        exportBtn.style.display = 'inline-block';
    } else {
        exportBtn.style.display = 'none';
    }
    
    // 更新全选复选框状态
    const allCheckboxes = document.querySelectorAll('.student-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    if (allCheckboxes.length > 0) {
        selectAllCheckbox.checked = count === allCheckboxes.length;
        selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
    }
}

// 导出选中的学生
async function exportSelected() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    const studentIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    if (studentIds.length === 0) {
        alert('请选择要导出的学生');
        return;
    }
    
    if (!confirm(`确定要导出选中的 ${studentIds.length} 名学生的信息吗？`)) {
        return;
    }
    
    const exportBtn = document.getElementById('exportSelectedBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.disabled = true;
    exportBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 导出中...';
    
    try {
        const response = await fetch('/api/teacher/export_students_batch.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                student_ids: studentIds
            })
        });
        
        if (!response.ok) {
            throw new Error('网络响应错误');
        }
        
        const result = await response.json();
        
        if (result.success) {
            if (typeof Notify !== 'undefined' && Notify.success) {
                Notify.success(result.message || '导出成功');
            } else {
                alert(result.message || '导出成功');
            }
            // 下载文件
            if (result.download_url) {
                window.location.href = result.download_url;
            }
        } else {
            if (typeof Notify !== 'undefined' && Notify.error) {
                Notify.error(result.message || '导出失败');
            } else {
                alert(result.message || '导出失败');
            }
        }
    } catch (error) {
        if (typeof Notify !== 'undefined' && Notify.error) {
            Notify.error('导出失败，请稍后重试');
        } else {
            alert('导出失败，请稍后重试');
        }
    } finally {
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalText;
    }
}

// 点击背景关闭模态框
document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetailModal();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
