if (typeof window.AdminNotificationSystem === 'undefined') {

    function getApiPath() {
        const currentPath = window.location.pathname;
        // Extract the base path up to and including super_admin/
        const match = currentPath.match(/(.*\/super_admin\/)/);
        if (match) {
            return match[1] + 'api/';
        }
        return 'api/';
    }

    class AdminNotificationSystem {
        constructor() {
            if (window.adminNotificationSystemInstance) {
                console.log('🔔 AdminNotificationSystem instance already exists, reusing');
                return window.adminNotificationSystemInstance;
            }

            this.isOpen = false;
            this.notifications = [];
            this.unreadCount = 0;
            this.currentPage = 1;
            this.hasMore = true;
            this.isLoading = false;
            this.refreshInterval = null;
            this.isInitialized = false;
            this.apiPath = getApiPath();

            console.log('🔔 AdminNotificationSystem constructor called');
            console.log('🔔 API Path:', this.apiPath);

            window.adminNotificationSystemInstance = this;
            this.init();
        }

        init() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }

        setup() {
            if (this.isInitialized) {
                console.log('🔔 AdminNotificationSystem already initialized');
                return;
            }

            console.log('🔔 Starting AdminNotificationSystem setup...');

            this.createPopupHTML();
            this.createCSSStyles();

            setTimeout(() => {
                this.bindEvents();
            }, 100);

            this.loadNotifications();
            this.startAutoRefresh();

            this.isInitialized = true;
            console.log('🔔 AdminNotificationSystem setup complete');
        }

        createCSSStyles() {
            if (document.getElementById('admin-notif-styles')) return;

            const style = document.createElement('style');
            style.id = 'admin-notif-styles';
            style.textContent = `
            .notifications-popup {
                position: fixed;
                top: 60px;
                right: 20px;
                width: 400px;
                max-height: 600px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                display: none;
                flex-direction: column;
                z-index: 9999;
            }
            
            .notifications-popup.open {
                display: flex;
            }
            
            @media (max-width: 768px) {
                .notifications-popup {
                    width: calc(100% - 40px);
                    right: 20px;
                    left: auto;
                }
            }
            
            .notification-header {
                padding: 16px;
                border-bottom: 1px solid #f0f0f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(135deg, #2E0D2A, #4a1545);
                border-radius: 12px 12px 0 0;
                color: white;
            }
            
            .notification-title {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
            }
            
            .notification-actions {
                display: flex;
                gap: 8px;
            }
            
            .notification-action-btn {
                background: rgba(255,255,255,0.2);
                border: none;
                color: white;
                padding: 6px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 12px;
                transition: background 0.2s;
            }
            
            .notification-action-btn:hover {
                background: rgba(255,255,255,0.3);
            }
            
            .notification-list {
                overflow-y: auto;
                flex: 1;
            }
            
            .notification-item {
                padding: 14px 16px;
                border-bottom: 1px solid #f5f5f5;
                cursor: pointer;
                transition: background 0.2s;
                display: flex;
                gap: 12px;
            }
            
            .notification-item:hover {
                background: #f9f9f9;
            }
            
            .notification-item.unread {
                background: #f0f4ff;
            }
            
            .notification-icon {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: linear-gradient(135deg, #2E0D2A, #4a1545);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 18px;
                flex-shrink: 0;
            }
            
            .notification-content {
                flex: 1;
                min-width: 0;
            }
            
            .notification-title-text {
                font-weight: 600;
                color: #1a1a1a;
                font-size: 14px;
                margin: 0 0 4px 0;
            }
            
            .notification-message {
                font-size: 13px;
                color: #555;
                margin: 0;
                word-break: break-word;
            }
            
            .notification-time {
                font-size: 12px;
                color: #999;
                margin-top: 4px;
            }
            
            .notification-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                margin-top: 4px;
            }
            
            .badge-high { background: #fee2e2; color: #991b1b; }
            .badge-medium { background: #fef3c7; color: #92400e; }
            .badge-low { background: #d1fae5; color: #065f46; }
            
            .notification-loading {
                padding: 20px;
                text-align: center;
                color: #999;
            }
            
            .notification-empty {
                padding: 40px 20px;
                text-align: center;
                color: #999;
            }
            
            .notification-footer {
                padding: 12px 16px;
                border-top: 1px solid #f0f0f0;
                text-align: center;
            }
            
            .notification-footer-btn {
                color: #2E0D2A;
                text-decoration: none;
                font-size: 13px;
                font-weight: 500;
            }
            
            .notification-footer-btn:hover {
                text-decoration: underline;
            }
            
            .notification-badge-icon {
                position: absolute;
                top: 0;
                right: 0;
                background: #ef4444;
                color: white;
                border-radius: 50%;
                width: 22px;
                height: 22px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: 600;
                min-width: 22px;
            }
        `;
            document.head.appendChild(style);
        }

        createPopupHTML() {
            const existingPopup = document.getElementById('adminNotificationsPopup');
            if (existingPopup) {
                existingPopup.remove();
            }

            const popup = document.createElement('div');
            popup.className = 'notifications-popup';
            popup.id = 'adminNotificationsPopup';
            popup.innerHTML = `
            <div class="notification-header">
                <h3 class="notification-title">Notifications</h3>
                <div class="notification-actions">
                    <button class="notification-action-btn" id="markAllReadBtn">
                        <i class="fas fa-check-double"></i> All Read
                    </button>
                    <button class="notification-action-btn" id="refreshNotifBtn">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="notification-list" id="notificationListAdmin">
                <div class="notification-loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading notifications...
                </div>
            </div>
            <div class="notification-footer">
                <a href="pages/notifications.php" class="notification-footer-btn">View All</a>
            </div>
        `;

            document.body.appendChild(popup);
        }

        bindEvents() {
            const popup = document.getElementById('adminNotificationsPopup');
            const notificationList = document.getElementById('notificationListAdmin');
            const markAllBtn = document.getElementById('markAllReadBtn');
            const refreshBtn = document.getElementById('refreshNotifBtn');

            if (!popup) {
                console.error('Notification popup not found');
                return;
            }

            // Mark all as read
            if (markAllBtn) {
                markAllBtn.addEventListener('click', () => this.markAllAsRead());
            }

            // Refresh
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => this.loadNotifications());
            }

            // Click outside to close
            document.addEventListener('click', (e) => {
                if (!popup.contains(e.target) && e.target.id !== 'notificationBellBtn') {
                    this.close();
                }
            });
        }

        async loadNotifications() {
            if (this.isLoading) return;

            this.isLoading = true;
            const listEl = document.getElementById('notificationListAdmin');

            try {
                const url = this.apiPath + 'notifications.php?action=list&limit=10&offset=0';
                console.log('[Notification] Loading from:', url);
                console.log('[Notification] API path:', this.apiPath);

                const response = await fetch(url);
                console.log('[Notification] Response status:', response.status);

                if (!response.ok) {
                    const text = await response.text();
                    console.error('[Notification] Error response:', text);
                    throw new Error(`API error: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    this.notifications = result.notifications || [];
                    this.renderNotifications();
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
                if (listEl) {
                    listEl.innerHTML = '<div class="notification-empty">Failed to load notifications</div>';
                }
            } finally {
                this.isLoading = false;
            }
        }

        renderNotifications() {
            const listEl = document.getElementById('notificationListAdmin');
            if (!listEl) return;

            if (this.notifications.length === 0) {
                listEl.innerHTML = '<div class="notification-empty">No notifications</div>';
                return;
            }

            listEl.innerHTML = this.notifications.map(notif => `
            <div class="notification-item ${notif.status === 'unread' ? 'unread' : ''}" data-id="${notif.id}">
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="notification-content">
                    <p class="notification-title-text">${this.escapeHtml(notif.title)}</p>
                    <p class="notification-message">${this.escapeHtml(notif.message)}</p>
                    <span class="badge-${notif.priority || 'medium'} notification-badge">${notif.priority || 'medium'}</span>
                    <div class="notification-time">${notif.time_ago || 'just now'}</div>
                </div>
            </div>
        `).join('');

            // Add click handlers
            listEl.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', () => {
                    const id = item.dataset.id;
                    this.markAsRead(id);
                });
            });
        }

        async markAsRead(notificationId) {
            try {
                const response = await fetch(this.apiPath + 'notifications.php?action=mark_read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `notification_id=${encodeURIComponent(notificationId)}`
                });

                const result = await response.json();
                if (result.success) {
                    this.loadNotifications();
                }
            } catch (error) {
                console.error('Error marking as read:', error);
            }
        }

        async markAllAsRead() {
            try {
                const response = await fetch(this.apiPath + 'notifications.php?action=mark_all_read', {
                    method: 'POST'
                });

                const result = await response.json();
                if (result.success) {
                    this.loadNotifications();
                }
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }

        startAutoRefresh() {
            this.refreshInterval = setInterval(() => {
                if (this.isOpen) {
                    this.loadNotifications();
                }
            }, 30000); // Every 30 seconds
        }

        open() {
            this.isOpen = true;
            const popup = document.getElementById('adminNotificationsPopup');
            if (popup) {
                popup.classList.add('open');
            }
        }

        close() {
            this.isOpen = false;
            const popup = document.getElementById('adminNotificationsPopup');
            if (popup) {
                popup.classList.remove('open');
            }
        }

        toggle() {
            this.isOpen ? this.close() : this.open();
        }

        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    }

    window.AdminNotificationSystem = AdminNotificationSystem;

    // Auto-initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        window.adminNotificationSystem = new AdminNotificationSystem();

        // Create notification bell button in navbar if not exists
        const navbar = document.querySelector('.navbar') || document.querySelector('.topbar');
        if (navbar && !document.getElementById('notificationBellBtn')) {
            const bellBtn = document.createElement('button');
            bellBtn.id = 'notificationBellBtn';
            bellBtn.className = 'topbar-bell';
            bellBtn.innerHTML = `
            <i class="fas fa-bell"></i>
            <span class="notification-badge-icon" id="notificationBadge">0</span>
        `;
            bellBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                window.adminNotificationSystem.toggle();
            });

            navbar.appendChild(bellBtn);
        }
    });

}
