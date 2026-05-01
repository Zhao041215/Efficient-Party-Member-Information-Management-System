<?php
/**
 * 管理员端系统设置
 */
$pageTitle = '系统设置 - 生化学院党员信息管理系统';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'superadmin']);

$db = Database::getInstance();

// 获取各类选项
$optionTypes = [
    'college' => '学院',
    'grade' => '年级',
    'class' => '班级',
    'political_status' => '政治面貌',
    'development_time' => '发展时间'
];

$options = [];
foreach ($optionTypes as $type => $label) {
    $options[$type] = $db->fetchAll("
        SELECT * FROM system_options 
        WHERE type = ? 
        ORDER BY sort_order, id
    ", [$type]);
}
?>

<div class="settings-container">
    <?php renderFeedbackTipAlert(); ?>
    <div class="settings-tabs">
        <?php $first = true; foreach ($optionTypes as $type => $label): ?>
            <button class="settings-tab <?php echo $first ? 'active' : ''; ?>" 
                    onclick="switchTab('<?php echo $type; ?>')">
                <?php echo $label; ?>
            </button>
        <?php $first = false; endforeach; ?>
    </div>
    
    <?php $first = true; foreach ($optionTypes as $type => $label): ?>
        <div class="settings-panel <?php echo $first ? 'active' : ''; ?>" id="panel-<?php echo $type; ?>" data-type="<?php echo $type; ?>" data-label="<?php echo e($label); ?>">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-list"></i> <?php echo $label; ?>管理</h3>
                    <div class="card-actions">
                        <?php if ($type === 'development_time'): ?>
                            <button class="btn btn-primary btn-sm" onclick="showAddDevelopmentTime()">
                                <i class="fa-solid fa-plus"></i> 添加<?php echo $label; ?>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-primary btn-sm" onclick="showAddOption('<?php echo $type; ?>', '<?php echo $label; ?>')">
                                <i class="fa-solid fa-plus"></i> 添加<?php echo $label; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($type === 'development_time'): ?>
                        <!-- 发展时间特殊显示 -->
                        <div class="development-time-list" id="list-development_time">
                            <?php if (empty($options[$type])): ?>
                                <div class="text-center text-muted py-4">暂无数据</div>
                            <?php else: ?>
                                <?php foreach ($options[$type] as $option):
                                    $parts = explode('|', $option['value']);
                                    $timeName = $parts[0] ?? '';
                                    $timeValue = $parts[1] ?? '';
                                ?>
                                    <div class="development-time-item" data-id="<?php echo $option['id']; ?>">
                                        <div class="time-label"><?php echo e($timeName); ?></div>
                                        <div class="time-value"><?php echo e($timeValue); ?></div>
                                        <div class="time-actions">
                                            <button class="btn btn-warning btn-sm"
                                                    onclick="editDevelopmentTime(<?php echo $option['id']; ?>, '<?php echo e($timeName); ?>', '<?php echo e($timeValue); ?>')">
                                                <i class="fa-solid fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm"
                                                    onclick="deleteOption(<?php echo $option['id']; ?>, '<?php echo e($timeName); ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- 普通选项显示 -->
                        <div class="option-list" id="list-<?php echo $type; ?>">
                            <?php if (empty($options[$type])): ?>
                                <div class="text-center text-muted py-4">暂无数据</div>
                            <?php else: ?>
                                <?php foreach ($options[$type] as $option): ?>
                                    <div class="option-item" data-id="<?php echo $option['id']; ?>">
                                        <div class="option-handle">
                                            <i class="fa-solid fa-grip-vertical"></i>
                                        </div>
                                        <div class="option-value"><?php echo e($option['value']); ?></div>
                                        <div class="option-actions">
                                            <button class="btn btn-warning btn-sm"
                                                    onclick="editOption(<?php echo $option['id']; ?>, '<?php echo e($option['value']); ?>', '<?php echo $type; ?>', '<?php echo $label; ?>')">
                                                <i class="fa-solid fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm"
                                                    onclick="deleteOption(<?php echo $option['id']; ?>, '<?php echo e($option['value']); ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php $first = false; endforeach; ?>
</div>

<!-- 添加/编辑模态框 -->
<div class="modal-overlay" id="optionModal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">添加选项</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="optionForm">
                <input type="hidden" name="id" id="optionId">
                <input type="hidden" name="type" id="optionType">
                <div class="form-group">
                    <label class="form-label required" id="optionLabel">选项值</label>
                    <input type="text" class="form-control" name="value" id="optionValue" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">取消</button>
            <button class="btn btn-primary" onclick="submitOption()">确认</button>
        </div>
    </div>
</div>

<!-- 发展时间添加/编辑模态框 -->
<div class="modal-overlay" id="developmentTimeModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="devTimeModalTitle">添加发展时间</h3>
            <button class="modal-close" onclick="closeDevelopmentTimeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="developmentTimeForm">
                <input type="hidden" name="id" id="devTimeId">
                <div class="form-group">
                    <label class="form-label required">时间名称</label>
                    <input type="text" class="form-control" name="time_name" id="devTimeName" placeholder="例如：确定入党积极分子时间" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">时间值</label>
                    <input type="date" class="form-control" name="time_value" id="devTimeValue" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDevelopmentTimeModal()">取消</button>
            <button class="btn btn-primary" onclick="submitDevelopmentTime()">确认</button>
        </div>
    </div>
</div>

<style>
.settings-container {
    max-width: 800px;
}

.settings-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.settings-tab {
    padding: 10px 20px;
    border: none;
    background: #f8f9fa;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.settings-tab:hover {
    background: #e9ecef;
}

.settings-tab.active {
    background: #c41e3a;
    color: #fff;
}

.settings-panel {
    display: none;
}

.settings-panel.active {
    display: block;
}

.option-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.option-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #f8f9fa;
    border-radius: 6px;
    transition: all 0.2s;
}

.option-item:hover {
    background: #e9ecef;
}

.option-handle {
    color: #aaa;
    cursor: grab;
}

.option-value {
    flex: 1;
    font-size: 15px;
}

.option-actions {
    display: flex;
    gap: 4px;
}

.card-actions {
    display: flex;
    gap: 8px;
}

/* 发展时间样式 */
.development-time-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.development-time-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    transition: all 0.2s;
}

.development-time-item:hover {
    background: #e9ecef;
}

.time-label {
    flex: 1;
    font-size: 15px;
    font-weight: 500;
    color: #333;
}

.time-value {
    font-size: 14px;
    color: #c41e3a;
    font-weight: 500;
    padding: 6px 12px;
    background: #fff;
    border-radius: 4px;
    border: 1px solid #c41e3a;
}

.time-actions {
    display: flex;
    gap: 4px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const OPTION_LABELS = {
    college: '学院',
    grade: '年级',
    class: '班级',
    political_status: '政治面貌',
    development_time: '发展时间'
};

document.addEventListener('DOMContentLoaded', function() {
    initSettingsSortables();

    document.getElementById('optionModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.getElementById('developmentTimeModal').addEventListener('click', function(e) {
        if (e.target === this) closeDevelopmentTimeModal();
    });
});

function initSettingsSortables() {
    document.querySelectorAll('.option-list').forEach(list => {
        if (list.dataset.sortableInitialized === '1') {
            return;
        }

        new Sortable(list, {
            handle: '.option-handle',
            animation: 150,
            onEnd: async function() {
                const items = list.querySelectorAll('.option-item');
                const order = Array.from(items).map((item, index) => ({
                    id: item.dataset.id,
                    sort_order: index
                }));

                try {
                    const response = await confirmSortOptions(order);
                    if (!response.success && !response.cancelled) {
                        Toast.error(response.message || '排序保存失败');
                    }
                } catch (error) {
                    Toast.error('排序保存失败');
                }
            }
        });

        list.dataset.sortableInitialized = '1';
    });
}

function switchTab(type) {
    document.querySelectorAll('.settings-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.settings-panel').forEach(panel => panel.classList.remove('active'));

    document.querySelector(`.settings-tab[onclick*="${type}"]`).classList.add('active');
    document.getElementById('panel-' + type).classList.add('active');
}

function getListElement(type) {
    return document.getElementById(`list-${type}`);
}

function ensureEmptyState(type) {
    const list = getListElement(type);
    if (!list) {
        return;
    }

    const itemSelector = type === 'development_time' ? '.development-time-item' : '.option-item';
    const items = list.querySelectorAll(itemSelector);
    const emptyState = list.querySelector('.text-center.text-muted');

    if (items.length === 0 && !emptyState) {
        const div = document.createElement('div');
        div.className = 'text-center text-muted py-4';
        div.textContent = '暂无数据';
        list.appendChild(div);
    }

    if (items.length > 0 && emptyState) {
        emptyState.remove();
    }
}

function createOptionItem(data) {
    const label = OPTION_LABELS[data.type] || '选项';
    const wrapper = document.createElement('div');
    wrapper.className = 'option-item';
    wrapper.dataset.id = data.id;
    wrapper.innerHTML = `
        <div class="option-handle">
            <i class="fa-solid fa-grip-vertical"></i>
        </div>
        <div class="option-value"></div>
        <div class="option-actions">
            <button class="btn btn-warning btn-sm">
                <i class="fa-solid fa-edit"></i>
            </button>
            <button class="btn btn-danger btn-sm">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;

    wrapper.querySelector('.option-value').textContent = data.value;
    wrapper.querySelector('.btn-warning').addEventListener('click', () => {
        editOption(data.id, data.value, data.type, label);
    });
    wrapper.querySelector('.btn-danger').addEventListener('click', () => {
        deleteOption(data.id, data.value);
    });
    return wrapper;
}

function createDevelopmentTimeItem(data) {
    const [timeName = '', timeValue = ''] = String(data.value).split('|');
    const wrapper = document.createElement('div');
    wrapper.className = 'development-time-item';
    wrapper.dataset.id = data.id;
    wrapper.innerHTML = `
        <div class="time-label"></div>
        <div class="time-value"></div>
        <div class="time-actions">
            <button class="btn btn-warning btn-sm">
                <i class="fa-solid fa-edit"></i>
            </button>
            <button class="btn btn-danger btn-sm">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;

    wrapper.querySelector('.time-label').textContent = timeName;
    wrapper.querySelector('.time-value').textContent = timeValue;
    wrapper.querySelector('.btn-warning').addEventListener('click', () => {
        editDevelopmentTime(data.id, timeName, timeValue);
    });
    wrapper.querySelector('.btn-danger').addEventListener('click', () => {
        deleteOption(data.id, timeName);
    });
    return wrapper;
}

function upsertOptionItem(data) {
    const list = getListElement(data.type);
    if (!list) {
        return;
    }

    const existing = list.querySelector(`[data-id="${data.id}"]`);
    const nextNode = data.type === 'development_time' ? createDevelopmentTimeItem(data) : createOptionItem(data);

    if (existing) {
        existing.replaceWith(nextNode);
    } else {
        list.appendChild(nextNode);
    }

    ensureEmptyState(data.type);
}

function removeOptionItem(type, id) {
    const list = getListElement(type);
    if (!list) {
        return;
    }

    const existing = list.querySelector(`[data-id="${id}"]`);
    if (existing) {
        existing.remove();
    }

    ensureEmptyState(type);
}

function showAddOption(type, label) {
    document.getElementById('modalTitle').textContent = '添加' + label;
    document.getElementById('optionLabel').textContent = label + '名称';
    document.getElementById('optionId').value = '';
    document.getElementById('optionType').value = type;
    document.getElementById('optionValue').value = '';
    document.getElementById('optionModal').classList.add('active');
}

function editOption(id, value, type, label) {
    document.getElementById('modalTitle').textContent = '编辑' + label;
    document.getElementById('optionLabel').textContent = label + '名称';
    document.getElementById('optionId').value = id;
    document.getElementById('optionType').value = type;
    document.getElementById('optionValue').value = value;
    document.getElementById('optionModal').classList.add('active');
}

function closeModal() {
    document.getElementById('optionModal').classList.remove('active');
}

/* async function submitOption() {
    const id = document.getElementById('optionId').value;
    const type = document.getElementById('optionType').value;
    const value = document.getElementById('optionValue').value.trim();

    if (!value) {
        Toast.error('请输入选项值');
        return;
    }

    try {
        const response = await Ajax.post('/api/admin/save_option.php', {
            id: id || null,
            type: type,
            value: value
        });

        if (!response.success) {
            Toast.error(response.message);
            return;
        }

        upsertOptionItem(response.data);
        initSettingsSortables();
        closeModal();
        Toast.success(response.message);
    } catch (error) {
        Toast.error('网络错误');
    }
}

async function deleteOption(id, value) {
    Modal.confirm(`确定要删除选项 "${value}" 吗？`, async () => {
        try {
            const response = await Ajax.post('/api/admin/delete_option.php', { id: id });
            if (!response.success) {
                Toast.error(response.message);
                return;
            }

            removeOptionItem(response.data.type, response.data.id);
            Toast.success(response.message);
        } catch (error) {
            Toast.error('网络错误');
        }
    });
}
*/

function showAddDevelopmentTime() {
    document.getElementById('devTimeModalTitle').textContent = '添加发展时间';
    document.getElementById('devTimeId').value = '';
    document.getElementById('devTimeName').value = '';
    document.getElementById('devTimeValue').value = '';
    document.getElementById('developmentTimeModal').classList.add('active');
}

function editDevelopmentTime(id, name, value) {
    document.getElementById('devTimeModalTitle').textContent = '编辑发展时间';
    document.getElementById('devTimeId').value = id;
    document.getElementById('devTimeName').value = name;
    document.getElementById('devTimeValue').value = value;
    document.getElementById('developmentTimeModal').classList.add('active');
}

function closeDevelopmentTimeModal() {
    document.getElementById('developmentTimeModal').classList.remove('active');
}

/* async function submitDevelopmentTime() {
    const id = document.getElementById('devTimeId').value;
    const timeName = document.getElementById('devTimeName').value.trim();
    const timeValue = document.getElementById('devTimeValue').value.trim();

    if (!timeName || !timeValue) {
        Toast.error('请填写完整信息');
        return;
    }

    try {
        const response = await Ajax.post('/api/admin/save_option.php', {
            id: id || null,
            type: 'development_time',
            value: timeName + '|' + timeValue
        });

        if (!response.success) {
            Toast.error(response.message);
            return;
        }

        upsertOptionItem(response.data);
        closeDevelopmentTimeModal();
        Toast.success(response.message);
    } catch (error) {
        Toast.error('网络错误');
    }
}
*/
/*
function buildOptionConfirmMessage(type, value) {
    const label = OPTION_LABELS[type] || '选项';
    return `<p>确定要保存 ${label} “<strong>${value}</strong>” 吗？</p>`;
}

async function confirmSortOptions(order) {
    return new Promise((resolve) => {
        AdminActionConfirm.open({
            title: '<i class="fa-solid fa-sort"></i> 保存排序',
            messageHtml: '<p>确定要保存当前排序结果吗？</p>',
            confirmText: '确认保存',
            onCancel: () => {
                resolve({ success: false, cancelled: true });
                setTimeout(() => location.reload(), 0);
            },
            onConfirm: async ({ adminPassword }) => {
                try {
                    const response = await Ajax.post('/api/admin/sort_options.php', {
                        items: order,
                        admin_password: adminPassword
                    });

                    if (!response.success) {
                        Toast.error(response.message || '排序保存失败');
                        resolve(response);
                        setTimeout(() => location.reload(), 0);
                        return false;
                    }

                    Toast.success(response.message);
                    resolve(response);
                    return true;
                } catch (error) {
                    Toast.error('排序保存失败');
                    resolve({ success: false });
                    setTimeout(() => location.reload(), 0);
                    return false;
                }
            }
        });
    });
}

async function submitOption() {
    const id = document.getElementById('optionId').value;
    const type = document.getElementById('optionType').value;
    const value = document.getElementById('optionValue').value.trim();

    if (!value) {
        Toast.error('请输入选项值');
        return;
    }

    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-gear"></i> 保存系统选项',
        messageHtml: buildOptionConfirmMessage(type, value),
        confirmText: '确认保存',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/save_option.php', {
                id: id || null,
                type,
                value,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            upsertOptionItem(response.data);
            initSettingsSortables();
            closeModal();
            Toast.success(response.message);
            return true;
        }
    });
}

async function deleteOption(id, value) {
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-trash"></i> 删除系统选项',
        messageHtml: `<p>确定要删除选项 “<strong>${value}</strong>” 吗？</p>`,
        confirmText: '确认删除',
        confirmButtonClass: 'btn-danger',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/delete_option.php', {
                id,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            removeOptionItem(response.data.type, response.data.id);
            Toast.success(response.message);
            return true;
        }
    });
}

async function submitDevelopmentTime() {
    const id = document.getElementById('devTimeId').value;
    const timeName = document.getElementById('devTimeName').value.trim();
    const timeValue = document.getElementById('devTimeValue').value.trim();

    if (!timeName || !timeValue) {
        Toast.error('请填写完整信息');
        return;
    }

    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-calendar-days"></i> 保存发展时间',
        messageHtml: `<p>确定要保存时间节点 “<strong>${timeName}</strong>” 吗？</p>`,
        confirmText: '确认保存',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/save_option.php', {
                id: id || null,
                type: 'development_time',
                value: `${timeName}|${timeValue}`,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            upsertOptionItem(response.data);
            closeDevelopmentTimeModal();
            Toast.success(response.message);
            return true;
        }
    });
}
*/

function buildOptionConfirmMessageSafe(type, value) {
    const label = OPTION_LABELS[type] || 'Option';
    return `<p>Save ${label}: <strong>${value}</strong> ?</p>`;
}

async function confirmSortOptions(order) {
    return new Promise((resolve) => {
        AdminActionConfirm.open({
            title: '<i class="fa-solid fa-sort"></i> Save Order',
            messageHtml: '<p>Save the current sort order?</p>',
            confirmText: 'Save',
            onCancel: () => {
                resolve({ success: false, cancelled: true });
                setTimeout(() => location.reload(), 0);
            },
            onConfirm: async ({ adminPassword }) => {
                try {
                    const response = await Ajax.post('/api/admin/sort_options.php', {
                        items: order,
                        admin_password: adminPassword
                    });

                    if (!response.success) {
                        Toast.error(response.message || 'Save failed');
                        resolve(response);
                        setTimeout(() => location.reload(), 0);
                        return false;
                    }

                    Toast.success(response.message);
                    resolve(response);
                    return true;
                } catch (error) {
                    Toast.error('Save failed');
                    resolve({ success: false });
                    setTimeout(() => location.reload(), 0);
                    return false;
                }
            }
        });
    });
}

async function submitOption() {
    const id = document.getElementById('optionId').value;
    const type = document.getElementById('optionType').value;
    const value = document.getElementById('optionValue').value.trim();

    if (!value) {
        Toast.error('Option value is required');
        return;
    }

    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-gear"></i> Save Option',
        messageHtml: buildOptionConfirmMessageSafe(type, value),
        confirmText: 'Save',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/save_option.php', {
                id: id || null,
                type,
                value,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            upsertOptionItem(response.data);
            initSettingsSortables();
            closeModal();
            Toast.success(response.message);
            return true;
        }
    });
}

async function deleteOption(id, value) {
    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-trash"></i> Delete Option',
        messageHtml: `<p>Delete option <strong>${value}</strong> ?</p>`,
        confirmText: 'Delete',
        confirmButtonClass: 'btn-danger',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/delete_option.php', {
                id,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            removeOptionItem(response.data.type, response.data.id);
            Toast.success(response.message);
            return true;
        }
    });
}

async function submitDevelopmentTime() {
    const id = document.getElementById('devTimeId').value;
    const timeName = document.getElementById('devTimeName').value.trim();
    const timeValue = document.getElementById('devTimeValue').value.trim();

    if (!timeName || !timeValue) {
        Toast.error('Complete the form first');
        return;
    }

    AdminActionConfirm.open({
        title: '<i class="fa-solid fa-calendar-days"></i> Save Timeline',
        messageHtml: `<p>Save timeline item <strong>${timeName}</strong> ?</p>`,
        confirmText: 'Save',
        onConfirm: async ({ adminPassword }) => {
            const response = await Ajax.post('/api/admin/save_option.php', {
                id: id || null,
                type: 'development_time',
                value: `${timeName}|${timeValue}`,
                admin_password: adminPassword
            });

            if (!response.success) {
                Toast.error(response.message);
                return false;
            }

            upsertOptionItem(response.data);
            closeDevelopmentTimeModal();
            Toast.success(response.message);
            return true;
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
