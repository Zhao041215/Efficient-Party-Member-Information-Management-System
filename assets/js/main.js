/**
 * 生化学院党员信息管理系统 - 主JS文件
 * 增强版：支持鸿蒙系统侧边栏显示
 */

// ========== 设备检测模块 ==========
const DeviceDetector = {
    // 检测是否为移动设备（更严格的判断，只在真正的移动设备上返回true）
    isMobile() {
        // 方法1: 检测User-Agent（包括鸿蒙系统）
        const mobileUA = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|HarmonyOS|OpenHarmony/i.test(navigator.userAgent);
        
        // 方法2: 检测屏幕宽度（只有小于等于576px才认为是移动设备）
        const narrowScreen = window.innerWidth <= 576;
        
        // 方法3: 检测触摸支持
        const hasTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0);
        
        // 综合判断：屏幕小于576px，或者是移动UA且有触摸支持
        return narrowScreen || (mobileUA && hasTouch);
    },
    
    // 检测是否为Edge浏览器
    isEdge() {
        return /Edg/i.test(navigator.userAgent);
    },
    
    // 获取设备信息
    getDeviceInfo() {
        return {
            userAgent: navigator.userAgent,
            screenWidth: window.innerWidth,
            screenHeight: window.innerHeight,
            hasTouch: 'ontouchstart' in window,
            maxTouchPoints: navigator.maxTouchPoints,
            devicePixelRatio: window.devicePixelRatio,
            isMobile: this.isMobile()
        };
    },
    PHONE_MAX_WIDTH: 576,
    TABLET_COLLAPSE_MAX_WIDTH: 820,

    hasTouch() {
        return ('ontouchstart' in window) ||
            (navigator.maxTouchPoints > 0) ||
            (navigator.msMaxTouchPoints > 0);
    },

    isIPadOS() {
        return navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    },

    getDeviceCategory() {
        const userAgent = navigator.userAgent || '';
        const isIPhone = /iPhone|iPod/i.test(userAgent);
        const isIPad = /iPad/i.test(userAgent) || this.isIPadOS();
        const isAndroid = /Android/i.test(userAgent);
        const isAndroidPhone = isAndroid && /Mobile/i.test(userAgent);
        const isAndroidTablet = isAndroid && !/Mobile/i.test(userAgent);
        const isOtherPhone = /Windows Phone|IEMobile|BlackBerry|Opera Mini|webOS/i.test(userAgent);

        if (isIPhone || isAndroidPhone || isOtherPhone) {
            return 'phone';
        }

        if (isIPad || isAndroidTablet) {
            return 'tablet';
        }

        return 'desktop';
    },

    isSidebarCollapsible(category = this.getDeviceCategory()) {
        if (category === 'phone') {
            return true;
        }

        if (category === 'tablet') {
            return window.innerWidth <= this.TABLET_COLLAPSE_MAX_WIDTH;
        }

        return false;
    },

    isEdge() {
        return /Edg/i.test(navigator.userAgent);
    },

    isMobile() {
        return this.getDeviceCategory() === 'phone';
    },

    getDeviceInfo() {
        const category = this.getDeviceCategory();

        return {
            userAgent: navigator.userAgent,
            screenWidth: window.innerWidth,
            screenHeight: window.innerHeight,
            hasTouch: this.hasTouch(),
            maxTouchPoints: navigator.maxTouchPoints,
            devicePixelRatio: window.devicePixelRatio,
            category,
            isMobile: category === 'phone',
            isSidebarCollapsible: this.isSidebarCollapsible(category)
        };
    }
};

// Toast提示
const Toast = {
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', duration = 3000) {
        this.init();
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icons = {
            success: '✔',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${message}</span>
        `;
        
        this.container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    
    success(message, duration) {
        this.show(message, 'success', duration);
    },
    
    error(message, duration) {
        this.show(message, 'error', duration);
    },
    
    warning(message, duration) {
        this.show(message, 'warning', duration);
    },
    
    info(message, duration) {
        this.show(message, 'info', duration);
    }
};

// 模态框
const Modal = {
    show(options) {
        const { title, content, onConfirm, onCancel, confirmText = '确定', cancelText = '取消', showCancel = true } = options;
        
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        
        overlay.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                <div class="modal-footer">
                    ${showCancel ? `<button class="btn btn-secondary modal-cancel">${cancelText}</button>` : ''}
                    <button class="btn btn-primary modal-confirm">${confirmText}</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        requestAnimationFrame(() => overlay.classList.add('active'));
        
        overlay.querySelector('.modal-close').addEventListener('click', () => {
            this.close(overlay);
            if (onCancel) onCancel();
        });
        
        const cancelBtn = overlay.querySelector('.modal-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                this.close(overlay);
                if (onCancel) onCancel();
            });
        }
        
        overlay.querySelector('.modal-confirm').addEventListener('click', () => {
            if (onConfirm) {
                const result = onConfirm();
                if (result !== false) {
                    this.close(overlay);
                }
            } else {
                this.close(overlay);
            }
        });
        
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                this.close(overlay);
                if (onCancel) onCancel();
            }
        });
        
        return overlay;
    },
    
    close(overlay) {
        overlay.classList.remove('active');
        setTimeout(() => overlay.remove(), 300);
    },
    
    confirm(message, onConfirm, onCancel) {
        return this.show({
            title: '确认操作',
            content: `<p>${message}</p>`,
            onConfirm,
            onCancel
        });
    },
    
    alert(message, onClose) {
        return this.show({
            title: '提示',
            content: `<p>${message}</p>`,
            showCancel: false,
            onConfirm: onClose
        });
    }
};

// CSRF Token管理
const AdminActionConfirm = {
    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    },

    buildAccountListHtml(accountList = [], title = '本次涉及账号') {
        if (!Array.isArray(accountList) || accountList.length === 0) {
            return '';
        }

        const items = accountList.map((item) => {
            if (typeof item === 'string') {
                return `<li>${this.escapeHtml(item)}</li>`;
            }

            const parts = [this.escapeHtml(item.username || '-')];
            if (item.name) {
                parts.push(this.escapeHtml(item.name));
            }
            if (item.role) {
                parts.push(this.escapeHtml(item.role));
            }

            return `<li>${parts.join(' / ')}</li>`;
        }).join('');

        return `
            <div class="form-group" style="margin-top: 16px;">
                <label class="form-label">${this.escapeHtml(title)}</label>
                <div style="max-height: 180px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 12px; background: #fafafa;">
                    <ul style="margin: 0; padding-left: 18px;">${items}</ul>
                </div>
            </div>
        `;
    },

    open(options) {
        const {
            title,
            messageHtml = '',
            confirmText = '确认',
            confirmButtonClass = 'btn-primary',
            accountList = [],
            accountListTitle = '本次涉及账号',
            extraContentHtml = '',
            onConfirm,
            onCancel
        } = options;

        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal" style="max-width: 520px;">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    <button class="modal-close" type="button">&times;</button>
                </div>
                <div class="modal-body">
                    ${messageHtml}
                    ${extraContentHtml}
                    ${this.buildAccountListHtml(accountList, accountListTitle)}
                    <div class="form-group" style="margin-top: 16px;">
                        <label class="form-label required">请输入您的管理员密码以确认操作</label>
                        <input type="password" class="form-control" data-admin-password-input placeholder="输入当前密码" autocomplete="current-password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-cancel-btn>取消</button>
                    <button class="btn ${confirmButtonClass}" type="button" data-confirm-btn>${confirmText}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        requestAnimationFrame(() => overlay.classList.add('active'));

        const passwordInput = overlay.querySelector('[data-admin-password-input]');
        const confirmBtn = overlay.querySelector('[data-confirm-btn]');
        const close = (triggerCancel = false) => {
            Modal.close(overlay);
            if (triggerCancel && typeof onCancel === 'function') {
                onCancel();
            }
        };

        setTimeout(() => passwordInput.focus(), 120);

        overlay.querySelector('.modal-close').addEventListener('click', () => close(true));
        overlay.querySelector('[data-cancel-btn]').addEventListener('click', () => close(true));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                close(true);
            }
        });

        passwordInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmBtn.click();
            }
        });

        confirmBtn.addEventListener('click', async () => {
            const adminPassword = passwordInput.value.trim();
            if (!adminPassword) {
                Toast.warning('请输入管理员密码');
                passwordInput.focus();
                return;
            }

            confirmBtn.disabled = true;
            const originalHtml = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 处理中...';

            try {
                const result = await onConfirm({
                    adminPassword,
                    overlay,
                    passwordInput,
                    confirmBtn
                });

                if (result !== false) {
                    close();
                    return;
                }
            } catch (error) {
                Toast.error('网络错误，请稍后重试');
            }

            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalHtml;
            passwordInput.focus();
        });

        return overlay;
    }
};

const CSRF = {
    token: null,

    // 获取CSRF Token
    getToken() {
        if (this.token) {
            return this.token;
        }

        // 从meta标签获取
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            this.token = metaToken.getAttribute('content');
            return this.token;
        }

        // 从cookie获取
        const cookieToken = this.getTokenFromCookie();
        if (cookieToken) {
            this.token = cookieToken;
            return this.token;
        }

        return null;
    },

    // 从Cookie获取Token
    getTokenFromCookie() {
        const name = 'csrf_token=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i].trim();
            if (c.indexOf(name) === 0) {
                return c.substring(name.length, c.length);
            }
        }
        return null;
    },

    // 设置Token
    setToken(token) {
        this.token = token;
    }
};

// AJAX请求封装（增强版，自动携带CSRF Token）
const Ajax = {
    async request(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const mergedOptions = { ...defaultOptions, ...options };

        // 为POST/PUT/DELETE请求自动添加CSRF Token
        if (['POST', 'PUT', 'DELETE'].includes(mergedOptions.method.toUpperCase())) {
            const csrfToken = CSRF.getToken();
            if (csrfToken) {
                // 添加到HTTP Header
                mergedOptions.headers['X-CSRF-Token'] = csrfToken;

                // 如果是JSON body，也添加到body中
                if (mergedOptions.body && typeof mergedOptions.body === 'object') {
                    mergedOptions.body.csrf_token = csrfToken;
                }
            }
        }

        if (mergedOptions.body && typeof mergedOptions.body === 'object') {
            mergedOptions.body = JSON.stringify(mergedOptions.body);
        }

        try {
            const response = await fetch(url, mergedOptions);
            const responseText = await response.text();
            let data;
            try {
                data = responseText ? JSON.parse(responseText) : {};
            } catch (parseError) {
                const detail = responseText ? responseText.replace(/<[^>]*>/g, '').trim().slice(0, 300) : '';
                throw new Error(`服务器返回 ${response.status} 错误${detail ? `：${detail}` : ''}`);
            }

            if (!response.ok && data.success !== false) {
                data.success = false;
                data.message = data.message || `服务器返回 ${response.status} 错误`;
            }

            if (!data.success && data.message && data.message.includes('CSRF')) {
                Toast.error('安全验证失败，请刷新页面后重试');
            }

            return data;
        } catch (error) {
            throw error;
        }
    },

    get(url, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        return this.request(fullUrl);
    },

    post(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: data
        });
    },

    async upload(url, formData) {
        try {
            // 为FormData添加CSRF Token
            const csrfToken = CSRF.getToken();
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken || ''
                },
                body: formData
            });

            const responseText = await response.text();
            let data;
            try {
                data = responseText ? JSON.parse(responseText) : {};
            } catch (parseError) {
                const detail = responseText ? responseText.replace(/<[^>]*>/g, '').trim().slice(0, 300) : '';
                throw new Error(`服务器返回 ${response.status} 错误${detail ? `：${detail}` : ''}`);
            }

            if (!response.ok && data.success !== false) {
                data.success = false;
                data.message = data.message || `服务器返回 ${response.status} 错误`;
            }

            // 如果返回CSRF错误，提示用户刷新页面
            if (!data.success && data.message && data.message.includes('CSRF')) {
                Toast.error('安全验证失败，请刷新页面后重试');
            }

            return data;
        } catch (error) {
            throw error;
        }
    }
};

window.Toast = Toast;
window.Modal = Modal;
window.AdminActionConfirm = AdminActionConfirm;
window.Ajax = Ajax;

// 表单验证
const Validator = {
    validateIdCard(idCard) {
        if (!/^\d{17}[\dXx]$/.test(idCard)) {
            return false;
        }
        
        const weight = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        const checkCode = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        
        let sum = 0;
        for (let i = 0; i < 17; i++) {
            sum += parseInt(idCard[i]) * weight[i];
        }
        
        return idCard[17].toUpperCase() === checkCode[sum % 11];
    },
    
    validatePhone(phone) {
        return /^1[3-9]\d{9}$/.test(phone);
    },
    
    validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },
    
    getAgeFromIdCard(idCard) {
        if (!this.validateIdCard(idCard)) return null;
        
        const birthYear = parseInt(idCard.substr(6, 4));
        const birthMonth = parseInt(idCard.substr(10, 2));
        const birthDay = parseInt(idCard.substr(12, 2));
        
        const today = new Date();
        let age = today.getFullYear() - birthYear;
        
        const birthDate = new Date(birthYear, birthMonth - 1, birthDay);
        if (today < new Date(today.getFullYear(), birthMonth - 1, birthDay)) {
            age--;
        }
        
        return age;
    },
    
    getBirthDateFromIdCard(idCard) {
        if (!this.validateIdCard(idCard)) return null;
        
        const year = idCard.substr(6, 4);
        const month = idCard.substr(10, 2);
        const day = idCard.substr(12, 2);
        
        return `${year}-${month}-${day}`;
    }
};

// 工具函数
const Utils = {
    formatDate(date, format = 'YYYY-MM-DD') {
        if (!date) return '';
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        
        return format
            .replace('YYYY', year)
            .replace('MM', month)
            .replace('DD', day);
    },
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    getUrlParams() {
        const params = {};
        const searchParams = new URLSearchParams(window.location.search);
        for (const [key, value] of searchParams) {
            params[key] = value;
        }
        return params;
    },
    
    setUrlParams(params) {
        const url = new URL(window.location);
        Object.keys(params).forEach(key => {
            if (params[key]) {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        window.history.pushState({}, '', url);
    },
    
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            Toast.success('已复制到剪贴板');
        } catch (err) {
            Toast.error('复制失败');
        }
    },
    
    downloadFile(url, filename) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename || '';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    },
    
    showLoading(container) {
        container.innerHTML = `
            <div class="loading">
                <div class="loading-spinner"></div>
            </div>
        `;
    },
    
    showEmpty(container, message = '暂无数据') {
        container.innerHTML = `
            <div class="empty-state">
                <i>🔭</i>
                <h4>${message}</h4>
            </div>
        `;
    }
};

// 表格全选功能
function initTableCheckbox(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const selectAll = table.querySelector('.select-all');
    const checkboxes = table.querySelectorAll('.row-checkbox');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }
    
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = Array.from(checkboxes).every(c => c.checked);
            const someChecked = Array.from(checkboxes).some(c => c.checked);
            if (selectAll) {
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            }
        });
    });
}

// 获取选中的行
function getSelectedRows(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return [];
    
    const checkboxes = table.querySelectorAll('.row-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

// 退出登录
async function logout() {
    if (typeof confirmLogout === 'function') {
        confirmLogout();
    } else {
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
                }
            } catch (error) {
                window.location.replace('/index.php');
            }
        }
    }
}

// 初始化Select2
function initSelect2(selector, options = {}) {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(selector).select2({
            placeholder: '请选择',
            allowClear: true,
            language: 'zh-CN',
            ...options
        });
    }
}

// ========== 增强的移动端菜单控制 ==========
function initLegacyMobileMenu() {
function initResponsiveSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    // 检查必要元素
    if (!menuToggle || !sidebar || !mainContent) {
        return;
    }

    // 根据设备类型应用初始样式
    function applyDeviceStyles() {
        const isMobile = DeviceDetector.isMobile();

        if (isMobile) {
            // 移动端：显示菜单按钮，隐藏侧边栏
            menuToggle.style.display = 'block';
            sidebar.classList.remove('active');
            mainContent.classList.remove('shifted');
            
            // 强制设置移动端样式
            sidebar.style.position = 'fixed';
            sidebar.style.left = '-240px';
            sidebar.style.width = '240px';
            sidebar.style.height = '100vh';
            sidebar.style.top = '0';
            sidebar.style.zIndex = '2000';
            sidebar.style.transition = 'left 0.3s ease';
            
            mainContent.style.marginLeft = '0';
            mainContent.style.transition = 'transform 0.3s ease';
        } else {
            // 桌面端：隐藏菜单按钮，显示侧边栏
            menuToggle.style.display = 'none';
            sidebar.classList.remove('active');
            mainContent.classList.remove('shifted');
            
            // 强制设置桌面端样式
            sidebar.style.position = 'fixed';
            sidebar.style.left = '0';
            sidebar.style.width = '240px';
            sidebar.style.height = '100vh';
            sidebar.style.top = '0';
            sidebar.style.zIndex = '1000';
            
            mainContent.style.marginLeft = '240px';
            mainContent.style.transform = 'none';
        }
    }
    
    // 切换侧边栏
    function toggleSidebar(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        const isActive = sidebar.classList.toggle('active');

        if (isActive) {
            sidebar.style.left = '0';
            mainContent.classList.add('shifted');
            mainContent.style.transform = 'translateX(240px)';
        } else {
            sidebar.style.left = '-240px';
            mainContent.classList.remove('shifted');
            mainContent.style.transform = 'none';
        }
    }
    
    // 关闭侧边栏
    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebar.style.left = '-240px';
        mainContent.classList.remove('shifted');
        mainContent.style.transform = 'none';
    }
    
    // 初始化样式
    applyDeviceStyles();
    
    // 绑定菜单按钮事件
    menuToggle.addEventListener('click', toggleSidebar);
    menuToggle.addEventListener('touchstart', toggleSidebar, { passive: false });
    
    // 点击侧边栏外部关闭（仅移动端）
    document.addEventListener('click', function(e) {
        if (!DeviceDetector.isMobile()) return;
        
        if (sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                closeSidebar();
            }
        }
    });
    
    // 触摸事件支持（仅移动端）
    document.addEventListener('touchstart', function(e) {
        if (!DeviceDetector.isMobile()) return;
        
        if (sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                closeSidebar();
            }
        }
    }, { passive: true });
    
    // 监听窗口大小变化
    const handleResize = Utils.debounce(() => {
        applyDeviceStyles();
    }, 250);

    window.addEventListener('resize', handleResize);
    window.addEventListener('orientationchange', handleResize);
}

// 页面加载完成后执行
function initMobileMenu() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    if (!menuToggle || !sidebar || !body) {
        return;
    }

    function isCollapsibleLayout() {
        return body.classList.contains('sidebar-collapsible');
    }

    function syncLayoutState() {
        const category = DeviceDetector.getDeviceCategory();
        const collapsible = DeviceDetector.isSidebarCollapsible(category);
        const isOpen = collapsible && body.classList.contains('sidebar-open');

        body.classList.remove('device-desktop', 'device-tablet', 'device-phone');
        body.classList.add(`device-${category}`);
        body.classList.toggle('sidebar-collapsible', collapsible);

        if (!collapsible) {
            body.classList.remove('sidebar-open');
        }

        menuToggle.setAttribute('aria-hidden', collapsible ? 'false' : 'true');
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        sidebar.setAttribute('aria-hidden', collapsible && !isOpen ? 'true' : 'false');

        if (sidebarOverlay) {
            sidebarOverlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }
    }

    function openSidebar() {
        if (!isCollapsibleLayout()) {
            return;
        }

        body.classList.add('sidebar-open');
        syncLayoutState();
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
        syncLayoutState();
    }

    function toggleSidebar(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (!isCollapsibleLayout()) {
            return;
        }

        if (body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    syncLayoutState();

    menuToggle.addEventListener('click', toggleSidebar);

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('click', function(event) {
        if (!isCollapsibleLayout() || !body.classList.contains('sidebar-open')) {
            return;
        }

        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            closeSidebar();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && isCollapsibleLayout() && body.classList.contains('sidebar-open')) {
            closeSidebar();
        }
    });

    const handleResize = Utils.debounce(() => {
        syncLayoutState();
    }, 250);

    window.addEventListener('resize', handleResize);
    window.addEventListener('orientationchange', handleResize);
}

}

function initMobileMenu() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    if (!menuToggle || !sidebar || !body) {
        return;
    }

    function isCollapsibleLayout() {
        return body.classList.contains('sidebar-collapsible');
    }

    function syncLayoutState() {
        const category = DeviceDetector.getDeviceCategory();
        const collapsible = DeviceDetector.isSidebarCollapsible(category);
        const isOpen = collapsible && body.classList.contains('sidebar-open');

        body.classList.remove('device-desktop', 'device-tablet', 'device-phone');
        body.classList.add(`device-${category}`);
        body.classList.toggle('sidebar-collapsible', collapsible);

        if (!collapsible) {
            body.classList.remove('sidebar-open');
        }

        menuToggle.setAttribute('aria-hidden', collapsible ? 'false' : 'true');
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        sidebar.setAttribute('aria-hidden', collapsible && !isOpen ? 'true' : 'false');

        if (sidebarOverlay) {
            sidebarOverlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }
    }

    function openSidebar() {
        if (!isCollapsibleLayout()) {
            return;
        }

        body.classList.add('sidebar-open');
        syncLayoutState();
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
        syncLayoutState();
    }

    function toggleSidebar(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (!isCollapsibleLayout()) {
            return;
        }

        if (body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    syncLayoutState();
    body.classList.add('sidebar-ready');
    menuToggle.addEventListener('click', toggleSidebar);

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('click', function(event) {
        if (!isCollapsibleLayout() || !body.classList.contains('sidebar-open')) {
            return;
        }

        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            closeSidebar();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && isCollapsibleLayout() && body.classList.contains('sidebar-open')) {
            closeSidebar();
        }
    });

    const handleResize = Utils.debounce(() => {
        syncLayoutState();
    }, 250);

    window.addEventListener('resize', handleResize);
    window.addEventListener('orientationchange', handleResize);
}

function initAdminPendingBadgeUpdater() {
    if (window.__adminPendingBadgeUpdaterStarted) {
        return;
    }

    const body = document.body;
    const role = body ? body.dataset.userRole : '';
    if (!role || !['admin', 'superadmin'].includes(role)) {
        return;
    }

    const sidebarBadge = document.getElementById('sidebarPendingAuditBadge');
    const pendingTabBadge = document.getElementById('pendingBadge');
    let timer = null;
    let lastCount = null;

    function animateBadge(element) {
        if (!element) {
            return;
        }

        element.classList.remove('badge-bounce');
        void element.offsetWidth;
        element.classList.add('badge-bounce');
        window.setTimeout(() => element.classList.remove('badge-bounce'), 650);
    }

    function applyCount(count) {
        [sidebarBadge, pendingTabBadge].forEach(element => {
            if (!element) {
                return;
            }

            element.textContent = count;
            if (element === sidebarBadge) {
                element.classList.toggle('d-none', count <= 0);
            }
        });

        if (lastCount !== null && count > lastCount) {
            animateBadge(sidebarBadge);
            animateBadge(pendingTabBadge);
            pulseElement('pendingCard');
        }

        if (document.getElementById('pendingCount')) {
            updateCounter('pendingCount', count, false);
        }

        lastCount = count;
    }

    async function refreshPendingCount() {
        try {
            const response = await fetch(`/api/admin/get_updates.php?_t=${Date.now()}`, {
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

            applyCount(parseInt(result.data.pending_count, 10) || 0);
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

        refreshPendingCount();
        timer = window.setInterval(refreshPendingCount, 5000);
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

    window.addEventListener('focus', refreshPendingCount);
    window.addEventListener('pageshow', refreshPendingCount);
}

document.addEventListener('DOMContentLoaded', function() {
    // 初始化CSRF Token
    CSRF.getToken();

    // 初始化所有表格的复选框功能
    document.querySelectorAll('table[id]').forEach(table => {
        initTableCheckbox(table.id);
    });

    // 添加动画样式
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    `;
    document.head.appendChild(style);

    initMobileMenu();
    initAdminPendingBadgeUpdater();
});
