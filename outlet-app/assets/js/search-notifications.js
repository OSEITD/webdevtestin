/**
 * Professional Global Search with keyboard navigation, recent searches, and loading states
 */
class GlobalSearch {
    constructor() {
        this.searchInput = document.getElementById('globalSearchInput');
        this.searchResults = document.getElementById('searchResults');
        this.searchOverlay = document.getElementById('searchOverlay');
        this.searchContainer = document.getElementById('searchContainer');
        this.debounceTimer = null;
        this.currentQuery = '';
        this.selectedIndex = -1;
        this.allItems = [];
        this.isLoading = false;
        this.recentSearchesKey = 'globalSearch_recent';
        this.maxRecentSearches = 5;
        
        this.init();
    }

    /** Position search results below the input (since it's now at body level) */
    positionResults() {
        if (!this.searchInput || !this.searchResults) return;
        
        const inputRect = this.searchInput.getBoundingClientRect();
        const containerRect = this.searchContainer ? this.searchContainer.getBoundingClientRect() : inputRect;
        
        this.searchResults.style.top = (inputRect.bottom + 8) + 'px';
        this.searchResults.style.left = containerRect.left + 'px';
        this.searchResults.style.width = containerRect.width + 'px';
    }

    init() {
        if (!this.searchInput) return;

        // Input handler with debounce
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                if (query.length === 0) {
                    this.showRecentSearches();
                } else {
                    this.hideResults();
                }
                return;
            }

            this.showLoading();
            this.debounceTimer = setTimeout(() => {
                this.performSearch(query);
            }, 250);
        });

        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.navigateResults(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.navigateResults(-1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.selectedIndex >= 0 && this.allItems[this.selectedIndex]) {
                    this.selectItem(this.allItems[this.selectedIndex]);
                } else {
                    const query = e.target.value.trim();
                    if (query.length >= 2) {
                        clearTimeout(this.debounceTimer);
                        this.performSearch(query);
                    }
                }
            } else if (e.key === 'Tab' && this.searchResults?.classList.contains('show')) {
                e.preventDefault();
                this.navigateResults(e.shiftKey ? -1 : 1);
            }
        });

        // Focus handler - show recent searches
        this.searchInput.addEventListener('focus', () => {
            if (this.currentQuery && this.searchResults?.innerHTML) {
                this.showResults();
            } else if (!this.searchInput.value.trim()) {
                this.showRecentSearches();
            }
        });

        // Click outside to close
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-container')) {
                this.hideResults();
            }
        });

        // Global keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.searchInput.focus();
                this.searchInput.select();
            }
            if (e.key === 'Escape') {
                this.hideResults();
                this.searchInput.blur();
            }
        });

        // Click handler for results (event delegation)
        if (this.searchResults) {
            this.searchResults.addEventListener('click', (e) => {
                const searchItem = e.target.closest('.search-item');
                if (searchItem) {
                    e.preventDefault();
                    const index = parseInt(searchItem.dataset.index, 10);
                    if (!isNaN(index) && this.allItems[index]) {
                        this.selectItem(this.allItems[index]);
                    }
                }

                const recentItem = e.target.closest('.recent-search-item');
                if (recentItem) {
                    e.preventDefault();
                    const query = recentItem.dataset.query;
                    this.searchInput.value = query;
                    this.performSearch(query);
                }

                const clearRecent = e.target.closest('.clear-recent');
                if (clearRecent) {
                    e.preventDefault();
                    this.clearRecentSearches();
                }
            });
        }
    }

    // ===== Recent Searches =====
    getRecentSearches() {
        try {
            const stored = localStorage.getItem(this.recentSearchesKey);
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    saveRecentSearch(query) {
        if (!query || query.length < 2) return;
        
        try {
            let recent = this.getRecentSearches();
            // Remove duplicate if exists
            recent = recent.filter(q => q.toLowerCase() !== query.toLowerCase());
            // Add to front
            recent.unshift(query);
            // Keep only max items
            recent = recent.slice(0, this.maxRecentSearches);
            localStorage.setItem(this.recentSearchesKey, JSON.stringify(recent));
        } catch (e) {
            console.warn('Could not save recent search:', e);
        }
    }

    showRecentSearches() {
        if (!this.searchResults) return;
        
        const recent = this.getRecentSearches();
        
        if (recent.length === 0) {
            this.searchResults.innerHTML = `
                <div class="search-results-header">
                    <span>Start typing to search...</span>
                </div>
                <div class="search-empty">
                    <i class="fas fa-search"></i>
                    <p>Search for parcels, customers, trips, and more</p>
                </div>
            `;
        } else {
            let html = `
                <div class="search-results-header">
                    <span>Recent Searches</span>
                    <button class="clear-recent" title="Clear recent searches">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div class="recent-searches-list">
            `;
            
            recent.forEach((query, index) => {
                html += `
                    <div class="recent-search-item" data-query="${this.escapeHtml(query)}" data-index="${index}">
                        <i class="fas fa-history"></i>
                        <span>${this.escapeHtml(query)}</span>
                        <i class="fas fa-arrow-right recent-arrow"></i>
                    </div>
                `;
            });
            
            html += '</div>';
            this.searchResults.innerHTML = html;
        }
        
        this.showResults();
    }

    clearRecentSearches() {
        try {
            localStorage.removeItem(this.recentSearchesKey);
            this.showRecentSearches();
        } catch (e) {
            console.warn('Could not clear recent searches:', e);
        }
    }

    // ===== Navigation =====
    navigateResults(direction) {
        const items = this.searchResults?.querySelectorAll('.search-item, .recent-search-item');
        if (!items || items.length === 0) return;
        
        // Remove current selection
        items.forEach(item => item.classList.remove('selected'));
        
        // Update index
        this.selectedIndex += direction;
        
        // Wrap around
        if (this.selectedIndex < 0) {
            this.selectedIndex = items.length - 1;
        } else if (this.selectedIndex >= items.length) {
            this.selectedIndex = 0;
        }
        
        // Apply selection
        const selectedItem = items[this.selectedIndex];
        if (selectedItem) {
            selectedItem.classList.add('selected');
            selectedItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    // ===== Item Selection =====
    selectItem(item) {
        if (!item) return;
        
        this.hideResults();
        this.searchInput.blur();
        
        // Handle onclick function if present
        if (item.onclick) {
            try {
                // Extract function call from onclick string
                const onclickStr = item.onclick;
                if (onclickStr.startsWith('viewParcelDetails')) {
                    const match = onclickStr.match(/viewParcelDetails\(['"]([^'"]+)['"]\)/);
                    if (match && typeof window.viewParcelDetails === 'function') {
                        window.viewParcelDetails(match[1]);
                        return;
                    }
                }
                // Fallback: try to execute
                eval(onclickStr);
                return;
            } catch (e) {
                console.warn('Could not execute onclick:', e);
            }
        }
        
        // Navigate to URL
        if (item.url && item.url !== '#') {
            window.location.href = item.url;
            return;
        }
        
        // Fallback navigation based on type
        const basePath = window.location.pathname.includes('/pages/') ? '' : 'pages/';
        switch (item.type) {
            case 'parcel':
                if (typeof window.viewParcelDetails === 'function') {
                    window.viewParcelDetails(item.id);
                } else {
                    window.location.href = `${basePath}parcel_management.php?parcel_id=${item.id}`;
                }
                break;
            case 'customer':
                window.location.href = `${basePath}business_customers.php?id=${item.id}`;
                break;
            case 'trip':
                window.location.href = `${basePath}manager_trips.php?trip_id=${item.id}`;
                break;
            case 'driver':
                window.location.href = `${basePath}driver_management.php?driver_id=${item.id}`;
                break;
            case 'notification':
                window.location.href = `${basePath}notifications.php?id=${item.id}`;
                break;
            default:
                console.warn('Unknown item type:', item.type);
        }
    }

    // ===== Utility Methods =====
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showLoading() {
        if (!this.searchResults) return;
        this.isLoading = true;
        this.searchResults.innerHTML = `
            <div class="search-loading">
                <div class="search-loading-spinner"></div>
                <span>Searching...</span>
            </div>
        `;
        this.showResults();
    }

    async performSearch(query) {
        this.currentQuery = query;
        this.selectedIndex = -1;
        this.allItems = [];
        
        try {
            const response = await fetch(`../api/search.php?q=${encodeURIComponent(query)}&type=all`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            this.isLoading = false;

            if (data.success) {
                this.saveRecentSearch(query);
                this.displayResults(data);
            } else {
                this.showError(data.error || 'Search failed. Please try again.');
            }
        } catch (error) {
            this.isLoading = false;
            console.error('Search error:', error);
            this.showError('Connection error. Please check your network.');
        }
    }

    displayResults(data) {
        if (!this.searchResults) return;
        this.allItems = [];
        this.selectedIndex = -1;

        if (data.total === 0) {
            this.searchResults.innerHTML = `
                <div class="search-results-header">
                    <span>No results for "${this.escapeHtml(this.currentQuery)}"</span>
                    <button class="search-close" onclick="globalSearch.hideResults()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-empty">
                    <i class="fas fa-search"></i>
                    <p>No results found for "${this.escapeHtml(this.currentQuery)}"</p>
                    <p class="search-tip">Try different keywords or check spelling</p>
                </div>
            `;
            this.showResults();
            return;
        }

        // Build flat items list for keyboard navigation
        const types = ['parcels', 'customers', 'trips', 'drivers', 'notifications'];
        types.forEach(type => {
            if (data.results[type] && data.results[type].length > 0) {
                data.results[type].forEach(item => {
                    this.allItems.push(item);
                });
            }
        });

        let html = `<div class="search-results-header">
            <span>${data.total} result${data.total > 1 ? 's' : ''} found</span>
            <button onclick="globalSearch.hideResults()" class="search-close">
                <i class="fas fa-times"></i>
            </button>
        </div>`;

        // Add quick filters
        html += this.renderQuickFilters(data.results);

        let itemIndex = 0;
        types.forEach(type => {
            if (data.results[type] && data.results[type].length > 0) {
                html += this.renderGroup(type, data.results[type], itemIndex);
                itemIndex += data.results[type].length;
            }
        });

        this.searchResults.innerHTML = html;
        this.showResults();
    }

    renderQuickFilters(results) {
        const filters = [
            { key: 'parcels', label: 'Parcels', icon: 'box', count: results.parcels?.length || 0 },
            { key: 'customers', label: 'Customers', icon: 'user', count: results.customers?.length || 0 },
            { key: 'trips', label: 'Trips', icon: 'route', count: results.trips?.length || 0 },
            { key: 'drivers', label: 'Drivers', icon: 'user-tie', count: results.drivers?.length || 0 },
            { key: 'notifications', label: 'Alerts', icon: 'bell', count: results.notifications?.length || 0 }
        ];

        const activeFilters = filters.filter(f => f.count > 0);
        if (activeFilters.length <= 1) return '';

        let html = '<div class="search-quick-filters">';
        activeFilters.forEach(filter => {
            html += `
                <button class="search-filter-btn" data-filter="${filter.key}" onclick="globalSearch.scrollToGroup('${filter.key}')">
                    <i class="fas fa-${filter.icon}"></i>
                    ${filter.label} (${filter.count})
                </button>
            `;
        });
        html += '</div>';
        return html;
    }

    scrollToGroup(type) {
        const group = this.searchResults?.querySelector(`.search-group[data-type="${type}"]`);
        if (group) {
            group.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    renderGroup(type, items, startIndex = 0) {
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
            <div class="search-group" data-type="${type}">
                <div class="search-group-header">
                    <i class="fas fa-${icons[type]}"></i>
                    <span>${titles[type]}</span>
                    <span class="search-group-count">${items.length}</span>
                </div>
                <div class="search-group-items">
        `;

        items.forEach((item, localIndex) => {
            const globalIndex = startIndex + localIndex;
            const statusClass = item.status ? `status-${item.status.toLowerCase().replace(/_/g, '-')}` : '';
            const onclickData = item.onclick ? `data-onclick="${this.escapeHtml(item.onclick)}"` : '';
            
            html += `
                <div class="search-item" data-index="${globalIndex}" data-type="${item.type}" data-id="${item.id}" data-url="${item.url || '#'}" ${onclickData} tabindex="0" role="option">
                    <div class="search-item-icon ${item.type}">
                        <i class="fas ${item.icon}"></i>
                    </div>
                    <div class="search-item-content">
                        <div class="search-item-title">${this.highlightMatch(item.title)}</div>
                        <div class="search-item-subtitle">${this.highlightMatch(item.subtitle || '')}</div>
                        ${item.info ? `<div class="search-item-info">${this.escapeHtml(item.info)}</div>` : ''}
                    </div>
                    ${item.status ? `<span class="search-item-status ${statusClass}">${this.formatStatus(item.status)}</span>` : ''}
                    <i class="fas fa-chevron-right search-item-arrow"></i>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        return html;
    }

    formatStatus(status) {
        if (!status) return '';
        return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    highlightMatch(text) {
        if (!text || !this.currentQuery) return this.escapeHtml(text);
        const escaped = this.escapeHtml(text);
        const regex = new RegExp(`(${this.escapeRegex(this.currentQuery)})`, 'gi');
        return escaped.replace(regex, '<mark>$1</mark>');
    }

    escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    showResults() {
        this.positionResults(); // Position the dropdown below the input
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
        this.selectedIndex = -1;
    }

    showError(message) {
        if (!this.searchResults) return;
        this.searchResults.innerHTML = `
            <div class="search-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${this.escapeHtml(message)}</p>
                <button class="search-retry-btn" onclick="globalSearch.performSearch(globalSearch.currentQuery)">
                    <i class="fas fa-redo"></i> Retry
                </button>
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
