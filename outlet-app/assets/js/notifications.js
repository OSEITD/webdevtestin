
if (typeof window.NotificationSystem === 'undefined') {

function getApiPath() {
    const currentPath = window.location.pathname;
    
    if (currentPath.includes('/pages/')) {
        return '../api/notifications/';
    }
    
    return 'api/notifications/';
}

class NotificationSystem {
    constructor() {
        
        if (window.notificationSystemInstance) {
            console.log('🔔 NotificationSystem instance already exists, reusing existing');
            return window.notificationSystemInstance;
        }
        
        this.isOpen = false;
        this.notifications = [];
        this.unreadCount = 0;
        this.currentPage = 1;
        this.hasMore = true;
        this.isLoading = false;
        this.refreshInterval = null;
        this.isInitialized = false;
        
        console.log('🔔 NotificationSystem constructor called');
        
        
        window.notificationSystemInstance = this;
        
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
            console.log('🔔 NotificationSystem already initialized, skipping setup');
            return;
        }
        
        console.log('🔔 Starting NotificationSystem setup...');
        
        
        this.createPopupHTML();
        
        
        setTimeout(() => {
            this.bindEvents();
        }, 100);
        
        this.loadNotifications();
        this.startAutoRefresh();
        
        this.isInitialized = true;
        console.log('🔔 NotificationSystem setup complete');
    }
    
    createPopupHTML() {
        
        const existingPopup = document.getElementById('notificationsPopup');
        if (existingPopup) {
            existingPopup.remove();
            console.log('🔔 Removed existing notification popup');
        }
        
        const popup = document.createElement('div');
        popup.className = 'notifications-popup';
        popup.id = 'notificationsPopup';
        popup.innerHTML = `
            <div class="notification-header">
                <h3 class="notification-title">Notifications</h3>
                <div class="notification-actions">
                    <button class="notification-action-btn" id="markAllRead">
                        <i class="fas fa-check-double"></i> All Read
                    </button>
                    <button class="notification-action-btn" id="refreshNotifications">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="notification-list" id="notificationList">
                <div class="notification-loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading notifications...
                </div>
            </div>
            <div class="notification-footer">
                <button class="view-all-btn" id="viewAllNotifications">
                    View All Notifications
                </button>
            </div>
        `;
        
        
        popup.style.position = 'fixed';
        popup.style.zIndex = '99999';
        
        
        this.positionPopup(popup);
        
        
        document.body.appendChild(popup);
        console.log('✅ Professional notification popup created with intelligent positioning');
    }
    
    positionPopup(popup) {
        
        const notificationBtn = document.querySelector('.notification-btn, #notificationButton');
        
        if (notificationBtn) {
            const rect = notificationBtn.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const popupWidth = 380;
            const popupMaxHeight = 600;
            
            
            let top = rect.bottom + 15; 
            let right = viewportWidth - rect.right;
            
            
            if (top + popupMaxHeight > viewportHeight - 20) {
                
                if (rect.top > popupMaxHeight + 20) {
                    top = rect.top - popupMaxHeight - 15;
                } else {
                    
                    top = Math.max(20, (viewportHeight - popupMaxHeight) / 2);
                }
            }
            
            
            if (right + popupWidth > viewportWidth - 20) {
                right = 20; 
            }
            
            
            top = Math.max(20, top);
            right = Math.max(20, right);
            
            popup.style.top = `${top}px`;
            popup.style.right = `${right}px`;
            
            console.log(`📍 Positioned popup at top: ${top}px, right: ${right}px`);
        } else {
            
            popup.style.top = '70px';
            popup.style.right = '20px';
            console.log('📍 Using fallback positioning');
        }
        
        
        this.addResponsivePositioning(popup);
    }
    
    addResponsivePositioning(popup) {
        const adjustPosition = () => {
            const viewportWidth = window.innerWidth;
            if (viewportWidth <= 768) {
                
                popup.style.left = '16px';
                popup.style.right = '16px';
                popup.style.width = 'auto';
                popup.style.maxWidth = 'none';
                popup.style.top = '70px';
            } else {
                
                if (!adjustPosition._resizing) {
                    adjustPosition._resizing = true;
                    setTimeout(() => {
                        this.positionPopup(popup);
                        adjustPosition._resizing = false;
                    }, 0);
                }
            }
        };
        
        window.addEventListener('resize', adjustPosition);
        
        const viewportWidth = window.innerWidth;
        if (viewportWidth <= 768) {
            adjustPosition();
        }
    }
    
    bindEvents() {
        console.log('🔔 Starting notification button binding...');
        
        
        const selectors = ['.notification-btn', '#notificationButton', 'button.notification-btn', 'button#notificationButton'];
        let button = null;
        
        for (const selector of selectors) {
            button = document.querySelector(selector);
            if (button) {
                console.log(`✅ Found notification button with selector: ${selector}`, button);
                break;
            }
        }
        
        if (!button) {
            console.error('❌ Notification button not found with any selector');
            console.log('Available buttons:', document.querySelectorAll('button'));
            
            
            setTimeout(() => {
                console.log('🔄 Retrying notification button binding...');
                this.bindEvents();
            }, 1000);
            return;
        }
        
        
        button.style.pointerEvents = 'auto';
        button.style.cursor = 'pointer';
        button.style.zIndex = '10';
        button.style.position = 'relative';
        
        
        const newBtn = button.cloneNode(true);
        button.parentNode.replaceChild(newBtn, button);
        
        
        newBtn.style.pointerEvents = 'auto';
        newBtn.style.cursor = 'pointer';
        newBtn.style.zIndex = '10';
        newBtn.style.position = 'relative';
        
        
        newBtn.addEventListener('click', (e) => {
            console.log('🔔 Notification button clicked!');
            console.log('Event:', e);
            e.preventDefault();
            e.stopPropagation();
            
            
            const popup = document.getElementById('notificationsPopup');
            console.log('Popup exists?', popup ? 'YES' : 'NO');
            if (popup) {
                console.log('Popup classes:', popup.className);
                console.log('Popup style display:', popup.style.display);
            }
            
            this.togglePopup();
        });
        
        
        newBtn.style.cursor = 'pointer';
        newBtn.title = 'Click to view notifications';
        
        console.log('✅ Notification button event listener added successfully');
        
        
        this.bindPopupEvents();
    }
    
    bindPopupEvents() {
        console.log('🔔 Binding popup events...');
        
        
        document.addEventListener('click', (e) => {
            const popup = document.getElementById('notificationsPopup');
            const notificationBtn = document.querySelector('.notification-btn, #notificationButton');
            
            if (this.isOpen && popup && !popup.contains(e.target) && e.target !== notificationBtn && !e.target.closest('.notification-btn')) {
                console.log('🔔 Clicking outside popup, closing...');
                this.closePopup();
            }
        });
        
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                console.log('🔔 Escape key pressed, closing popup...');
                this.closePopup();
            }
        });
        
        
        setTimeout(() => {
            const markAllBtn = document.getElementById('markAllRead');
            if (markAllBtn) {
                markAllBtn.addEventListener('click', () => {
                    console.log('🔔 Mark all as read clicked');
                    this.markAllAsRead();
                });
            }
            
            
            const refreshBtn = document.getElementById('refreshNotifications');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    console.log('🔔 Refresh notifications clicked');
                    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    this.loadNotifications(true);
                    setTimeout(() => {
                        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                    }, 1000);
                });
            }
        }, 100);
    }
    
    togglePopup() {
        console.log('🔔 Toggle popup called, isOpen:', this.isOpen);
        const popup = document.getElementById('notificationsPopup');

        if (!popup) {
            console.error('❌ Notification popup not found! Creating it now...');
            this.createPopupHTML();
            
            setTimeout(() => this.togglePopup(), 100);
            return;
        }

        console.log('Popup element found:', popup);
        console.log('Popup classes before toggle:', popup.className);
        console.log('Popup style display:', popup.style.display);
        console.log('Popup computed style visibility:', window.getComputedStyle(popup).visibility);
        console.log('Popup computed style opacity:', window.getComputedStyle(popup).opacity);

        if (this.isOpen) {
            console.log('🔔 Closing popup...');
            this.closePopup();
        } else {
            console.log('🔔 Opening popup...');
            this.openPopup();
        }
    }
    
    openPopup() {
        console.log('🔔 Opening popup');
        const popup = document.getElementById('notificationsPopup');
        if (popup) {
            
            this.positionPopup(popup);
            
            console.log('Popup classes before adding show:', popup.className);
            popup.classList.add('show');
            console.log('Popup classes after adding show:', popup.className);
            this.isOpen = true;
            
            
            document.body.classList.add('notifications-open');
            
            console.log('✅ Popup shown, loading notifications...');

            
            setTimeout(() => {
                const computedStyle = window.getComputedStyle(popup);
                console.log('Popup computed visibility:', computedStyle.visibility);
                console.log('Popup computed opacity:', computedStyle.opacity);
                console.log('Popup computed display:', computedStyle.display);
                console.log('Popup computed transform:', computedStyle.transform);
            }, 10);

            this.loadNotifications(true); 
        } else {
            console.error('❌ Popup element not found when trying to open');
        }
    }

    closePopup() {
        console.log('🔔 Closing popup');
        const popup = document.getElementById('notificationsPopup');
        if (popup) {
            console.log('Popup classes before removing show:', popup.className);
            popup.classList.remove('show');
            console.log('Popup classes after removing show:', popup.className);
            this.isOpen = false;
            
            
            document.body.classList.remove('notifications-open');
            
            console.log('✅ Popup closed');
        }
    }
    
    async loadNotifications(reset = false) {
        if (this.isLoading) return;
        
        console.log('🔔 Loading notifications, reset:', reset);
        this.isLoading = true;
        
        if (reset) {
            this.currentPage = 1;
            this.notifications = [];
            this.hasMore = true;
            console.log('🔔 Reset: cleared notifications and reset pagination');
        }
        
        try {
            const apiUrl = `${getApiPath()}notifications.php?page=${this.currentPage}&limit=10&status=all`;
            console.log('🔔 Fetching from:', apiUrl);
            
            const response = await fetch(apiUrl, {
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                if (response.status === 401) {
                    console.log('Authentication required - redirecting to login');
                    window.location.href = 'login.php';
                    return;
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            console.log('🔔 Notification API response:', data);
            console.log('🔔 Unread count from API:', data.unread_count);
            
            if (data.success) {
                if (reset) {
                    this.notifications = data.notifications || [];
                    console.log('🔔 Reset: loaded', this.notifications.length, 'notifications');
                } else {
                    this.notifications = [...this.notifications, ...(data.notifications || [])];
                    console.log('🔔 Appended notifications, total:', this.notifications.length);
                }
                
                
                const newUnreadCount = data.unread_count || 0;
                if (newUnreadCount !== this.unreadCount) {
                    console.log('🔔 Unread count changed from', this.unreadCount, 'to', newUnreadCount);
                }
                this.unreadCount = newUnreadCount;
                this.hasMore = (data.notifications || []).length === 10; 
                
                
                console.log('🔔 Forcing badge update...');
                this.updateBadge();
                this.renderNotifications();
                
                if (!reset) {
                    this.currentPage++;
                }
                
                console.log('🔔 Notification loading completed successfully');
            } else {
                console.error('🔔 API returned error:', data.error);
                this.showError(data.error || 'Failed to load notifications');
            }
        } catch (error) {
            console.error('🔔 Error loading notifications:', error);
            this.showError('Failed to load notifications: ' + error.message);
        } finally {
            this.isLoading = false;
        }
    }
    
    async loadMoreNotifications() {
        if (!this.hasMore || this.isLoading) return;
        
        this.currentPage++;
        await this.loadNotifications();
    }
    
    shouldLoadMore(container) {
        return container.scrollTop + container.clientHeight >= container.scrollHeight - 50;
    }
    
    renderNotifications() {
        const listContainer = document.getElementById('notificationList');
        if (!listContainer) return;
        
        console.log('Rendering notifications:', this.notifications.length);
        
        if (this.notifications.length === 0) {
            listContainer.innerHTML = `
                <div class="notification-empty">
                    <div class="notification-empty-icon">
                        <i class="fas fa-bell-slash"></i>
                    </div>
                    <p class="notification-empty-text">No notifications yet</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        this.notifications.forEach(notification => {
            html += this.renderNotificationItem(notification);
        });
        
        if (this.isLoading) {
            html += `
                <div class="notification-loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading more...
                </div>
            `;
        }
        
        listContainer.innerHTML = html;
        
        
        listContainer.querySelectorAll('.notification-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                this.handleNotificationClick(this.notifications[index]);
            });
        });
    }
    
    renderNotificationItem(notification) {
        const timeAgo = this.getTimeAgo(new Date(notification.created_at));
        const icon = this.getNotificationIcon(notification.notification_type);
        const priorityClass = notification.priority || 'medium';
        
        return `
            <div class="notification-item ${notification.status === 'unread' ? 'unread' : ''}" data-id="${notification.id}">
                <div class="notification-icon ${notification.notification_type}">
                    <i class="${icon}"></i>
                </div>
                <div class="notification-content">
                    <h4 class="notification-content-title">${this.escapeHtml(notification.title)}</h4>
                    <p class="notification-content-message">${this.escapeHtml(notification.message)}</p>
                    <div class="notification-meta">
                        <span class="notification-time">${timeAgo}</span>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span class="notification-type">${notification.notification_type.replace('_', ' ')}</span>
                            <div class="notification-priority ${priorityClass}"></div>
                        </div>
                    </div>
                </div>
                <div class="notification-actions">
                    ${notification.status === 'unread' ? 
                        `<button class="notification-action-btn mark-read-btn" title="Mark as read" onclick="window.notificationSystem.markAsRead('${notification.id}'); event.stopPropagation();">
                            <i class="fas fa-check"></i>
                        </button>` : ''
                    }
                    <button class="notification-action-btn delete-btn" title="Delete notification" onclick="window.notificationSystem.deleteNotification('${notification.id}'); event.stopPropagation();">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="notification-dropdown">
                        <button class="notification-action-btn dropdown-btn" title="More actions" onclick="window.notificationSystem.toggleDropdown('${notification.id}'); event.stopPropagation();">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="notification-dropdown-menu" id="dropdown-${notification.id}">
                            ${notification.status === 'unread' ? 
                                `<button onclick="window.notificationSystem.markAsRead('${notification.id}'); event.stopPropagation();">
                                    <i class="fas fa-check"></i> Mark as Read
                                </button>` : 
                                `<button onclick="window.notificationSystem.markAsUnread('${notification.id}'); event.stopPropagation();">
                                    <i class="fas fa-eye"></i> Mark as Unread
                                </button>`
                            }
                            <button onclick="window.notificationSystem.archiveNotification('${notification.id}'); event.stopPropagation();">
                                <i class="fas fa-archive"></i> Archive
                            </button>
                            <button onclick="window.notificationSystem.deleteNotification('${notification.id}'); event.stopPropagation();" class="delete-action">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    getNotificationIcon(type) {
        const icons = {
            'parcel_created': 'fas fa-box',
            'parcel_status_change': 'fas fa-box-open',
            'delivery_assigned': 'fas fa-truck',
            'delivery_completed': 'fas fa-check-circle',
            'driver_unavailable': 'fas fa-user-times',
            'payment_received': 'fas fa-credit-card',
            'urgent_delivery': 'fas fa-exclamation-triangle',
            'system_alert': 'fas fa-info-circle',
            'customer_inquiry': 'fas fa-question-circle'
        };
        return icons[type] || 'fas fa-bell';
    }
    
    async handleNotificationClick(notification) {
        console.log('Notification clicked:', notification);
        
        
        if (notification.status === 'unread') {
            await this.markAsRead(notification.id);
            notification.status = 'read';
            this.unreadCount = Math.max(0, this.unreadCount - 1);
            this.updateBadge();
        }
        
        
        switch (notification.notification_type) {
            case 'parcel_created':
            case 'parcel_status_change':
                if (notification.parcel_id) {
                    this.showParcelDetails(notification.parcel_id);
                }
                break;
            case 'delivery_assigned':
            case 'delivery_completed':
                if (notification.delivery_id) {
                    this.showDeliveryDetails(notification.delivery_id);
                }
                break;
            default:
                console.log('Notification clicked:', notification);
        }
        
        this.closePopup();
    }
    
    showParcelDetails(parcelId) {
        
        console.log('Show parcel details:', parcelId);
        
    }
    
    showDeliveryDetails(deliveryId) {
        console.log('Show delivery details:', deliveryId);
    }
    
    async markAsRead(notificationId) {
        try {
            const response = await fetch(`${getApiPath()}notifications.php`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notification_id: notificationId,
                    action: 'mark_read'
                })
            });
            
            const data = await response.json();
            if (!data.success) {
                console.error('Failed to mark notification as read:', data.error);
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    async markAllAsRead() {
        if (this.unreadCount === 0) return;
        
        try {
            const response = await fetch(`${getApiPath()}notifications.php`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            if (data.success) {
                
                this.notifications.forEach(notification => {
                    if (notification.status === 'unread') {
                        notification.status = 'read';
                    }
                });
                
                this.unreadCount = 0;
                this.updateBadge();
                this.renderNotifications();
                
                
                this.showToast('All notifications marked as read', 'success');
            } else {
                this.showToast(data.error || 'Failed to mark all as read', 'error');
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
            this.showToast('Failed to mark all as read', 'error');
        }
    }
    
    async refreshNotifications() {
        const refreshBtn = document.getElementById('refreshNotifications');
        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>';
        }
        
        await this.loadNotifications(true);
        
        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        }
    }
    
    updateBadge() {
        console.log('🔔 Updating badge - unread count:', this.unreadCount);
        const badge = document.querySelector('.notification-btn .badge');
        console.log('🔔 Badge element found:', badge ? 'YES' : 'NO');
        
        if (badge) {
            if (this.unreadCount > 0) {
                const displayCount = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.textContent = displayCount;
                badge.style.display = 'flex';
                console.log('🔔 Badge updated to show:', displayCount);
                
                
                badge.classList.add('new');
                setTimeout(() => badge.classList.remove('new'), 1000);
            } else {
                badge.style.display = 'none';
                console.log('🔔 Badge hidden (no unread notifications)');
            }
        } else {
            console.error('🔔 Badge element not found! Available elements with .notification-btn:');
            document.querySelectorAll('.notification-btn').forEach((btn, i) => {
                console.log(`  Button ${i}:`, btn, 'Badge child:', btn.querySelector('.badge'));
            });
        }
    }
    
    startAutoRefresh() {
        
        this.refreshInterval = setInterval(() => {
            if (!this.isOpen) {
                
                console.log('🔔 Auto-refreshing notifications (30s interval)');
                this.loadNotifications(true);
            }
        }, 30000);
    }
    
    
    startFrequentRefresh() {
        console.log('🔔 Starting frequent refresh mode for 2 minutes');
        
        
        if (this.frequentRefreshInterval) {
            clearInterval(this.frequentRefreshInterval);
        }
        
        
        this.frequentRefreshInterval = setInterval(() => {
            if (!this.isOpen) {
                console.log('🔔 Frequent refresh: loading notifications');
                this.loadNotifications(true);
            }
        }, 5000);
        
        
        setTimeout(() => {
            if (this.frequentRefreshInterval) {
                console.log('🔔 Stopping frequent refresh mode');
                clearInterval(this.frequentRefreshInterval);
                this.frequentRefreshInterval = null;
            }
        }, 120000); 
    }
    
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
        if (this.frequentRefreshInterval) {
            clearInterval(this.frequentRefreshInterval);
            this.frequentRefreshInterval = null;
        }
    }
    
    showError(message) {
        const listContainer = document.getElementById('notificationList');
        if (listContainer) {
            listContainer.innerHTML = `
                <div class="notification-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `;
        }
    }
    
    showToast(message, type = 'info') {
        
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 6px;
            color: white;
            font-size: 14px;
            z-index: 10000;
            transition: all 0.3s;
            ${type === 'success' ? 'background: #10b981;' : ''}
            ${type === 'error' ? 'background: #ef4444;' : ''}
            ${type === 'info' ? 'background: #3b82f6;' : ''}
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    getTimeAgo(date) {
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) {
            return 'Just now';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes} min ago`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours}h ago`;
        } else if (diffInSeconds < 604800) {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days}d ago`;
        } else {
            return date.toLocaleDateString();
        }
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    
    async deleteNotification(notificationId) {
        console.log('Deleting notification:', notificationId);
        
        if (!confirm('Are you sure you want to delete this notification?')) {
            return;
        }
        
        try {
            const response = await fetch(`${getApiPath()}notifications.php`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            });
            
            const data = await response.json();
            if (data.success) {
                
                this.notifications = this.notifications.filter(n => n.id !== notificationId);
                
                
                const deletedNotification = this.notifications.find(n => n.id === notificationId);
                if (deletedNotification && deletedNotification.status === 'unread') {
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    this.updateBadge();
                }
                
                this.renderNotifications();
                this.showToast('Notification deleted', 'success');
            } else {
                this.showToast(data.error || 'Failed to delete notification', 'error');
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
            this.showToast('Failed to delete notification', 'error');
        }
    }
    
    async markAsUnread(notificationId) {
        console.log('Marking as unread:', notificationId);
        
        try {
            const response = await fetch(`${getApiPath()}notifications.php`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notification_id: notificationId,
                    action: 'mark_unread'
                })
            });
            
            const data = await response.json();
            if (data.success) {
                
                const notification = this.notifications.find(n => n.id === notificationId);
                if (notification && notification.status === 'read') {
                    notification.status = 'unread';
                    this.unreadCount++;
                    this.updateBadge();
                    this.renderNotifications();
                    this.showToast('Marked as unread', 'success');
                }
            } else {
                this.showToast(data.error || 'Failed to mark as unread', 'error');
            }
        } catch (error) {
            console.error('Error marking as unread:', error);
            this.showToast('Failed to mark as unread', 'error');
        }
    }
    
    async archiveNotification(notificationId) {
        console.log('Archiving notification:', notificationId);
        
        try {
            const response = await fetch(`${getApiPath()}notifications.php`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notification_id: notificationId,
                    action: 'archive'
                })
            });
            
            const data = await response.json();
            if (data.success) {
                
                const notification = this.notifications.find(n => n.id === notificationId);
                if (notification) {
                    if (notification.status === 'unread') {
                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                        this.updateBadge();
                    }
                    this.notifications = this.notifications.filter(n => n.id !== notificationId);
                    this.renderNotifications();
                    this.showToast('Notification archived', 'success');
                }
            } else {
                this.showToast(data.error || 'Failed to archive notification', 'error');
            }
        } catch (error) {
            console.error('Error archiving notification:', error);
            this.showToast('Failed to archive notification', 'error');
        }
    }
    
    toggleDropdown(notificationId) {
        const dropdown = document.getElementById(`dropdown-${notificationId}`);
        if (!dropdown) return;
        
        
        document.querySelectorAll('.notification-dropdown-menu').forEach(menu => {
            if (menu.id !== `dropdown-${notificationId}`) {
                menu.classList.remove('show');
            }
        });
        
        
        dropdown.classList.toggle('show');
        
        
        const closeDropdown = (e) => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
                document.removeEventListener('click', closeDropdown);
            }
        };
        
        if (dropdown.classList.contains('show')) {
            setTimeout(() => document.addEventListener('click', closeDropdown), 10);
        }
    }

    
    destroy() {
        this.stopAutoRefresh();
        const popup = document.getElementById('notificationsPopup');
        if (popup) {
            popup.remove();
        }
        
        
        window.notificationSystemInstance = null;
        window.notificationSystemInitialized = false;
        
        console.log('🔔 NotificationSystem destroyed and cleaned up');
    }
}

console.log('Initializing notification system...');

if (window.notificationSystemInitialized) {
    console.log('🔔 NotificationSystem already initialized, skipping');
} else {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (document.querySelector('.notification-btn') && !window.notificationSystemInitialized) {
                window.notificationSystem = new NotificationSystem();
                window.notificationSystemInitialized = true;
                console.log('Notification system initialized on DOMContentLoaded');
            }
        });
    } else {
        
        if (document.querySelector('.notification-btn') && !window.notificationSystemInitialized) {
            window.notificationSystem = new NotificationSystem();
            window.notificationSystemInitialized = true;
            console.log('Notification system initialized immediately');
        } else if (window.notificationSystemInitialized) {
            console.log('Notification system already exists');
        } else {
            console.warn('Notification button not found in DOM');
        }
    }

    
    window.addEventListener('beforeunload', () => {
        if (window.notificationSystem) {
            window.notificationSystem.destroy();
        }
    });
}

} 
