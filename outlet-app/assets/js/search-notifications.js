

class GlobalSearch {
    constructor() {
        this.searchInput = document.getElementById('globalSearchInput');
        this.searchResults = document.getElementById('searchResults');
        this.searchOverlay = document.getElementById('searchOverlay');
        this.debounceTimer = null;
        this.currentQuery = '';
        
        this.init();
    }

    init() {
        if (!this.searchInput) return;

        
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                this.hideResults();
                return;
            }

            this.debounceTimer = setTimeout(() => {
                this.performSearch(query);
            }, 300);
        });

        
        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = e.target.value.trim();
                if (query.length >= 2) {
                    clearTimeout(this.debounceTimer);
                    this.performSearch(query);
                }
            }
        });

        
        this.searchInput.addEventListener('focus', () => {
            if (this.currentQuery && this.searchResults) {
                this.showResults();
            }
        });

        
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-container')) {
                this.hideResults();
            }
        });

        
        document.addEventListener('keydown', (e) => {
            
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.searchInput.focus();
            }
            
            if (e.key === 'Escape') {
                this.hideResults();
                this.searchInput.blur();
            }
        });
    }

    async performSearch(query) {
        this.currentQuery = query;
        
        try {
            const response = await fetch(`../api/search.php?q=${encodeURIComponent(query)}&type=all`);
            const data = await response.json();

            if (data.success) {
                this.displayResults(data);
            } else {
                this.showError('Search failed. Please try again.');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Search error. Please try again.');
        }
    }

    displayResults(data) {
        if (!this.searchResults) return;

        if (data.total === 0) {
            this.searchResults.innerHTML = `
                <div class="search-results-header">
                    <span>No results</span>
                    <button onclick="globalSearch.hideResults()" class="search-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-empty">
                    <i class="fas fa-search"></i>
                    <p>No results found for "${this.currentQuery}"</p>
                </div>
            `;
            this.showResults();
            return;
        }

        let html = `<div class="search-results-header">
            <span>${data.total} result${data.total > 1 ? 's' : ''} found</span>
            <button onclick="globalSearch.hideResults()" class="search-close">
                <i class="fas fa-times"></i>
            </button>
        </div>`;

        
        const types = ['parcels', 'customers', 'notifications', 'trips', 'drivers'];
        types.forEach(type => {
            if (data.results[type] && data.results[type].length > 0) {
                html += this.renderGroup(type, data.results[type]);
            }
        });

        this.searchResults.innerHTML = html;
        this.showResults();
    }

    renderGroup(type, items) {
        const icons = {
            parcels: 'box',
            customers: 'user',
            trips: 'route',
            notifications: 'bell',
            drivers: 'user-tie'
        };
        
        const titles = {
            parcels: 'Parcels',
            customers: 'Customers',
            trips: 'Trips',
            notifications: 'Notifications',
            drivers: 'Drivers'
        };

        let html = `
            <div class="search-group">
                <div class="search-group-header">
                    <i class="fas fa-${icons[type]}"></i>
                    <span>${titles[type]}</span>
                </div>
                <div class="search-group-items">
        `;

        items.forEach(item => {
            const statusClass = item.status ? `status-${item.status.toLowerCase().replace('_', '-')}` : '';
            html += `
                <a href="${item.url}" class="search-item">
                    <div class="search-item-icon">
                        <i class="fas fa-${item.icon}"></i>
                    </div>
                    <div class="search-item-content">
                        <div class="search-item-title">${this.highlightMatch(item.title)}</div>
                        <div class="search-item-subtitle">${this.highlightMatch(item.subtitle)}</div>
                        ${item.info ? `<div class="search-item-info">${item.info}</div>` : ''}
                    </div>
                    ${item.status ? `<span class="search-item-status ${statusClass}">${item.status}</span>` : ''}
                </a>
            `;
        });

        html += `
                </div>
            </div>
        `;

        return html;
    }

    renderResultGroup(title, items, icon) {
        let html = `
            <div class="search-group">
                <div class="search-group-header">
                    <i class="fas fa-${icon}"></i>
                    <span>${title}</span>
                </div>
                <div class="search-group-items">
        `;

        items.forEach(item => {
            const statusClass = item.status ? `status-${item.status.toLowerCase().replace('_', '-')}` : '';
            html += `
                <a href="${item.url}" class="search-item">
                    <div class="search-item-icon">
                        <i class="fas fa-${item.icon}"></i>
                    </div>
                    <div class="search-item-content">
                        <div class="search-item-title">${this.highlightMatch(item.title)}</div>
                        <div class="search-item-subtitle">${this.highlightMatch(item.subtitle)}</div>
                        ${item.info ? `<div class="search-item-info">${item.info}</div>` : ''}
                    </div>
                    ${item.status ? `<span class="search-item-status ${statusClass}">${item.status}</span>` : ''}
                </a>
            `;
        });

        html += `
                </div>
            </div>
        `;

        return html;
    }

    highlightMatch(text) {
        if (!text || !this.currentQuery) return text;
        const regex = new RegExp(`(${this.escapeRegex(this.currentQuery)})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    showResults() {
        if (this.searchResults) {
            this.searchResults.classList.add('show');
        }
        if (this.searchOverlay) {
            this.searchOverlay.classList.add('show');
        }
    }

    hideResults() {
        if (this.searchResults) {
            this.searchResults.classList.remove('show');
        }
        if (this.searchOverlay) {
            this.searchOverlay.classList.remove('show');
        }
    }

    showError(message) {
        if (!this.searchResults) return;
        this.searchResults.innerHTML = `
            <div class="search-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${message}</p>
            </div>
        `;
        this.showResults();
    }
}

class NotificationManager {
    constructor() {
        this.notificationBell = document.getElementById('notificationBell');
        this.notificationBadge = document.getElementById('notificationBadge');
        this.notificationPanel = document.getElementById('notificationPanel');
        this.unreadCount = 0;
        this.refreshInterval = null;
        
        this.init();
    }

    init() {
        if (!this.notificationBell) {
            console.error('❌ Notification bell not found');
            return;
        }

        
        this.notificationBell.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.togglePanel();
        });

        
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.notification-container')) {
                if (this.notificationPanel && this.notificationPanel.classList.contains('show')) {
                    this.closePanel();
                }
            }
        });

        
        this.loadUnreadCount();
        
        
        this.refreshInterval = setInterval(() => {
            this.loadUnreadCount();
        }, 30000);
    }

    async loadUnreadCount() {
        try {
            const response = await fetch('../api/notifications.php?action=unread_count');
            const data = await response.json();

            if (data.success) {
                this.updateBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Error loading unread count:', error);
        }
    }

    updateBadge(count) {
        this.unreadCount = count;
        
        if (this.notificationBadge) {
            if (count > 0) {
                this.notificationBadge.textContent = count > 99 ? '99+' : count;
                this.notificationBadge.style.display = 'flex';
            } else {
                this.notificationBadge.style.display = 'none';
            }
        }
    }

    async togglePanel() {
        const isOpen = this.notificationPanel.classList.contains('show');
        console.log('🔔 Toggle panel - currently:', isOpen ? 'open' : 'closed');
        
        if (isOpen) {
            this.closePanel();
        } else {
            await this.openPanel();
        }
    }

    async openPanel() {
        
        await this.loadNotifications();
        this.notificationPanel.classList.add('show');
    }

    closePanel() {
        if (this.notificationPanel) {
            this.notificationPanel.classList.remove('show');
        }
    }

    async loadNotifications() {
        const container = document.getElementById('notificationList');
        if (!container) return;

        container.innerHTML = '<div class="notification-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

        try {
            const response = await fetch('../api/notifications.php?action=list&limit=20');
            const data = await response.json();

            if (data.success) {
                this.displayNotifications(data.notifications);
            } else {
                container.innerHTML = '<div class="notification-error">Failed to load notifications</div>';
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            container.innerHTML = '<div class="notification-error">Error loading notifications</div>';
        }
    }

    displayNotifications(notifications) {
        const container = document.getElementById('notificationList');
        if (!container) return;

        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }

        let html = '';
        notifications.forEach(notif => {
            const unreadClass = notif.status === 'unread' ? 'unread' : '';
            const priorityClass = `priority-${notif.priority || 'medium'}`;
            const iconMap = {
                'parcel_created': 'fa-box',
                'parcel_status_change': 'fa-truck',
                'delivery_assigned': 'fa-user-check',
                'delivery_completed': 'fa-check-circle',
                'driver_unavailable': 'fa-exclamation-triangle',
                'payment_received': 'fa-dollar-sign',
                'urgent_delivery': 'fa-exclamation-circle',
                'system_alert': 'fa-info-circle',
                'customer_inquiry': 'fa-question-circle'
            };
            const icon = iconMap[notif.notification_type] || 'fa-bell';
            
            
            let clickAction = '';
            if (notif.parcel_id) {
                clickAction = `notificationManager.handleNotificationClick('${notif.id}', '${notif.notification_type}', '${notif.parcel_id}')`;
            } else if (notif.trip_id) {
                clickAction = `notificationManager.handleNotificationClick('${notif.id}', '${notif.notification_type}', null, '${notif.trip_id}')`;
            } else {
                clickAction = `notificationManager.handleNotificationClick('${notif.id}', '${notif.notification_type}')`;
            }

            html += `
                <div class="notification-item ${unreadClass} ${priorityClass}" data-id="${notif.id}" onclick="${clickAction}" style="cursor: pointer;">
                    <div class="notification-icon">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notif.title}</div>
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-time">${notif.time_ago || ''}</div>
                    </div>
                    <button class="notification-dismiss" onclick="notificationManager.dismissNotification('${notif.id}', event)" title="Dismiss">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    async handleNotificationClick(notificationId, notificationType, parcelId = null, tripId = null) {
        
        await this.markAsRead(notificationId);
        
        
        const panel = document.getElementById('notificationPanel');
        if (panel) {
            panel.classList.remove('show');
        }
        
        
        const basePath = window.location.pathname.includes('/pages/') ? '../pages/' : 'pages/';
        
        switch(notificationType) {
            case 'parcel_created':
            case 'parcel_status_change':
                if (parcelId) {
                    window.location.href = `${basePath}parcel_management.php?parcel_id=${parcelId}`;
                } else {
                    window.location.href = `${basePath}parcel_management.php`;
                }
                break;
                
            case 'delivery_assigned':
            case 'urgent_delivery':
                if (tripId) {
                    window.location.href = `${basePath}trip_management.php?trip_id=${tripId}`;
                } else {
                    window.location.href = `${basePath}trip_management.php`;
                }
                break;
                
            case 'delivery_completed':
                window.location.href = `${basePath}outlet_dashboard.php`;
                break;
                
            case 'driver_unavailable':
                window.location.href = `${basePath}driver_management.php`;
                break;
                
            case 'payment_received':
                if (parcelId) {
                    window.location.href = `${basePath}parcel_management.php?parcel_id=${parcelId}`;
                }
                break;
                
            case 'system_alert':
            case 'customer_inquiry':
                window.location.href = `${basePath}notifications.php`;
                break;
                
            default:
                
                window.location.href = `${basePath}notifications.php`;
        }
    }

    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('notification_id', notificationId);

            const response = await fetch('../api/notifications.php?action=mark_read', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                
                const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (item) {
                    item.classList.remove('unread');
                }
                this.loadUnreadCount();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            const response = await fetch('../api/notifications.php?action=mark_all_read', {
                method: 'POST'
            });

            const data = await response.json();
            if (data.success) {
                
                await this.loadNotifications();
                this.updateBadge(0);
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }

    async dismissNotification(notificationId, event) {
        event.stopPropagation();
        
        try {
            const formData = new FormData();
            formData.append('notification_id', notificationId);

            const response = await fetch('../api/notifications.php?action=dismiss', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                
                const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (item) {
                    item.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => {
                        item.remove();
                        
                        const list = document.getElementById('notificationList');
                        if (list && list.querySelectorAll('.notification-item').length === 0) {
                            list.innerHTML = `
                                <div class="notification-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No notifications</p>
                                </div>
                            `;
                        }
                    }, 300);
                }
                this.loadUnreadCount();
            }
        } catch (error) {
            console.error('Error dismissing notification:', error);
        }
    }
}

let globalSearch, notificationManager;

document.addEventListener('DOMContentLoaded', () => {
    globalSearch = new GlobalSearch();
    notificationManager = new NotificationManager();
});
