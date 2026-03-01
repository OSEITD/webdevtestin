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
        this.notificationBell  = document.getElementById('notificationBell');
        this.notificationBadge = document.getElementById('notificationBadge');
        this.notificationPanel = document.getElementById('notificationPanel');
        this.unreadCount          = 0;
        this.previousUnreadCount  = null; // null = first load, no ring
        this.currentFilter        = 'all'; // 'all' | 'unread' | 'urgent'
        this.allNotifications     = [];    // cached notification list
        this.refreshInterval      = null;

        this.init();
    }

    init() {
        if (!this.notificationBell) return;

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
        this.refreshInterval = setInterval(() => this.loadUnreadCount(), 30000);
    }

    // ─── Badge & Bell ───────────────────────────────────────────────────────────

    async loadUnreadCount() {
        try {
            const response = await fetch('../api/notifications.php?action=unread_count');
            const data = await response.json();
            if (data.success) this.updateBadge(data.unread_count);
        } catch (e) {}
    }

    updateBadge(count) {
        // Ring bell when count increases after the first load
        if (this.previousUnreadCount !== null && count > this.previousUnreadCount) {
            this.ringBell();
        }
        this.previousUnreadCount = count;
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

    ringBell() {
        const bellIcon = this.notificationBell && this.notificationBell.querySelector('.fa-bell');
        if (!bellIcon) return;
        bellIcon.style.animation = 'none';
        void bellIcon.offsetWidth; // force reflow
        bellIcon.style.animation = 'bellRing 0.9s ease-in-out';
        setTimeout(() => { bellIcon.style.animation = ''; }, 900);
    }

    // ─── Panel open / close ────────────────────────────────────────────────────

    async togglePanel() {
        const isOpen = this.notificationPanel.classList.contains('show');
        if (isOpen) { this.closePanel(); } else { await this.openPanel(); }
    }

    async openPanel() {
        this.renderPanelHeader();
        await this.loadNotifications();
        this.notificationPanel.classList.add('show');
    }

    closePanel() {
        if (this.notificationPanel) this.notificationPanel.classList.remove('show');
    }

    // ─── Dynamic header with filter tabs ──────────────────────────────────────

    renderPanelHeader() {
        const header = this.notificationPanel && this.notificationPanel.querySelector('.notification-header');
        if (!header) return;

        const subtitle = this.unreadCount > 0
            ? `<span class="notif-header-unread">${this.unreadCount} unread</span>`
            : `<span class="notif-header-all-read"><i class="fas fa-check-circle"></i> All caught up</span>`;

        const tab = (key, label, badge = false) => {
            const active = this.currentFilter === key ? 'active' : '';
            const badgeHtml = badge && this.unreadCount > 0
                ? ` <span class="notif-filter-badge">${this.unreadCount}</span>` : '';
            return `<button class="notif-filter-tab ${active}" onclick="notificationManager.setFilter('${key}')">${label}${badgeHtml}</button>`;
        };

        header.innerHTML = `
            <div class="notif-header-top">
                <div class="notif-header-info">
                    <h3>Notifications</h3>
                    <div class="notif-header-subtitle">${subtitle}</div>
                </div>
                <div class="notif-header-actions">
                    <button class="notif-action-btn" onclick="notificationManager.refreshPanel()" title="Refresh">
                        <i class="fas fa-sync-alt" id="notifRefreshIcon"></i>
                    </button>
                </div>
            </div>
            <div class="notification-filter-tabs">
                ${tab('all',    'All')}
                ${tab('unread', 'Unread', true)}
                ${tab('urgent', 'Urgent')}
            </div>`;
    }

    async setFilter(filter) {
        this.currentFilter = filter;
        this.renderPanelHeader();
        this.renderFilteredNotifications();
    }

    renderFilteredNotifications() {
        let list = this.allNotifications;
        if (this.currentFilter === 'unread') {
            list = list.filter(n => n.status === 'unread');
        } else if (this.currentFilter === 'urgent') {
            list = list.filter(n => n.priority === 'urgent' || n.priority === 'high');
        }
        this.displayNotifications(list);
    }

    // ─── Load & refresh ────────────────────────────────────────────────────────

    async loadNotifications() {
        const container = document.getElementById('notificationList');
        if (!container) return;
        container.innerHTML = '<div class="notification-loading"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            const response = await fetch('../api/notifications.php?action=list&limit=30');
            const data = await response.json();
            if (data.success) {
                this.allNotifications = data.notifications || [];
                this.renderFilteredNotifications();
            } else {
                container.innerHTML = '<div class="notification-error"><i class="fas fa-exclamation-triangle"></i><p>Failed to load notifications</p></div>';
            }
        } catch (e) {
            container.innerHTML = '<div class="notification-error"><i class="fas fa-exclamation-triangle"></i><p>Error loading notifications</p></div>';
        }
    }

    async refreshPanel() {
        const icon = document.getElementById('notifRefreshIcon');
        if (icon) icon.style.animation = 'spin 0.6s linear infinite';
        await this.loadNotifications();
        await this.loadUnreadCount();
        this.renderPanelHeader();
        if (icon) icon.style.animation = '';
    }

    // ─── Render notifications ──────────────────────────────────────────────────

    displayNotifications(notifications) {
        const container = document.getElementById('notificationList');
        if (!container) return;

        if (!notifications || notifications.length === 0) {
            const msgs = { unread: 'No unread notifications', urgent: 'No urgent notifications', all: 'No notifications' };
            container.innerHTML = `<div class="notification-empty"><i class="fas fa-bell-slash"></i><p>${msgs[this.currentFilter] || msgs.all}</p></div>`;
            return;
        }

        const iconMap = {
            parcel_created: 'fa-box', parcel_status_change: 'fa-truck',
            delivery_assigned: 'fa-user-check', delivery_completed: 'fa-check-circle',
            driver_unavailable: 'fa-exclamation-triangle', payment_received: 'fa-dollar-sign',
            urgent_delivery: 'fa-exclamation-circle', system_alert: 'fa-info-circle',
            customer_inquiry: 'fa-question-circle'
        };

        let html = '';
        notifications.forEach(notif => {
            const unreadClass  = notif.status === 'unread' ? 'unread' : '';
            const priorityCls  = notif.priority ? `priority-${notif.priority}` : '';
            const typeCls      = `notif-type-${notif.notification_type || 'system_alert'}`;
            const icon         = iconMap[notif.notification_type] || 'fa-bell';

            // Extract parcel_id / trip_id — check direct column, then parsed_data jsonb
            let parsedData = {};
            try { parsedData = notif.parsed_data || (notif.data ? (typeof notif.data === 'string' ? JSON.parse(notif.data) : notif.data) : {}); } catch (e) {}
            const parcelId = notif.parcel_id  || parsedData.parcel_id  || '';
            const tripId   = parsedData.trip_id || parsedData.delivery_id || notif.delivery_id || '';

            const priorityChip = notif.priority === 'urgent'
                ? '<span class="notif-priority-chip urgent">Urgent</span>'
                : notif.priority === 'high' ? '<span class="notif-priority-chip high">High</span>' : '';

            const clickFn = `notificationManager.handleNotificationClick('${notif.id}','${notif.notification_type || ''}','${parcelId}','${tripId}')`;

            html += `
                <div class="notification-item ${unreadClass} ${priorityCls} ${typeCls}" data-id="${notif.id}" onclick="${clickFn}" style="cursor:pointer;">
                    <div class="notification-icon"><i class="fas ${icon}"></i></div>
                    <div class="notification-content">
                        <div class="notification-title">${this.esc(notif.title)}${priorityChip}</div>
                        <div class="notification-message">${this.esc(notif.message || '')}</div>
                        <div class="notification-time">${notif.time_ago || ''}</div>
                    </div>
                    <button class="notification-dismiss" onclick="notificationManager.dismissNotification('${notif.id}',event)" title="Dismiss">
                        <i class="fas fa-times"></i>
                    </button>
                </div>`;
        });

        container.innerHTML = html;
    }

    esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    // ─── Click navigation ──────────────────────────────────────────────────────

    async handleNotificationClick(notificationId, notificationType, parcelId = '', tripId = '') {
        await this.markAsRead(notificationId);
        this.closePanel();

        const base = window.location.pathname.includes('/pages/') ? '' : 'pages/';
        const nav = {
            parcel_created:       parcelId ? `${base}parcel_management.php?parcel_id=${parcelId}` : `${base}parcel_management.php`,
            parcel_status_change: parcelId ? `${base}parcel_management.php?parcel_id=${parcelId}` : `${base}parcel_management.php`,
            delivery_assigned:    tripId   ? `${base}manager_trips.php?trip_id=${tripId}`         : `${base}manager_trips.php`,
            urgent_delivery:      tripId   ? `${base}manager_trips.php?trip_id=${tripId}`         : `${base}manager_trips.php`,
            delivery_completed:   `${base}outlet_dashboard.php`,
            driver_unavailable:   `${base}driver_management.php`,
            payment_received:     parcelId ? `${base}parcel_management.php?parcel_id=${parcelId}` : `${base}parcel_management.php`,
            system_alert:         `${base}notifications.php`,
            customer_inquiry:     `${base}notifications.php`
        };
        window.location.href = nav[notificationType] || `${base}notifications.php`;
    }

    // ─── Mark read / dismiss ───────────────────────────────────────────────────

    async markAsRead(notificationId) {
        try {
            const fd = new FormData();
            fd.append('notification_id', notificationId);
            const res  = await fetch('../api/notifications.php?action=mark_read', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                // Update DOM item
                const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (item) item.classList.remove('unread');
                // Update cache
                const cached = this.allNotifications.find(n => n.id === notificationId);
                if (cached) cached.status = 'read';
                // Decrement badge without re-fetching
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.previousUnreadCount = this.unreadCount;
                this.updateBadge(this.unreadCount);
                this.renderPanelHeader();
            }
        } catch (e) {}
    }

    async markAllAsRead() {
        try {
            const res  = await fetch('../api/notifications.php?action=mark_all_read', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                this.allNotifications.forEach(n => { n.status = 'read'; });
                this.updateBadge(0);
                this.renderPanelHeader();
                this.renderFilteredNotifications();
            }
        } catch (e) {}
    }

    async dismissNotification(notificationId, event) {
        event.stopPropagation();
        try {
            const fd = new FormData();
            fd.append('notification_id', notificationId);
            const res  = await fetch('../api/notifications.php?action=dismiss', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (item) {
                    item.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => {
                        this.allNotifications = this.allNotifications.filter(n => n.id !== notificationId);
                        this.renderFilteredNotifications();
                    }, 300);
                }
                this.loadUnreadCount();
            }
        } catch (e) {}
    }
}

let globalSearch, notificationManager;

document.addEventListener('DOMContentLoaded', () => {
    globalSearch = new GlobalSearch();
    notificationManager = new NotificationManager();
});
