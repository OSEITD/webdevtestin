<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../includes/auth_guard.php';
requireAuth();
requireRole(['outlet_staff', 'outlet_manager']);
require_once '../config.php';

$current_user_id = $_SESSION['user_id'];
$outlet_id = $_SESSION['outlet_id'];
$company_id = $_SESSION['company_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Outlet Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <link rel="stylesheet" href="../css/notifications-page.css">
</head>
<body>
    <div class="mobile-dashboard">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="menu-overlay" id="menuOverlay"></div>

        <main class="main-content">
            <div class="content-container notifications-wide">
                <a href="outlet_dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>

                <section class="notifications-shell">
                    <header class="notifications-hero">
                        <div class="notifications-hero-text">
                            <span class="notifications-hero-eyebrow">Outlet Alerts Centre</span>
                            <h1>
                                <i class="fas fa-bell"></i>
                                Notifications
                            </h1>
                            <p>Stay on top of parcel updates, driver alerts, and system messages with real-time insights designed for outlet teams.</p>
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
                    <button id="prevPage" onclick="previousPage()">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <span id="pageInfo"></span>
                    <button id="nextPage" onclick="nextPage()">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <div id="actionModal" class="action-modal">
        <div class="action-modal-backdrop" onclick="notificationsPage.hideActionModal()" onmouseover="event.stopPropagation();"></div>
        <div class="action-modal-content" onclick="event.stopPropagation();" onmouseover="event.stopPropagation();">
            <div class="action-modal-header">
                <h3>Notification Actions</h3>
                <button class="action-modal-close" onclick="notificationsPage.hideActionModal()" onmouseover="event.stopPropagation();">
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
                if (this.isLoading) {
                    console.log('Already loading notifications, skipping duplicate request.');
                    return;
                }
                this.isLoading = true;
                try {
                    if (this.currentController && !this.currentController.signal.aborted) {
                        console.log('Cancelling previous request...');
                        this.currentController.abort();
                    }
                    if (!silent) {
                        const container = document.getElementById('notificationsContainer');
                        if (container) {
                            container.innerHTML = `
                                <div class="loading-container">
                                    <i class="fas fa-spinner fa-spin pulse"></i>
                                    <span style="margin-left: 12px;">Loading notifications...</span>
                                </div>
                            `;
                        }
                    }
                    let url = `../api/notifications/notifications.php?page=${this.currentPage}&limit=${this.limit}&status=${this.currentFilter}`;
                    const typeFilter = document.getElementById('typeFilter');
                    if (typeFilter && typeFilter.value) {
                        url += `&type=${encodeURIComponent(typeFilter.value)}`;
                    }
                    const dateFilter = document.getElementById('dateFilter');
                    if (dateFilter && dateFilter.value) {
                        url += `&date=${encodeURIComponent(dateFilter.value)}`;
                    }
                    const priorityFilter = document.getElementById('priorityFilter');
                    if (priorityFilter && priorityFilter.value) {
                        url += `&priority=${encodeURIComponent(priorityFilter.value)}`;
                    }
                    this.currentController = new AbortController();
                    const timeoutId = setTimeout(() => {
                        try {
                            if (this.currentController && !this.currentController.signal.aborted) {
                                console.log('Request timeout reached, aborting...');
                                this.currentController.abort();
                            }
                        } catch (error) {
                            console.warn('Error during timeout abort:', error);
                        }
                    }, 15000);
                    console.log('Making request to:', url);
                    const response = await fetch(url, {
                        signal: this.currentController.signal,
                        headers: {
                            'Accept': 'application/json',
                            'Cache-Control': 'no-cache'
                        }
                    });
                    clearTimeout(timeoutId);
                    console.log('Response received:', response.status, response.statusText);
                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status} ${response.statusText}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Invalid response format - expected JSON');
                    }
                    const data = await response.json();
                    if (data.success) {
                        this.notifications = data.notifications || [];
                        this.updateAnalytics();
                        this.renderNotifications();
                        this.updatePagination(data.pagination || {});
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
                        console.error('Error loading notifications:', error);
                    }
                    if (error.name === 'AbortError') {
                        this.currentController = null;
                    }
                    let errorMessage = 'Failed to load notifications';
                    if (error.name === 'AbortError') {
                        if (!silent) {
                            errorMessage = 'Request was cancelled or timed out';
                        } else {
                            this.isLoading = false;
                            return;
                        }
                    } else if (error.message.includes('NetworkError') || error.message.includes('fetch')) {
                        errorMessage = 'Network error - please check your internet connection';
                    } else if (error.message.includes('Server error: 401')) {
                        errorMessage = 'Session expired - please log in again';
                        setTimeout(() => {
                            window.location.href = '../login.php';
                        }, 2000);
                    } else if (error.message.includes('Server error: 403')) {
                        errorMessage = 'Access denied - insufficient permissions';
                    } else if (error.message.includes('Server error: 500')) {
                        errorMessage = 'Server error - please try again later';
                    } else if (error.message) {
                        errorMessage = error.message;
                    }
                    this.showError(errorMessage);
                    if (!silent && error.name !== 'AbortError') {
                        this.showToast(errorMessage, 'error');
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
                            <button onclick="notificationsPage.loadNotifications()" class="empty-state-btn">
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
                const timeAgo = this.getTimeAgo(new Date(notification.created_at));
                const icon = this.getNotificationIcon(notification.notification_type);
                const iconColor = this.getNotificationIconColor(notification.notification_type);
                const priorityClass = `priority-${notification.priority || 'medium'}`;

                return `
                    <div class="notification-card ${notification.status === 'unread' ? 'unread' : ''} ${priorityClass}"
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
                                        <span class="notification-type">${notification.notification_type.replace('_', ' ')}</span>
                                        <span class="meta-separator">•</span>
                                        <span class="notification-priority ${notification.priority || 'medium'}">
                                            ${(notification.priority || 'medium').toUpperCase()}
                                        </span>
                                    </div>
                                    ${notification.status === 'unread' ? '<div class="unread-indicator"></div>' : ''}
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
                document.querySelectorAll('.notification-card input[type="checkbox"]').forEach(cb => {
                    cb.checked = false;
                });
                this.updateBulkActions();
            }

            showActionModal(notificationId) {
                const notification = this.notifications.find(n => n.id === notificationId);
                if (!notification) return;

                const modal = document.getElementById('actionModal');
                const buttonsContainer = document.getElementById('actionModalButtons');

                const buttons = [];

                if (notification.status === 'unread') {
                    buttons.push(`
                        <button class="action-modal-btn mark-read-btn"
                                onclick="event.stopPropagation(); notificationsPage.markAsRead('${notificationId}'); notificationsPage.hideActionModal();"
                                onmouseover="event.stopPropagation();"
                                onmouseout="event.stopPropagation();">
                            <i class="fas fa-check"></i>
                            <span>Mark as Read</span>
                        </button>
                    `);
                } else {
                    buttons.push(`
                        <button class="action-modal-btn mark-unread-btn"
                                onclick="event.stopPropagation(); notificationsPage.markAsUnread('${notificationId}'); notificationsPage.hideActionModal();"
                                onmouseover="event.stopPropagation();"
                                onmouseout="event.stopPropagation();">
                            <i class="fas fa-undo"></i>
                            <span>Mark as Unread</span>
                        </button>
                    `);
                }

                buttons.push(`
                    <button class="action-modal-btn archive-btn"
                            onclick="event.stopPropagation(); notificationsPage.archiveNotification('${notificationId}'); notificationsPage.hideActionModal();"
                            onmouseover="event.stopPropagation();"
                            onmouseout="event.stopPropagation();">
                        <i class="fas fa-archive"></i>
                        <span>Archive</span>
                    </button>
                `);

                buttons.push(`
                    <button class="action-modal-btn delete-btn"
                            onclick="event.stopPropagation(); notificationsPage.deleteNotification('${notificationId}'); notificationsPage.hideActionModal();"
                            onmouseover="event.stopPropagation();"
                            onmouseout="event.stopPropagation();">
                        <i class="fas fa-trash"></i>
                        <span>Delete</span>
                    </button>
                `);

                buttonsContainer.innerHTML = buttons.join('');

                modal.style.pointerEvents = 'all';
                modal.classList.add('show');

                document.body.style.overflow = 'hidden';
            }

            hideActionModal() {
                const modal = document.getElementById('actionModal');
                modal.classList.remove('show');

                document.body.style.overflow = '';
            }

            async markAsRead(notificationId) {
                await this.updateNotification(notificationId, 'mark_read');
            }

            async markAsUnread(notificationId) {
                await this.updateNotification(notificationId, 'mark_unread');
            }

            async archiveNotification(notificationId) {
                await this.updateNotification(notificationId, 'archive');
            }

            async deleteNotification(notificationId) {
                if (!notificationId) {
                    this.showToast('Invalid notification ID', 'error');
                    return;
                }

                if (!confirm('Are you sure you want to delete this notification?')) {
                    return;
                }

                try {
                    const response = await fetch('../api/notifications/notifications.php', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ notification_id: notificationId })
                    });

                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status} ${response.statusText}`);
                    }

                    const data = await response.json();
                    if (data.success) {
                        this.showToast('Notification deleted successfully', 'success');
                        this.loadNotifications(true);
                    } else {
                        throw new Error(data.error || 'Failed to delete notification');
                    }
                } catch (error) {
                    console.error('Error deleting notification:', error);

                    let errorMessage = 'Failed to delete notification';
                    if (error.message.includes('Server error: 401')) {
                        errorMessage = 'Session expired - please log in again';
                    } else if (error.message.includes('Server error: 403')) {
                        errorMessage = 'Access denied';
                    } else if (error.message) {
                        errorMessage = error.message;
                    }

                    this.showToast(errorMessage, 'error');
                }
            }

            async updateNotification(notificationId, action) {
                try {
                    if (!notificationId || !action) {
                        throw new Error('Missing notification ID or action');
                    }

                    const response = await fetch('../api/notifications/notifications.php', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            notification_id: notificationId,
                            action: action
                        })
                    });

                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status} ${response.statusText}`);
                    }

                    const data = await response.json();
                    if (data.success) {
                        this.showToast('Notification updated successfully', 'success');
                        this.loadNotifications(true);
                    } else {
                        throw new Error(data.error || 'Failed to update notification');
                    }
                } catch (error) {
                    console.error('Error updating notification:', error);

                    let errorMessage = 'Failed to update notification';
                    if (error.message.includes('Server error: 401')) {
                        errorMessage = 'Session expired - please log in again';
                    } else if (error.message.includes('Server error: 403')) {
                        errorMessage = 'Access denied';
                    } else if (error.message) {
                        errorMessage = error.message;
                    }

                    this.showToast(errorMessage, 'error');
                }
            }

            updatePagination(pagination) {
                this.totalPages = Math.ceil(pagination.total / this.limit);
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
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadNotifications();
                }
            }

            nextPage() {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadNotifications();
                }
            }

            showError(message) {
                const container = document.getElementById('notificationsContainer');
                container.innerHTML = `
                    <div class="empty-state error-state">
                        <div class="empty-state-icon" style="background: var(--error-color);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3>Oops! Something went wrong</h3>
                        <p>${this.escapeHtml(message)}</p>
                        <div style="display: flex; gap: 12px; justify-content: center; margin-top: 20px;">
                            <button onclick="notificationsPage.loadNotifications()" class="empty-state-btn">
                                <i class="fas fa-refresh"></i> Try Again
                            </button>
                            <button onclick="location.reload()" class="empty-state-btn" style="background: var(--text-secondary);">
                                <i class="fas fa-sync"></i> Reload Page
                            </button>
                        </div>
                    </div>
                `;
            }

            showToast(message, type = 'info') {
                document.querySelectorAll('.toast-notification').forEach(toast => {
                    toast.remove();
                });

                const toast = document.createElement('div');
                toast.className = 'toast-notification';

                const colors = {
                    success: 'var(--success-color)',
                    error: 'var(--error-color)',
                    info: 'var(--info-color)',
                    warning: 'var(--warning-color)'
                };

                const icons = {
                    success: 'fas fa-check-circle',
                    error: 'fas fa-exclamation-circle',
                    info: 'fas fa-info-circle',
                    warning: 'fas fa-exclamation-triangle'
                };

                toast.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${colors[type] || colors.info};
                    color: white;
                    padding: 16px 20px;
                    border-radius: 12px;
                    font-size: 14px;
                    font-weight: 500;
                    z-index: 10000;
                    box-shadow: var(--shadow-lg);
                    transform: translateX(400px);
                    transition: var(--transition-medium);
                    max-width: 400px;
                    word-wrap: break-word;
                `;

                toast.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="${icons[type] || icons.info}" style="font-size: 18px;"></i>
                        <span>${this.escapeHtml(message)}</span>
                        <button onclick="this.parentElement.parentElement.remove()"
                                style="background: none; border: none; color: white; cursor: pointer; margin-left: auto; font-size: 16px;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;

                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.style.transform = 'translateX(0)';
                }, 100);

                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.style.transform = 'translateX(400px)';
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.remove();
                            }
                        }, 300);
                    }
                }, 5000);
            }

            getTimeAgo(date) {
                const now = new Date();
                const diffInSeconds = Math.floor((now - date) / 1000);

                if (diffInSeconds < 60) return 'Just now';
                if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min ago`;
                if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
                if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
                return date.toLocaleDateString();
            }

            escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            startAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }

                this.refreshInterval = setInterval(() => {
                    if (this.autoRefreshEnabled && !document.hidden) {
                        this.loadNotifications(true);
                    }
                }, 30000);
            }

            stopAutoRefresh() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                    this.refreshInterval = null;
                }
            }

            cleanup() {
                this.stopAutoRefresh();

                if (this.currentController) {
                    this.currentController.abort();
                    this.currentController = null;
                }

                this.notifications = [];
                this.selectedNotifications.clear();
            }

            toggleAutoRefresh() {
                this.autoRefreshEnabled = !this.autoRefreshEnabled;
                if (this.autoRefreshEnabled) {
                    this.startAutoRefresh();
                    this.showToast('Auto-refresh enabled', 'info');
                } else {
                    this.stopAutoRefresh();
                    this.showToast('Auto-refresh disabled', 'info');
                }
                this.updateAutoRefreshIndicator();
            }

            showNewNotificationAlert(count) {
                const alert = document.createElement('div');
                alert.style.cssText = `
                    position: fixed;
                    top: 80px;
                    right: 20px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 16px 20px;
                    border-radius: 12px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
                    z-index: 10001;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transform: translateX(400px);
                    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                    border: 1px solid rgba(255,255,255,0.2);
                `;
                alert.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-bell" style="font-size: 18px;"></i>
                        <div>
                            <div style="font-weight: 600;">${count} new notification${count > 1 ? 's' : ''}</div>
                            <div style="font-size: 12px; opacity: 0.9;">Click to refresh</div>
                        </div>
                    </div>
                `;

                alert.onclick = () => {
                    this.loadNotifications();
                    alert.remove();
                };

                document.body.appendChild(alert);

                setTimeout(() => {
                    alert.style.transform = 'translateX(0)';
                }, 100);

                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.style.transform = 'translateX(400px)';
                        setTimeout(() => alert.remove(), 400);
                    }
                }, 5000);
            }

            updateUnreadBadge(count) {
                const badges = document.querySelectorAll('.unread-count, .notification-badge');
                badges.forEach(badge => {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                });

                const baseTitle = 'Notifications - Parcel Management';
                document.title = count > 0 ? `(${count}) ${baseTitle}` : baseTitle;
            }

            updateAnalytics() {
                const visibleCount = this.notifications.length;
                const unreadCount = this.notifications.filter(n => (n.status || '').toLowerCase() === 'unread').length;
                const highPriorityCount = this.notifications.filter(n => (n.priority || '').toLowerCase() === 'high').length;
                const archivedCount = this.notifications.filter(n => (n.status || '').toLowerCase() === 'archived').length;

                this.setTextContent('statVisible', visibleCount);
                this.setTextContent('statUnread', unreadCount);
                this.setTextContent('statHighPriority', highPriorityCount);
                this.setTextContent('statArchived', archivedCount);
                this.setTextContent('statFilterSummary', this.buildFilterSummary());
                this.updateStatusLabel();
            }

            buildFilterSummary() {
                const typeLabel = this.getSelectedOptionLabel('typeFilter', 'All types');
                const dateLabel = this.getSelectedOptionLabel('dateFilter', 'Any time');
                const priorityLabel = this.getSelectedOptionLabel('priorityFilter', 'Any priority');
                return `${typeLabel} • ${dateLabel} • ${priorityLabel}`;
            }

            getSelectedOptionLabel(elementId, fallback) {
                const select = document.getElementById(elementId);
                if (!select || !select.options || select.selectedIndex < 0) {
                    return fallback;
                }
                const option = select.options[select.selectedIndex];
                return option ? option.textContent : fallback;
            }

            updateStatusLabel() {
                this.setTextContent('statStatusLabel', this.getStatusLabel());
            }

            getStatusLabel() {
                const labels = {
                    all: 'All statuses',
                    unread: 'Unread only',
                    read: 'Read only',
                    archived: 'Archived only',
                    dismissed: 'Dismissed only'
                };
                if (labels[this.currentFilter]) {
                    return labels[this.currentFilter];
                }
                return this.currentFilter ? this.currentFilter.charAt(0).toUpperCase() + this.currentFilter.slice(1) : 'All statuses';
            }

            updateAutoRefreshIndicator() {
                const button = document.getElementById('autoRefreshBtn');
                const label = document.getElementById('autoRefreshLabel');
                if (!button || !label) {
                    return;
                }
                if (this.autoRefreshEnabled) {
                    button.classList.remove('auto-refresh-off');
                    label.textContent = 'Auto-refresh on';
                } else {
                    button.classList.add('auto-refresh-off');
                    label.textContent = 'Auto-refresh off';
                }
            }

            setTextContent(elementId, value) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.textContent = value;
                }
            }

            clearFilters() {
                ['typeFilter', 'dateFilter', 'priorityFilter'].forEach((filterId) => {
                    const element = document.getElementById(filterId);
                    if (element) {
                        element.value = '';
                    }
                });

                this.setFilter('all');
                this.showToast('Filters reset to default view', 'info');
            }

            async markAllAsRead() {
                try {
                    const response = await fetch('../api/notifications/notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ action: 'mark_all_read' })
                    });

                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status} ${response.statusText}`);
                    }

                    const data = await response.json();
                    if (data.success) {
                        this.showToast(data.message || 'All notifications marked as read', 'success');
                        this.loadNotifications();
                    } else {
                        throw new Error(data.error || 'Failed to mark notifications as read');
                    }
                } catch (error) {
                    console.error('Error marking all notifications as read:', error);
                    this.showToast(error.message || 'Failed to mark notifications as read', 'error');
                }
            }
        }

        async function refreshNotifications() {
            notificationsPage.loadNotifications();
        }

        function previousPage() {
            notificationsPage.previousPage();
        }

        function nextPage() {
            notificationsPage.nextPage();
        }

        async function markSelectedAsRead() {
            const selectedIds = Array.from(notificationsPage.selectedNotifications);
            if (selectedIds.length === 0) {
                notificationsPage.showToast('Please select notifications to mark as read', 'info');
                return;
            }

            try {
                const response = await fetch('../api/notifications/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'bulk_action',
                        notification_ids: selectedIds,
                        bulk_action: 'mark_read'
                    })
                });

                if (!response.ok) {
                    throw new Error(`Server error: ${response.status} ${response.statusText}`);
                }

                const data = await response.json();
                if (data.success) {
                    notificationsPage.showToast(data.message || 'Notifications marked as read', 'success');
                    notificationsPage.clearSelection();
                    notificationsPage.loadNotifications(true);
                } else {
                    throw new Error(data.error || 'Failed to mark notifications as read');
                }
            } catch (error) {
                console.error('Error marking notifications as read:', error);
                notificationsPage.showToast(error.message || 'Failed to mark notifications as read', 'error');
            }
        }

        async function archiveSelected() {
            const selectedIds = Array.from(notificationsPage.selectedNotifications);
            if (selectedIds.length === 0) {
                notificationsPage.showToast('Please select notifications to archive', 'info');
                return;
            }

            try {
                const response = await fetch('../api/notifications/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'bulk_action',
                        notification_ids: selectedIds,
                        bulk_action: 'archive'
                    })
                });

                if (!response.ok) {
                    throw new Error(`Server error: ${response.status} ${response.statusText}`);
                }

                const data = await response.json();
                if (data.success) {
                    notificationsPage.showToast(data.message || 'Notifications archived', 'success');
                    notificationsPage.clearSelection();
                    notificationsPage.loadNotifications(true);
                } else {
                    throw new Error(data.error || 'Failed to archive notifications');
                }
            } catch (error) {
                console.error('Error archiving notifications:', error);
                notificationsPage.showToast(error.message || 'Failed to archive notifications', 'error');
            }
        }

        async function deleteSelected() {
            const selectedIds = Array.from(notificationsPage.selectedNotifications);
            if (selectedIds.length === 0) {
                notificationsPage.showToast('Please select notifications to delete', 'info');
                return;
            }

            const confirmMessage = selectedIds.length === 1
                ? 'Are you sure you want to delete this notification?'
                : `Are you sure you want to delete ${selectedIds.length} notifications?`;

            if (!confirm(confirmMessage)) {
                return;
            }

            try {
                const response = await fetch('../api/notifications/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'bulk_action',
                        notification_ids: selectedIds,
                        bulk_action: 'delete'
                    })
                });

                if (!response.ok) {
                    throw new Error(`Server error: ${response.status} ${response.statusText}`);
                }

                const data = await response.json();
                if (data.success) {
                    notificationsPage.showToast(data.message || 'Notifications deleted', 'success');
                    notificationsPage.clearSelection();
                    notificationsPage.loadNotifications(true);
                } else {
                    throw new Error(data.error || 'Failed to delete notifications');
                }
            } catch (error) {
                console.error('Error deleting notifications:', error);
                notificationsPage.showToast(error.message || 'Failed to delete notifications', 'error');
            }
        }

        function clearSelection() {
            notificationsPage.clearSelection();
        }

        const notificationsPage = new NotificationsPage();
    </script>

    <script src="../assets/js/sidebar-toggle.js"></script>
</body>
</html>
