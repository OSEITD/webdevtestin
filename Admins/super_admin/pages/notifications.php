<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin - Notifications';
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/notifications-page.css">

<!-- Extra fix for super admin wrapper -->
<style>
    .notifications-shell { margin-top: 0; padding-top: 0; }
    .notifications-hero { border-radius: 12px; margin-bottom: 24px; }
    .content-container { max-width: 1200px; margin: 0 auto; width: 100%; }
    .back-button { 
        display: inline-flex; align-items: center; gap: 8px; 
        color: var(--text-secondary, #64748b); text-decoration: none; 
        font-weight: 500; margin-bottom: 20px; transition: color 0.2s;
    }
    .back-button:hover { color: var(--primary-color, #2E0D2A); }
</style>

<div class="content-container" style="padding-top: 90px;">
    <a href="dashboard.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
        Back to Dashboard
    </a>

    <section class="notifications-shell">
        <header class="notifications-hero">
            <div class="notifications-hero-text">
                        <span class="notifications-hero-eyebrow">System Alerts Centre</span>
                        <h1>
                            <i class="fas fa-bell"></i>
                            Notifications
                        </h1>
                        <p>Stay on top of updates, alerts, and system messages with real-time insights.</p>
                    </div>
                    <div class="notifications-hero-actions">
                        <button type="button" class="hero-btn" id="autoRefreshBtn" onclick="notificationsPage.toggleAutoRefresh()">
                            <i class="fas fa-sync-alt"></i>
                            <span id="autoRefreshLabel">Auto-refresh on</span>
                        </button>
                        <button type="button" class="hero-btn hero-btn-primary" onclick="notificationsPage.markAllAsRead()">
                            <i class="fas fa-check-double"></i>
                            Mark all as read
                        </button>
                    </div>
                </header>

                <div class="notifications-toolbar">
                    <div class="notifications-filter-group">
                        <div class="filter-field">
                            <label for="typeFilter">Type</label>
                            <select id="typeFilter" class="filter-select">
                                <option value="">All types</option>
                                <option value="parcel">Parcel</option>
                                <option value="delivery">Delivery</option>
                                <option value="payment">Payment</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="dateFilter">Received</label>
                            <select id="dateFilter" class="filter-select">
                                <option value="">Any time</option>
                                <option value="today">Today</option>
                                <option value="week">This week</option>
                                <option value="month">This month</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="priorityFilter">Priority</label>
                            <select id="priorityFilter" class="filter-select">
                                <option value="">Any priority</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                    </div>
                    <div class="notifications-toolbar-actions">
                        <button type="button" class="toolbar-btn" onclick="notificationsPage.clearFilters()">
                            <i class="fas fa-sliders-h"></i>
                            Clear filters
                        </button>
                        <button type="button" class="toolbar-btn toolbar-btn-refresh" onclick="refreshNotifications()">
                            <i class="fas fa-rotate"></i>
                            Refresh
                        </button>
                    </div>
                </div>

                <section class="notifications-stats" aria-live="polite">
                    <article class="notifications-stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div>
                            <span class="stat-label">Visible</span>
                            <span class="stat-value" id="statVisible">0</span>
                            <span class="stat-meta" id="statFilterSummary">All filters</span>
                        </div>
                    </article>
                    <article class="notifications-stat-card">
                        <div class="stat-icon stat-icon-info">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                        <div>
                            <span class="stat-label">Unread</span>
                            <span class="stat-value" id="statUnread">0</span>
                            <span class="stat-meta" id="statStatusLabel">All statuses</span>
                        </div>
                    </article>
                    <article class="notifications-stat-card">
                        <div class="stat-icon stat-icon-warning">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div>
                            <span class="stat-label">High priority</span>
                            <span class="stat-value" id="statHighPriority">0</span>
                            <span class="stat-meta">Across current view</span>
                        </div>
                    </article>
                    <article class="notifications-stat-card">
                        <div class="stat-icon stat-icon-neutral">
                            <i class="fas fa-archive"></i>
                        </div>
                        <div>
                            <span class="stat-label">Archived</span>
                            <span class="stat-value" id="statArchived">0</span>
                            <span class="stat-meta">Current dataset</span>
                        </div>
                    </article>
                </section>

                <div class="notifications-filters" role="toolbar" aria-label="Notification status filters">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="unread">Unread</button>
                    <button class="filter-btn" data-filter="read">Read</button>
                    <button class="filter-btn" data-filter="archived">Archived</button>
                    <button class="filter-btn" data-filter="dismissed">Dismissed</button>
                </div>
            </section>

            <div class="bulk-actions" id="bulkActions" style="display: none;">
                <span>Selected: <span id="selectedCount">0</span></span>
                <button onclick="markSelectedAsRead()" class="primary">
                    <i class="fas fa-check"></i> Mark as Read
                </button>
                <button onclick="archiveSelected()">
                    <i class="fas fa-archive"></i> Archive
                </button>
                <button onclick="deleteSelected()" class="danger">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>

            <div id="notificationsContainer">
                <div class="loading-container">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span style="margin-left: 12px;">Loading notifications...</span>
                </div>
            </div>

            <div class="pagination" id="pagination" style="display: none;">
                <button id="prevPage" onclick="previousPage()" class="pagination-btn">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span id="pageInfo" class="page-number active"></span>
                <button id="nextPage" onclick="nextPage()" class="pagination-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </main>
</div>

<div id="actionModal" class="action-modal">
    <div class="action-modal-backdrop" onclick="notificationsPage.hideActionModal()"></div>
    <div class="action-modal-content">
        <div class="action-modal-header">
            <h3>Notification Actions</h3>
            <button class="action-modal-close" onclick="notificationsPage.hideActionModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="action-modal-body">
            <div id="actionModalButtons">
            </div>
        </div>
    </div>
</div>

<script>
    class NotificationsPage {
        constructor() {
            this.currentFilter = 'all';
            this.currentPage = 1;
            this.limit = 20;
            this.notifications = [];
            this.selectedNotifications = new Set();
            this.totalPages = 1;
            this.refreshInterval = null;
            this.autoRefreshEnabled = true;
            this.lastUnreadCount = 0;
            this.currentController = null;
            this.isLoading = false;

            this.init();
        }

        init() {
            this.bindEvents();
            this.updateAutoRefreshIndicator();
            this.updateStatusLabel();
            this.loadNotifications();
            this.startAutoRefresh();

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.hideActionModal();
                }
            });

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopAutoRefresh();
                } else {
                    this.startAutoRefresh();
                    this.loadNotifications();
                }
            });

            window.addEventListener('beforeunload', () => {
                this.cleanup();
            });
        }

        bindEvents() {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const filter = e.target.dataset.filter;
                    this.setFilter(filter);
                });
            });

            const filters = ['typeFilter', 'dateFilter', 'priorityFilter'];
            filters.forEach(filterId => {
                const filterElement = document.getElementById(filterId);
                if (filterElement) {
                    filterElement.addEventListener('change', () => {
                        this.currentPage = 1;
                        this.loadNotifications();
                    });
                }
            });
        }

        setFilter(filter) {
            this.currentFilter = filter;
            this.currentPage = 1;

            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.filter === filter) {
                    btn.classList.add('active');
                }
            });

            this.updateStatusLabel();
            this.loadNotifications();
        }

        async loadNotifications(silent = false) {
            if (this.isLoading) return;
            this.isLoading = true;
            
            try {
                if (this.currentController && !this.currentController.signal.aborted) {
                    this.currentController.abort();
                }
                if (!silent) {
                    const container = document.getElementById('notificationsContainer');
                    if (container) {
                        container.innerHTML = `
                            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-spinner fa-spin fa-2x"></i>
                                <div style="margin-top: 12px;">Loading notifications...</div>
                            </div>
                        `;
                    }
                }
                
                let url = `../api/notifications.php?action=list&page=${this.currentPage}&limit=${this.limit}&status=${this.currentFilter}`;
                const typeFilter = document.getElementById('typeFilter');
                if (typeFilter && typeFilter.value) url += `&type=${encodeURIComponent(typeFilter.value)}`;
                const dateFilter = document.getElementById('dateFilter');
                if (dateFilter && dateFilter.value) url += `&date=${encodeURIComponent(dateFilter.value)}`;
                const priorityFilter = document.getElementById('priorityFilter');
                if (priorityFilter && priorityFilter.value) url += `&priority=${encodeURIComponent(priorityFilter.value)}`;
                
                this.currentController = new AbortController();
                const timeoutId = setTimeout(() => {
                    if (this.currentController && !this.currentController.signal.aborted) {
                        this.currentController.abort();
                    }
                }, 15000);
                
                const response = await fetch(url, {
                    signal: this.currentController.signal,
                    headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' }
                });
                clearTimeout(timeoutId);
                
                if (!response.ok) throw new Error(`Server error: ${response.status}`);
                
                const data = await response.json();
                if (data.success) {
                    this.notifications = data.notifications || [];
                    this.updateAnalytics();
                    this.renderNotifications();
                    this.updatePagination(data.pagination || { total: this.notifications.length, page: 1, limit: this.limit });
                    
                    if (data.unread_count > this.lastUnreadCount && this.lastUnreadCount > 0) {
                        this.showNewNotificationAlert(data.unread_count - this.lastUnreadCount);
                    }
                    this.lastUnreadCount = data.unread_count || 0;
                    this.updateUnreadBadge(data.unread_count || 0);
                } else {
                    throw new Error(data.error || 'Failed to load notifications');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.showError(error.message || 'Failed to load notifications');
                    if (!silent) this.showToast('Failed to load notifications', 'error');
                }
            }
            this.isLoading = false;
        }

        renderNotifications() {
            const container = document.getElementById('notificationsContainer');
            container.style.opacity = '0.7';

            if (this.notifications.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-bell-slash"></i>
                        </div>
                        <h3>No notifications found</h3>
                        <p>You don't have any ${this.currentFilter === 'all' ? '' : this.currentFilter} notifications yet.</p>
                        <button onclick="notificationsPage.loadNotifications()" class="empty-state-btn" style="padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: white; margin-top: 15px; cursor: pointer;">
                            <i class="fas fa-refresh"></i> Refresh
                        </button>
                    </div>
                `;
                container.style.opacity = '1';
                return;
            }

            const html = `
                <div class="notifications-grid">
                    ${this.notifications.map((notification, index) => this.renderNotificationCard(notification, index)).join('')}
                </div>
            `;

            container.innerHTML = html;

            setTimeout(() => {
                container.style.opacity = '1';
                const cards = container.querySelectorAll('.notification-card');
                cards.forEach((card, index) => {
                    card.style.animationDelay = `${index * 50}ms`;
                    card.classList.add('slide-in');
                });
            }, 50);
        }

        renderNotificationCard(notification, index = 0) {
            const timeAgo = notification.time_ago || this.getTimeAgo(new Date(notification.created_at));
            const icon = this.getNotificationIcon(notification.notification_type);
            const iconColor = this.getNotificationIconColor(notification.notification_type);
            const priorityClass = `priority-${notification.priority || 'medium'}`;

            return `
                <div class="notification-card ${notification.status === 'unread' || notification.is_read === false ? 'unread' : ''} ${priorityClass}"
                     data-id="${notification.id}"
                     style="animation-delay: ${index * 50}ms">
                    <div class="notification-card-header">
                        <div class="notification-card-icon" style="background: ${iconColor};">
                            <i class="${icon}"></i>
                        </div>
                        <div class="notification-card-content">
                            <div class="notification-card-top">
                                <h3 class="notification-card-title">${this.escapeHtml(notification.title)}</h3>
                                <div class="notification-card-actions">
                                    <input type="checkbox"
                                           onchange="notificationsPage.toggleSelection('${notification.id}')"
                                           class="notification-checkbox">
                                    <button class="notification-action-btn dropdown-trigger"
                                            onclick="notificationsPage.showActionModal('${notification.id}')"
                                            title="Actions">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="notification-card-message">${this.escapeHtml(notification.message)}</p>
                            <div class="notification-card-meta">
                                <div class="notification-meta-left">
                                    <span class="notification-time">${timeAgo}</span>
                                    <span class="meta-separator">•</span>
                                    <span class="notification-type">${(notification.notification_type || 'system').replace('_', ' ')}</span>
                                    <span class="meta-separator">•</span>
                                    <span class="notification-priority ${notification.priority || 'medium'}">
                                        ${(notification.priority || 'medium').toUpperCase()}
                                    </span>
                                </div>
                                ${notification.status === 'unread' || notification.is_read === false ? '<div class="unread-indicator"></div>' : ''}
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

        getNotificationIconColor(type) {
            const colors = {
                'parcel_created': '#10b981',
                'parcel_status_change': '#3b82f6',
                'delivery_assigned': '#f59e0b',
                'delivery_completed': '#22c55e',
                'driver_unavailable': '#ef4444',
                'payment_received': '#8b5cf6',
                'urgent_delivery': '#f97316',
                'system_alert': '#6b7280',
                'customer_inquiry': '#06b6d4'
            };
            return colors[type] || '#6b7280';
        }

        toggleSelection(notificationId) {
            if (this.selectedNotifications.has(notificationId)) {
                this.selectedNotifications.delete(notificationId);
            } else {
                this.selectedNotifications.add(notificationId);
            }
            this.updateBulkActions();
        }

        updateBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            if (this.selectedNotifications.size > 0) {
                bulkActions.style.display = 'flex';
                selectedCount.textContent = this.selectedNotifications.size;
            } else {
                bulkActions.style.display = 'none';
            }
        }

        clearSelection() {
            this.selectedNotifications.clear();
            document.querySelectorAll('.notification-card input[type="checkbox"]').forEach(cb => { cb.checked = false; });
            this.updateBulkActions();
        }

        showActionModal(notificationId) {
            const notification = this.notifications.find(n => n.id === notificationId);
            if (!notification) return;

            const modal = document.getElementById('actionModal');
            const buttonsContainer = document.getElementById('actionModalButtons');
            const buttons = [];

            if (notification.status === 'unread' || notification.is_read === false) {
                buttons.push(`
                    <button class="action-modal-btn mark-read-btn" onclick="notificationsPage.markAsRead('${notificationId}'); notificationsPage.hideActionModal();">
                        <i class="fas fa-check"></i> <span>Mark as Read</span>
                    </button>
                `);
            } else {
                buttons.push(`
                    <button class="action-modal-btn mark-unread-btn" onclick="notificationsPage.markAsUnread('${notificationId}'); notificationsPage.hideActionModal();">
                        <i class="fas fa-undo"></i> <span>Mark as Unread</span>
                    </button>
                `);
            }

            buttons.push(`
                <button class="action-modal-btn archive-btn" onclick="notificationsPage.archiveNotification('${notificationId}'); notificationsPage.hideActionModal();">
                    <i class="fas fa-archive"></i> <span>Archive</span>
                </button>
                <button class="action-modal-btn delete-btn" onclick="notificationsPage.deleteNotification('${notificationId}'); notificationsPage.hideActionModal();">
                    <i class="fas fa-trash"></i> <span>Delete</span>
                </button>
            `);

            buttonsContainer.innerHTML = buttons.join('');
            modal.classList.add('show');
        }

        hideActionModal() {
            document.getElementById('actionModal').classList.remove('show');
        }

        async markAsRead(notificationId) { await this.updateNotification(notificationId, 'mark_read'); }
        async markAsUnread(notificationId) { await this.updateNotification(notificationId, 'mark_unread'); }
        async archiveNotification(notificationId) { await this.updateNotification(notificationId, 'archive'); }

        async deleteNotification(notificationId) {
            if (!confirm('Are you sure you want to delete this notification?')) return;
            try {
                const response = await fetch('../api/notifications.php?action=bulk_action', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notification_id: notificationId, action: 'delete' })
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast('Notification deleted successfully', 'success');
                    this.loadNotifications(true);
                } else throw new Error(data.error);
            } catch (error) {
                this.showToast(error.message || 'Failed to delete notification', 'error');
            }
        }

        async updateNotification(notificationId, action) {
            try {
                const response = await fetch('../api/notifications.php?action=bulk_action', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notification_id: notificationId, action: action })
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast('Notification updated successfully', 'success');
                    this.loadNotifications(true);
                } else throw new Error(data.error);
            } catch (error) {
                this.showToast(error.message || 'Failed to update notification', 'error');
            }
        }

        updatePagination(pagination) {
            this.totalPages = pagination.total ? Math.ceil(pagination.total / this.limit) : 1;
            const paginationEl = document.getElementById('pagination');
            const pageInfo = document.getElementById('pageInfo');
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');

            if (this.totalPages > 1) {
                paginationEl.style.display = 'flex';
                pageInfo.textContent = `Page ${this.currentPage} of ${this.totalPages}`;
                prevBtn.disabled = this.currentPage === 1;
                nextBtn.disabled = this.currentPage === this.totalPages;
            } else {
                paginationEl.style.display = 'none';
            }
        }

        previousPage() {
            if (this.currentPage > 1) { this.currentPage--; this.loadNotifications(); }
        }

        nextPage() {
            if (this.currentPage < this.totalPages) { this.currentPage++; this.loadNotifications(); }
        }

        showError(message) {
            document.getElementById('notificationsContainer').innerHTML = `
                <div style="padding: 40px; text-align: center; color: var(--error-color);">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                    <div style="margin-top: 12px;">${this.escapeHtml(message)}</div>
                    <button onclick="notificationsPage.loadNotifications()" class="empty-state-btn" style="margin-top: 15px; cursor: pointer; padding: 6px 12px;">Try Again</button>
                </div>
            `;
        }

        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            const colors = { success: '#22c55e', error: '#ef4444', info: '#3b82f6', warning: '#f59e0b' };
            toast.style.cssText = `position:fixed; top:20px; right:20px; background:${colors[type]}; color:white; padding:12px 20px; border-radius:8px; z-index:10000; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1); transform:translateX(100%); transition:transform 0.3s;`;
            toast.innerHTML = `${this.escapeHtml(message)}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.style.transform = 'translateX(0)', 50);
            setTimeout(() => { toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 300); }, 3000);
        }

        getTimeAgo(date) {
            const diffInSeconds = Math.floor((new Date() - date) / 1000);
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
            if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
            return date.toLocaleDateString();
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        startAutoRefresh() {
            if (this.refreshInterval) clearInterval(this.refreshInterval);
            this.refreshInterval = setInterval(() => {
                if (this.autoRefreshEnabled && !document.hidden) this.loadNotifications(true);
            }, 30000);
        }

        stopAutoRefresh() {
            if (this.refreshInterval) { clearInterval(this.refreshInterval); this.refreshInterval = null; }
        }

        cleanup() { this.stopAutoRefresh(); if (this.currentController) this.currentController.abort(); }

        toggleAutoRefresh() {
            this.autoRefreshEnabled = !this.autoRefreshEnabled;
            if (this.autoRefreshEnabled) { this.startAutoRefresh(); this.showToast('Auto-refresh enabled', 'success'); }
            else { this.stopAutoRefresh(); this.showToast('Auto-refresh disabled', 'info'); }
            this.updateAutoRefreshIndicator();
        }

        showNewNotificationAlert(count) { this.showToast(`${count} new notification(s)`, 'info'); }

        updateUnreadBadge(count) {
            const badges = document.querySelectorAll('.unread-count, .notification-badge');
            badges.forEach(b => {
                if (count > 0) { b.textContent = count > 99 ? '99+' : count; b.style.display = 'block'; }
                else b.style.display = 'none';
            });
        }

        updateAnalytics() {
            const visible = this.notifications.length;
            const unread = this.notifications.filter(n => n.status === 'unread' || n.is_read === false).length;
            const highPri = this.notifications.filter(n => (n.priority || '').toLowerCase() === 'high').length;
            const archived = this.notifications.filter(n => n.status === 'archived').length;
            
            this.setTextContent('statVisible', visible);
            this.setTextContent('statUnread', unread);
            this.setTextContent('statHighPriority', highPri);
            this.setTextContent('statArchived', archived);
            this.updateStatusLabel();
            this.setTextContent('statFilterSummary', this.buildFilterSummary());
        }

        buildFilterSummary() {
            const tf = document.getElementById('typeFilter');
            const df = document.getElementById('dateFilter');
            const pf = document.getElementById('priorityFilter');
            return `${tf && tf.selectedIndex > 0 ? tf.options[tf.selectedIndex].text : 'All'} • ${df && df.selectedIndex > 0 ? df.options[df.selectedIndex].text : 'Any time'} • ${pf && pf.selectedIndex > 0 ? pf.options[pf.selectedIndex].text : 'Any'}`;
        }

        updateStatusLabel() {
            const l = { all: 'All', unread: 'Unread', read: 'Read', archived: 'Archived', dismissed: 'Dismissed' };
            this.setTextContent('statStatusLabel', l[this.currentFilter] || 'All');
        }

        updateAutoRefreshIndicator() {
            const btn = document.getElementById('autoRefreshBtn');
            const lbl = document.getElementById('autoRefreshLabel');
            if (btn && lbl) {
                if (this.autoRefreshEnabled) { btn.classList.remove('auto-refresh-off'); lbl.textContent = 'Auto-refresh on'; }
                else { btn.classList.add('auto-refresh-off'); lbl.textContent = 'Auto-refresh off'; }
            }
        }

        setTextContent(elementId, value) { const el = document.getElementById(elementId); if (el) el.textContent = value; }

        clearFilters() {
            ['typeFilter', 'dateFilter', 'priorityFilter'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            this.setFilter('all');
            this.showToast('Filters reset', 'info');
        }

        async markAllAsRead() {
            try {
                const response = await fetch('../api/notifications.php?action=mark_all_read', { method: 'POST' });
                const data = await response.json();
                if (data.success) { this.showToast('All notifications read', 'success'); this.loadNotifications(); }
                else throw new Error(data.error);
            } catch (error) { this.showToast('Failed to mark all as read', 'error'); }
        }
    }

    const notificationsPage = new NotificationsPage();

    function refreshNotifications() { notificationsPage.loadNotifications(); }
    function previousPage() { notificationsPage.previousPage(); }
    function nextPage() { notificationsPage.nextPage(); }
    function clearSelection() { notificationsPage.clearSelection(); }
    
    async function markSelectedAsRead() {
        if (!notificationsPage.selectedNotifications.size) return;
        try {
            const response = await fetch('../api/notifications.php?action=bulk_action', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', notification_ids: Array.from(notificationsPage.selectedNotifications) })
            });
            const data = await response.json();
            if (data.success) { notificationsPage.clearSelection(); notificationsPage.loadNotifications(true); }
        } catch (e) {
            console.error(e);
        }
    }
    
    async function archiveSelected() {
        if (!notificationsPage.selectedNotifications.size) return;
        try {
            const response = await fetch('../api/notifications.php?action=bulk_action', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'archive', notification_ids: Array.from(notificationsPage.selectedNotifications) })
            });
            const data = await response.json();
            if (data.success) { notificationsPage.clearSelection(); notificationsPage.loadNotifications(true); }
        } catch (e) {
            console.error(e);
        }
    }
    
    async function deleteSelected() {
        if (!notificationsPage.selectedNotifications.size) return;
        if (!confirm('Are you sure you want to delete selected notifications?')) return;
        try {
            const response = await fetch('../api/notifications.php?action=bulk_action', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', notification_ids: Array.from(notificationsPage.selectedNotifications) })
            });
            const data = await response.json();
            if (data.success) { notificationsPage.clearSelection(); notificationsPage.loadNotifications(true); }
        } catch (e) {
            console.error(e);
        }
    }
</script>
</body>
</html>

