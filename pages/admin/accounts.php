<?php
/**
 * 管理员端账户管理
 */
$pageTitle = '账户管理 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'superadmin']);

$db = Database::getInstance();

// 获取筛选参数
$filters = [
    'keyword' => $_GET['keyword'] ?? '',
    'role' => normalizeFilterValues($_GET['role'] ?? []),
    'status' => normalizeFilterValues($_GET['status'] ?? []),
    'grade' => normalizeFilterValues($_GET['grade'] ?? []),
    'info_status' => normalizeFilterValues($_GET['info_status'] ?? []),
];

// 构建查询条件
$where = ['1=1'];
$params = [];

if (!empty($filters['keyword'])) {
    $where[] = "(u.username LIKE ? OR u.name LIKE ?)";
    $params[] = '%' . $filters['keyword'] . '%';
    $params[] = '%' . $filters['keyword'] . '%';
}

if (!empty($filters['role'])) {
    appendMultiSelectFilter($where, $params, 'u.role', $filters['role']);
}

if (count($filters['status']) === 1) {
    $statusValue = $filters['status'][0] === 'active' ? 1 : 0;
    $where[] = "u.is_active = ?";
    $params[] = $statusValue;
}

if (!empty($filters['grade'])) {
    appendMultiSelectFilter($where, $params, 'si.grade', $filters['grade']);
}

if (count($filters['info_status']) === 1) {
    if ($filters['info_status'][0] === 'completed') {
        $where[] = "si.info_completed = 1";
    } else {
        $where[] = "(si.info_completed = 0 OR si.info_completed IS NULL)";
    }
}

$whereClause = implode(' AND ', $where);

// 分页
$page = max(1, intval($_GET['page'] ?? 1));
$perPageOption = $_GET['per_page'] ?? '20';
$perPage = $perPageOption === 'all' ? PHP_INT_MAX : intval($perPageOption);

$total = $db->fetchOne(
    "SELECT COUNT(*) as count
     FROM users u
     LEFT JOIN student_info si ON u.id = si.user_id
     WHERE $whereClause",
    $params
)['count'];

if ($perPageOption === 'all') {
    $totalPages = 1;
    $offset = 0;
    $page = 1;
} else {
    $totalPages = (int) ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
}

$users = $db->fetchAll(
    "SELECT u.*,
            CASE WHEN si.info_completed = 1 THEN '已完善' ELSE '未完善' END AS info_status
     FROM users u
     LEFT JOIN student_info si ON u.id = si.user_id
     WHERE $whereClause
     ORDER BY u.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$roleMap = [
    'student' => '学生',
    'teacher' => '教师',
    'admin' => '管理员',
    'superadmin' => '系统管理员',
];

// 获取系统选项
$colleges = getSystemOptions('college');
$grades = getSystemOptions('grade');
$classes = getSystemOptions('class');
?>

<div class="card">
    <?php renderFeedbackTipAlert(); ?>
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-user-gear"></i> 账户管理</h3>
        <div class="card-actions">
            <button class="btn btn-primary btn-sm" onclick="showAddModal()">
                <i class="fa-solid fa-plus"></i> 添加账户
            </button>
            <button class="btn btn-success btn-sm" onclick="showImportModal()">
                <i class="fa-solid fa-file-import"></i> 批量导入
            </button>
            <a href="/api/admin/download_template.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-download"></i> 下载模板
            </a>
            <button class="btn btn-success btn-sm" onclick="exportAccounts()">
                <i class="fa-solid fa-file-export"></i> 导出
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- 筛选条件 -->
        <form class="filter-form" method="get">
            <div class="filter-row">
                <div class="filter-item">
                    <input
                        type="text"
                        class="form-control"
                        name="keyword"
                        placeholder="用户名/姓名"
                        value="<?php echo e($filters['keyword']); ?>"
                    >
                </div>
                <div class="filter-item">
                    <select class="form-control filter-multi-select" name="role[]" multiple data-placeholder="全部角色">
                        <option value="student" <?php echo in_array('student', $filters['role'], true) ? 'selected' : ''; ?>>学生</option>
                        <option value="teacher" <?php echo in_array('teacher', $filters['role'], true) ? 'selected' : ''; ?>>教师</option>
                        <option value="admin" <?php echo in_array('admin', $filters['role'], true) ? 'selected' : ''; ?>>管理员</option>
                        <option value="superadmin" <?php echo in_array('superadmin', $filters['role'], true) ? 'selected' : ''; ?>>系统管理员</option>
                    </select>
                </div>
                <div class="filter-item">
                    <select class="form-control filter-multi-select" name="status[]" multiple data-placeholder="全部状态">
                        <option value="active" <?php echo in_array('active', $filters['status'], true) ? 'selected' : ''; ?>>正常</option>
                        <option value="disabled" <?php echo in_array('disabled', $filters['status'], true) ? 'selected' : ''; ?>>禁用</option>
                    </select>
                </div>
                <div class="filter-item">
                    <select class="form-control filter-multi-select" name="grade[]" multiple data-placeholder="全部年级">
                        <?php foreach ($grades as $grade): ?>
                            <option value="<?php echo e($grade); ?>" <?php echo in_array($grade, $filters['grade'], true) ? 'selected' : ''; ?>>
                                <?php echo e($grade); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <select class="form-control filter-multi-select" name="info_status[]" multiple data-placeholder="全部信息状态">
                        <option value="completed" <?php echo in_array('completed', $filters['info_status'], true) ? 'selected' : ''; ?>>已完善</option>
                        <option value="incomplete" <?php echo in_array('incomplete', $filters['info_status'], true) ? 'selected' : ''; ?>>未完善</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> 查询</button>
                    <a href="/pages/admin/accounts.php" class="btn btn-secondary"><i class="fa-solid fa-undo"></i> 重置</a>
                </div>
            </div>
        </form>

        <div class="result-info">
            共 <strong><?php echo $total; ?></strong> 个账户
            <span id="selectedInfo" style="margin-left: 20px; color: #007bff; display: none;">
                已选中 <strong id="selectedCount">0</strong> 个账户
            </span>
        </div>

        <div class="per-page-selector">
            <label>每页显示：</label>
            <select class="form-control" id="perPageSelect" onchange="changePerPage(this.value)">
                <option value="20" <?php echo $perPageOption === '20' ? 'selected' : ''; ?>>20 条</option>
                <option value="50" <?php echo $perPageOption === '50' ? 'selected' : ''; ?>>50 条</option>
                <option value="all" <?php echo $perPageOption === 'all' ? 'selected' : ''; ?>>全部显示</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="checkAll" onchange="toggleAll()"></th>
                        <th>用户名</th>
                        <th>姓名</th>
                        <th>角色</th>
                        <th>状态</th>
                        <th>信息状态</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="text-center text-muted">暂无数据</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        class="user-check"
                                        value="<?php echo $user['id']; ?>"
                                        data-username="<?php echo e($user['username']); ?>"
                                        data-name="<?php echo e($user['name'] ?: '-'); ?>"
                                        data-role="<?php echo e($roleMap[$user['role']] ?? $user['role']); ?>"
                                    >
                                </td>
                                <td><?php echo e($user['username']); ?></td>
                                <td><?php echo e($user['name'] ?: '-'); ?></td>
                                <td><span class="badge badge-info"><?php echo e($roleMap[$user['role']] ?? $user['role']); ?></span></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $user['is_active'] ? '正常' : '禁用'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'student'): ?>
                                        <span class="badge badge-<?php echo $user['info_status'] === '已完善' ? 'success' : 'warning'; ?>">
                                            <?php echo e($user['info_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php
                                    // 检查当前登录用户是否有权限管理该用户
                                    $canManage = canManageUser($user['role']);
                                    ?>
                                    <div class="btn-group">
                                        <?php if ($canManage): ?>
                                            <button class="btn btn-warning btn-sm" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo e($user['username']); ?>')">
                                                <i class="fa-solid fa-key"></i>
                                            </button>
                                            <?php if ($user['is_active']): ?>
                                                <button class="btn btn-secondary btn-sm" onclick="toggleStatus(<?php echo $user['id']; ?>, 'disable')">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-success btn-sm" onclick="toggleStatus(<?php echo $user['id']; ?>, 'enable')">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($user['role'] === 'student'): ?>
                                                <button class="btn btn-info btn-sm" onclick="showChangeModal(<?php echo $user['id']; ?>, '<?php echo e($user['username']); ?>')">
                                                    <i class="fa-solid fa-exchange-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo e($user['username']); ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 12px;">无权限操作</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 批量操作 -->
        <div class="batch-actions">
            <button class="btn btn-warning btn-sm" onclick="batchResetPassword()">
                <i class="fa-solid fa-key"></i> 批量重置密码
            </button>
            <button class="btn btn-info btn-sm" onclick="batchGraduate()">
                <i class="fa-solid fa-graduation-cap"></i> 设为毕业生
            </button>
            <button class="btn btn-danger btn-sm" onclick="batchDelete()">
                <i class="fa-solid fa-trash"></i> 批量删除
            </button>
            <button class="btn btn-success btn-sm" onclick="exportSelected()">
                <i class="fa-solid fa-file-export"></i> 导出选中
            </button>
        </div>

        <?php if ($totalPages > 1 && $perPageOption !== 'all'): ?>
            <div class="pagination-wrapper">
                <?php
                $queryParams = array_merge(array_filter($filters), ['per_page' => $perPageOption]);
                echo generatePagination($page, $totalPages, '?' . http_build_query($queryParams) . '&page=');
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 添加账户弹窗 -->
<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-user-plus"></i> 添加账户</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addForm">
                <div class="form-group">
                    <label class="form-label required">用户名（学号/工号）</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">姓名</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">角色</label>
                    <select class="form-control" name="role" id="addRole" required onchange="toggleStudentFields()">
                        <option value="student">学生</option>
                        <option value="teacher">教师</option>
                        <option value="admin">管理员</option>
                        <?php if (isSuperAdmin()): ?>
                            <option value="superadmin">系统管理员</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div id="studentFields">
                    <div class="form-group">
                        <label class="form-label required">性别</label>
                        <select class="form-control" name="gender">
                            <option value="男">男</option>
                            <option value="女">女</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">学院</label>
                        <select class="form-control select2" name="college">
                            <?php foreach ($colleges as $college): ?>
                                <option value="<?php echo e($college); ?>"><?php echo e($college); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">年级</label>
                        <select class="form-control select2" name="grade">
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo e($grade); ?>"><?php echo e($grade); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">班级</label>
                        <select class="form-control select2" name="class">
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo e($class); ?>"><?php echo e($class); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">初始密码</label>
                    <input type="text" class="form-control" name="password" placeholder="留空则默认为用户名">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addModal')">取消</button>
            <button class="btn btn-primary" onclick="submitAdd()">确认添加</button>
        </div>
    </div>
</div>

<!-- 批量导入弹窗 -->
<div class="modal-overlay" id="importModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-file-import"></i> 批量导入账户</h3>
            <button class="modal-close" onclick="closeModal('importModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle"></i>
                请先下载模板，按格式填写后上传 CSV 文件。初始密码默认为学号/工号。
            </div>
            <form id="importForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label required">选择文件</label>
                    <input type="file" class="form-control" name="file" accept=".csv" required>
                </div>
            </form>
            <div id="importResult" style="display: none;">
                <div class="alert" id="importAlert"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('importModal')">取消</button>
            <button class="btn btn-primary" onclick="submitImport()" id="importBtn">开始导入</button>
        </div>
    </div>
</div>

<!-- 学籍变动弹窗 -->
<div class="modal-overlay" id="changeModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-exchange-alt"></i> 学籍变动</h3>
            <button class="modal-close" onclick="closeModal('changeModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>用户：<strong id="changeUsername"></strong></p>
            <form id="changeForm">
                <input type="hidden" name="user_id" id="changeUserId">
                <div class="form-group">
                    <label class="form-label required">变动类型</label>
                    <select class="form-control" name="action" id="changeAction" required onchange="toggleTransferFields()">
                        <option value="graduate">设为毕业</option>
                        <option value="transfer">转专业/班级</option>
                    </select>
                </div>
                <div id="transferFields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label required">转入学院</label>
                        <select class="form-control" name="college">
                            <option value="">请选择</option>
                            <?php foreach ($colleges as $college): ?>
                                <option value="<?php echo e($college); ?>"><?php echo e($college); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">转入年级</label>
                        <select class="form-control" name="grade">
                            <option value="">请选择</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo e($grade); ?>"><?php echo e($grade); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">转入班级</label>
                        <select class="form-control" name="class">
                            <option value="">请选择</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo e($class); ?>"><?php echo e($class); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('changeModal')">取消</button>
            <button class="btn btn-primary" onclick="submitChange()">确认</button>
        </div>
    </div>
</div>

<style>
.filter-form { background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 20px; }
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.filter-item { min-width: 150px; }
.filter-actions { display: flex; gap: 8px; }
.result-info { padding: 12px 0; color: #666; border-bottom: 1px solid #eee; margin-bottom: 16px; }
.card-actions { display: flex; gap: 8px; }
.btn-group { display: flex; gap: 4px; }
.batch-actions { margin-top: 16px; padding-top: 16px; border-top: 1px solid #eee; display: flex; gap: 8px; }
.per-page-selector { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.per-page-selector label { margin: 0; }
.per-page-selector select { width: auto; min-width: 120px; }
#addModal,
#importModal,
#changeModal {
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
    transition: opacity 0.18s ease, visibility 0.18s ease;
    align-items: flex-start;
    padding: 24px 12px;
    overflow-y: auto;
}
#addModal .modal,
#importModal .modal,
#changeModal .modal,
#addModal.active .modal,
#importModal.active .modal,
#changeModal.active .modal {
    transform: none;
    transition: none;
    will-change: auto;
}
#addModal .modal {
    display: flex;
    flex-direction: column;
    width: min(560px, calc(100vw - 24px));
    max-height: calc(100vh - 48px);
    overflow: hidden;
}
#addModal .modal-body {
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding-bottom: 18px !important;
}
#addModal .modal-footer {
    position: sticky;
    bottom: 0;
    flex-shrink: 0;
    z-index: 2;
    background: var(--surface-solid, #fffaf3) !important;
    box-shadow: 0 -12px 26px rgba(74, 17, 10, 0.08);
}
#addModal #studentFields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
#addModal #studentFields .form-group {
    margin-bottom: 0;
}
#addModal .select2-container {
    max-width: 100%;
}
.select2-container--open {
    z-index: 1065;
}
@media (max-width: 576px) {
    #addModal,
    #importModal,
    #changeModal {
        padding: 10px;
    }
    #addModal .modal {
        width: 100%;
        max-height: calc(100vh - 20px);
    }
    #addModal #studentFields {
        grid-template-columns: 1fr;
    }
    #addModal .modal-footer {
        flex-direction: column-reverse;
    }
    #addModal .modal-footer .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.filter-form .filter-multi-select').select2({
        width: '100%',
        language: 'zh-CN',
        allowClear: true,
        closeOnSelect: false
    });

    $('#addModal .select2').select2({
        width: '100%',
        language: 'zh-CN',
        dropdownParent: $('#addModal')
    });
    bindAccountCheckboxes();
});

function toggleStudentFields() {
    const role = document.getElementById('addRole').value;
    document.getElementById('studentFields').style.display = role === 'student' ? 'block' : 'none';
}

function toggleAll() {
    const checkAll = document.getElementById('checkAll');
    document.querySelectorAll('.user-check').forEach((cb) => {
        cb.checked = checkAll.checked;
    });
    updateSelectedCount();
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.user-check:checked')).map((cb) => cb.value);
}

function getAccountById(userId) {
    const checkbox = document.querySelector(`.user-check[value="${userId}"]`);
    if (!checkbox) return null;
    return {
        username: checkbox.dataset.username || '',
        name: checkbox.dataset.name || '',
        role: checkbox.dataset.role || ''
    };
}

function getSelectedAccountDetails() {
    return Array.from(document.querySelectorAll('.user-check:checked')).map((checkbox) => ({
        username: checkbox.dataset.username || '',
        name: checkbox.dataset.name || '',
        role: checkbox.dataset.role || ''
    }));
}

function bindAccountCheckboxes() {
    document.querySelectorAll('.user-check').forEach((checkbox) => {
        if (checkbox.dataset.bound === '1') return;
        checkbox.dataset.bound = '1';
        checkbox.addEventListener('change', updateSelectedCount);
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const allCheckboxes = Array.from(document.querySelectorAll('.user-check'));
    const count = allCheckboxes.filter((checkbox) => checkbox.checked).length;
    const selectedInfo = document.getElementById('selectedInfo');
    const selectedCount = document.getElementById('selectedCount');
    const checkAll = document.getElementById('checkAll');
    if (count > 0) {
        selectedCount.textContent = count;
        selectedInfo.style.display = 'inline';
    } else {
        selectedInfo.style.display = 'none';
    }
    if (checkAll) {
        checkAll.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
        checkAll.indeterminate = count > 0 && count < allCheckboxes.length;
    }
}

async function refreshAccountsPage(delay = 0) {
    const refresh = async () => {
        try {
            const response = await fetch(window.location.href, {
                method: 'GET',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('refresh failed');
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const replacements = [
                '.result-info',
                '.table-responsive',
                '.pagination-wrapper'
            ];

            replacements.forEach((selector) => {
                const current = document.querySelector(selector);
                const next = doc.querySelector(selector);

                if (current && next) {
                    current.replaceWith(next);
                    return;
                }

                if (!current && next && selector === '.pagination-wrapper') {
                    const batchActions = document.querySelector('.batch-actions');
                    if (batchActions) {
                        batchActions.insertAdjacentElement('afterend', next);
                    }
                    return;
                }

                if (current && !next && selector === '.pagination-wrapper') {
                    current.remove();
                }
            });

            const checkAll = document.getElementById('checkAll');
            if (checkAll) {
                checkAll.checked = false;
                checkAll.indeterminate = false;
            }
            bindAccountCheckboxes();
        } catch (error) {
            location.reload();
        }
    };

    if (delay > 0) {
        setTimeout(refresh, delay);
        return;
    }

    refresh();
}

function getUserRow(userId) {
    const checkbox = document.querySelector(`.user-check[value="${userId}"]`);
    return checkbox ? checkbox.closest('tr') : null;
}

function updateTotalCount(delta) {
    const totalEl = document.querySelector('.result-info strong');
    if (!totalEl) return;

    const current = parseInt(totalEl.textContent, 10);
    if (!Number.isNaN(current)) {
        totalEl.textContent = Math.max(0, current + delta);
    }
}

function removeUserRows(userIds) {
    userIds.forEach((userId) => {
        const row = getUserRow(userId);
        if (row) {
            row.remove();
            updateTotalCount(-1);
        }
    });

    updateSelectedCount();
}

function updateUserStatusRow(userId, action) {
    const row = getUserRow(userId);
    if (!row) return;

    const isEnable = action === 'enable';
    const statusBadge = row.querySelector('td:nth-child(5) .badge');
    if (statusBadge) {
        statusBadge.className = `badge badge-${isEnable ? 'success' : 'danger'}`;
        statusBadge.textContent = isEnable ? '正常' : '禁用';
    }

    const statusButton = row.querySelector(`button[onclick*="toggleStatus(${userId}"]`);
    if (statusButton) {
        statusButton.className = `btn btn-${isEnable ? 'secondary' : 'success'} btn-sm`;
        statusButton.setAttribute('onclick', `toggleStatus(${userId}, '${isEnable ? 'disable' : 'enable'}')`);
        statusButton.innerHTML = `<i class="fa-solid fa-${isEnable ? 'ban' : 'check'}"></i>`;
    }
}

function showAddModal() {
    document.getElementById('addForm').reset();
    toggleStudentFields();
    const modal = document.getElementById('addModal');
    if (!modal.classList.contains('active')) {
        modal.classList.add('active');
    }
}

function showImportModal() {
    document.getElementById('importForm').reset();
    document.getElementById('importResult').style.display = 'none';
    const modal = document.getElementById('importModal');
    if (!modal.classList.contains('active')) {
        modal.classList.add('active');
    }
}

function showChangeModal(userId, username) {
    document.getElementById('changeUserId').value = userId;
    document.getElementById('changeUsername').textContent = username;
    document.getElementById('changeForm').reset();
    document.getElementById('changeAction').value = 'graduate';
    toggleTransferFields();
    const modal = document.getElementById('changeModal');
    if (!modal.classList.contains('active')) {
        modal.classList.add('active');
    }
}

function toggleTransferFields() {
    const action = document.getElementById('changeAction').value;
    document.getElementById('transferFields').style.display = action === 'transfer' ? 'block' : 'none';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function renderImportResult(result) {
    const resultDiv = document.getElementById('importResult');
    const alertDiv = document.getElementById('importAlert');
    resultDiv.style.display = 'block';
    alertDiv.className = `alert ${result.success ? 'alert-success' : 'alert-danger'}`;
    let html = escapeHtml(result.message || (result.success ? '导入成功' : '导入失败'));
    if (Array.isArray(result.errors) && result.errors.length > 0) {
        html += '<ul style="margin-top: 10px; margin-bottom: 0;">';
        result.errors.forEach((err) => { html += `<li>${escapeHtml(err)}</li>`; });
        html += '</ul>';
    }
    alertDiv.innerHTML = html;
}

function renderImportStatus(type, message) {
    const resultDiv = document.getElementById('importResult');
    const alertDiv = document.getElementById('importAlert');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-info';
    const iconHtml = type === 'loading'
        ? '<i class="fa-solid fa-spinner fa-spin"></i> '
        : '<i class="fa-solid fa-check-circle"></i> ';

    resultDiv.style.display = 'block';
    alertDiv.className = `alert ${alertClass}`;
    alertDiv.innerHTML = iconHtml + escapeHtml(message);
}

function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function buildPreviewExtra(preview) {
    if (!preview.error_count) return '';
    let html = `<div class="alert alert-warning" style="margin-top: 12px;">预检发现 ${preview.error_count} 行不符合要求。`;
    if (Array.isArray(preview.errors) && preview.errors.length > 0) {
        html += '<ul style="margin-top: 8px; margin-bottom: 0; padding-left: 20px;">';
        preview.errors.forEach((err) => { html += `<li>${escapeHtml(err)}</li>`; });
        html += '</ul>';
    }
    html += '</div>';
    return html;
}

async function submitAdd() {
    const data = Object.fromEntries(new FormData(document.getElementById('addForm')).entries());
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-user-plus"></i> 添加账户',
        messageHtml: '<p>确认按当前表单内容创建该账户吗？</p>',
        confirmText: '确认添加',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/add_user.php', { ...data, admin_password: adminPassword });
            if (!response.success) {
                Toast.error(response.message);
                return false;
            }
            Toast.success(response.message);
            closeModal('addModal');
            refreshAccountsPage();
            return true;
        }
    });
}

async function submitImport() {
    const file = document.querySelector('#importForm input[type="file"]').files[0];
    if (!file) {
        Toast.error('请选择文件');
        return;
    }
    const btn = document.getElementById('importBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 正在预检...';
    renderImportStatus('loading', '正在预检，请稍后...');
    try {
        const previewFormData = new FormData();
        previewFormData.append('file', file);
        const preview = await Ajax.upload('/api/admin/preview_import_users.php', previewFormData);
        if (!preview.success) {
            renderImportResult(preview);
            Toast.error(preview.message || '预检失败');
            return;
        }
        renderImportStatus('success', `预检通过，未发现错误。即将进行二次密码验证，本次将处理 ${preview.total_count || 0} 个账户。`);
        Toast.success('预检通过，未发现错误');
        await wait(800);
        AdminActionConfirm.open({
            title: '<i class="fa-solid fa-file-import"></i> 批量导入账户',
            messageHtml: `<p>本次将处理 <strong>${preview.total_count || 0}</strong> 个账户。</p>`,
            extraContentHtml: buildPreviewExtra(preview),
            accountList: preview.accounts || [],
            confirmText: '开始导入',
            onConfirm: async ({ adminPassword }) => {
                const importFormData = new FormData();
                importFormData.append('file', file);
                importFormData.append('admin_password', adminPassword);
                const result = await Ajax.upload('/api/admin/import_users.php', importFormData);
                renderImportResult(result);
                if (!result.success) {
                    Toast.error(result.message);
                    return false;
                }
                Toast.success(result.message);
                closeModal('importModal');
                refreshAccountsPage(300);
                return true;
            }
        });
    } catch (error) {
        renderImportResult({
            success: false,
            message: error.message || '网络错误，请稍后重试'
        });
        Toast.error(error.message || '网络错误');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '开始导入';
    }
}

async function resetPassword(userId, username) {
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-key"></i> 重置密码',
        messageHtml: `<p>确认将 <strong>${username}</strong> 的密码重置为用户名吗？</p>`,
        accountList: [getAccountById(userId) || { username }],
        confirmText: '确认重置',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/reset_password.php', { user_id: userId, admin_password: adminPassword });
            if (!response.success) {
                Toast.error(response.message);
                return false;
            }
            Toast.success(response.message);
            return true;
        }
    });
}

async function toggleStatus(userId, action) {
    const actionText = action === 'enable' ? '启用' : '禁用';
    const account = getAccountById(userId);
    AdminActionConfirm.open({
        title: `<i class="fa-solid fa-user-lock"></i> ${actionText}账户`,
        messageHtml: `<p>确认${actionText}该账户吗？</p>`,
        accountList: account ? [account] : [],
        confirmText: actionText,
        confirmButtonClass: action === 'disable' ? 'btn-danger' : 'btn-primary',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/toggle_status.php', { user_id: userId, action, admin_password: adminPassword });
            if (!response.success) {
                Toast.error(response.message);
                return false;
            }
            Toast.success(response.message);
            updateUserStatusRow(userId, action);
            return true;
        }
    });
}

async function submitChange() {
    const data = Object.fromEntries(new FormData(document.getElementById('changeForm')).entries());
    const username = document.getElementById('changeUsername').textContent;
    const actionLabel = data.action === 'transfer' ? '转专业/班级' : '设为毕业';
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-exchange-alt"></i> 学籍变动',
        messageHtml: `<p>确认对 <strong>${username}</strong> 执行“${actionLabel}”吗？</p>`,
        confirmText: '确认',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/student_change.php', { ...data, admin_password: adminPassword });
            if (!response.success) {
                Toast.error(response.message);
                return false;
            }
            Toast.success(response.message);
            closeModal('changeModal');
            refreshAccountsPage();
            return true;
        }
    });
}

async function batchResetPassword() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        Toast.warning('请至少选择一个账户');
        return;
    }
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-key"></i> 批量重置密码',
        messageHtml: `<p>确认重置已选 <strong>${ids.length}</strong> 个账户的密码吗？</p>`,
        accountList: getSelectedAccountDetails(),
        confirmText: '确认重置',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/batch_reset_password.php', { user_ids: ids, admin_password: adminPassword });
            if (!response.success) {
                Toast.error(response.message);
                return false;
            }
            Toast.success(response.message);
            return true;
        }
    });
}

async function deleteUser(userId, username) {
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-user-minus"></i> 删除账户',
        messageHtml: `<p>确认删除 <strong>${username}</strong> 吗？此操作不可恢复。</p>`,
        accountList: [getAccountById(userId) || { username }],
        confirmText: '确认删除',
        confirmButtonClass: 'btn-danger',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/delete_user.php', { user_id: userId, admin_password: adminPassword });
            if (!response.success) {
                Toast.error(response.message);
                return false;
            }
            Toast.success(response.message);
            removeUserRows([String(userId)]);
            return true;
        }
    });
}

async function batchDelete() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        Toast.warning('请至少选择一个账户');
        return;
    }
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-trash"></i> 批量删除',
        messageHtml: `<p>确认删除已选 <strong>${ids.length}</strong> 个账户吗？此操作不可恢复。</p>`,
        accountList: getSelectedAccountDetails(),
        confirmText: '确认删除',
        confirmButtonClass: 'btn-danger',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/batch_delete.php', { user_ids: ids, admin_password: adminPassword });
            if (!response.success) {
                Toast.error(response.message);
                return false;
            }
            Toast.success(response.message);
            removeUserRows(ids);
            return true;
        }
    });
}

async function batchGraduate() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        Toast.warning('请至少选择一个账户');
        return;
    }
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-graduation-cap"></i> 批量设为毕业生',
        messageHtml: `<p>确认将已选 <strong>${ids.length}</strong> 名学生设为毕业生吗？</p>`,
        accountList: getSelectedAccountDetails(),
        confirmText: '确认',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/batch_graduate.php', { user_ids: ids, admin_password: adminPassword });
            if (!response.success) {
                Toast.error(response.message);
                return false;
            }
            Toast.success(response.message);
            refreshAccountsPage();
            return true;
        }
    });
}

function changePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// 导出全部账户（带当前筛选条件）
function exportAccounts() {
    const currentUrl = new URL(window.location.href);
    const params = new URLSearchParams(currentUrl.search);

    // 构建导出URL，带上当前筛选条件
    const exportUrl = '/api/admin/export_accounts.php?' + params.toString();

    // 触发下载
    window.location.href = exportUrl;

    Toast.success('正在导出账户信息...');
}

// 导出选中账户
async function exportSelected() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        Toast.warning('请至少选择一个账户');
        return;
    }

    try {
        const response = await Ajax.post('/api/admin/export_accounts_batch.php', {
            user_ids: ids
        });

        if (!response.success) {
            Toast.error(response.message);
            return;
        }

        Toast.success(response.message);

        // 触发下载
        const link = document.createElement('a');
        link.href = response.download_url;
        link.download = response.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } catch (error) {
        Toast.error('导出失败，请稍后重试');
    }
}

document.querySelectorAll('.modal-overlay').forEach((modal) => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
