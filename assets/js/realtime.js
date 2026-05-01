/**
 * 实时数据更新模块
 * 路径: /assets/js/realtime.js
 */

class RealtimeUpdater {
    constructor(options = {}) {
        this.interval = options.interval || 5000; // 默认5秒轮询
        this.apiUrl = options.apiUrl || '/api/admin/get_updates.php';
        this.onUpdate = options.onUpdate || (() => {});
        this.onNewPending = options.onNewPending || (() => {});
        this.enabled = false;
        this.timer = null;
        this.lastBatchId = null;
        this.lastPendingCount = null;
        this.isFirstCheck = true;
    }

    start() {
        if (this.enabled) return;
        this.enabled = true;
        this.check(); // 立即检查一次
        this.timer = setInterval(() => this.check(), this.interval);
    }

    stop() {
        if (!this.enabled) return;
        this.enabled = false;
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    async check() {
        if (!this.enabled) return;

        try {
            const joiner = this.apiUrl.includes('?') ? '&' : '?';
            const requestUrl = `${this.apiUrl}${joiner}_t=${Date.now()}`;
            const response = await fetch(requestUrl, {
                method: 'GET',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                if (response.status === 401) {
                    this.stop();
                    window.location.href = '/';
                }
                return;
            }

            const result = await response.json();
            
            if (result.success && result.data) {
                const data = result.data;
                
                // 检测新的待审核申请
                if (!this.isFirstCheck && this.lastBatchId !== data.latest_batch_id && data.latest_batch_id) {
                    this.onNewPending(data);
                    this.showNotification('有新的信息修改申请待审核', 'info');
                }
                
                this.lastBatchId = data.latest_batch_id;
                this.lastPendingCount = data.pending_count;
                this.isFirstCheck = false;
                
                this.onUpdate(data);
            }
        } catch (error) {
            // 静默处理错误
        }
    }

    showNotification(message, type = 'info') {
        // 使用浏览器通知（需要用户授权）
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('生化学院党员信息管理系统', {
                body: message,
                icon: '/assets/images/logo.png',
                badge: '/assets/images/logo.png'
            });
        }
        
        // 同时显示页面内提示
        if (typeof Toast !== 'undefined') {
            Toast.info(message);
        }
    }

    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }
}

// 更新页面元素的辅助函数
function updateCounter(elementId, newValue, animate = true) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const oldValue = parseInt(element.textContent) || 0;
    
    if (oldValue !== newValue) {
        if (animate) {
            element.classList.add('counter-update');
            setTimeout(() => element.classList.remove('counter-update'), 600);
        }
        
        // 数字动画效果
        if (animate && Math.abs(newValue - oldValue) <= 10) {
            animateCounter(element, oldValue, newValue, 500);
        } else {
            element.textContent = newValue;
        }
    }
}

function animateCounter(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            element.textContent = end;
            clearInterval(timer);
        } else {
            element.textContent = Math.round(current);
        }
    }, 16);
}

// 为待审核数量添加脉冲效果
function pulseElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.classList.add('pulse-animation');
        setTimeout(() => element.classList.remove('pulse-animation'), 1000);
    }
}
