class NotificationSystem {
    constructor() {
        this.container = document.querySelector('.notifications-container');
        this.badge = document.querySelector('.badge'); // header badge
    this.sidebar = document.getElementById('notificationSidebar');
    this.overlay = document.getElementById('notificationOverlay');
    // Support either an in-header open button (legacy) or a link to the page
    this.openBtn = document.querySelector('.notification-btn') || document.querySelector('.notification-link');
        this.closeBtn = document.querySelector('.notification-close-btn');

        this.notifications = [];

        this.init();
    }

    async init() {
        if (!this.container) return;

        this.setupEvents();
        await this.loadNotifications();
        this.renderNotifications();
        this.updateBadge();

        // Optional: setup live updates from Supabase (if integrated)
        // this.setupRealtime();
    }

    setupEvents() {
        this.openBtn?.addEventListener('click', () => {
            this.sidebar.style.display = 'block';
            this.overlay.style.display = 'block';
        });

        this.closeBtn?.addEventListener('click', () => {
            this.sidebar.style.display = 'none';
            this.overlay.style.display = 'none';
        });

        this.overlay?.addEventListener('click', () => {
            this.sidebar.style.display = 'none';
            this.overlay.style.display = 'none';
        });
    }

    async loadNotifications() {
        // Load from localStorage
        const saved = localStorage.getItem('company_notifications');
        if (saved) {
            try {
                this.notifications = JSON.parse(saved);
            } catch (e) {
                console.error('Error parsing localStorage notifications', e);
                this.notifications = [];
            }
        }

        // Fetch from API
        try {
            // Use a page-relative path so this works whether the app is served from
            // a subfolder (e.g. /WDParcelSendReceiverPWA/) or root. The notifications
            // page lives in company-app/pages/, so the API is at ../api/fetch_notifications.php
            const res = await fetch('../api/fetch_notifications.php');
            if (!res.ok) {
                throw new Error('Network response was not ok: ' + res.status + ' ' + res.statusText);
            }
            const data = await res.json();

            if (data.success && Array.isArray(data.notifications)) {
                this.notifications = data.notifications.map(n => ({
                    id: n.id || n.notification_id || n.notificationId || n.notificationID,
                    title: n.title || 'No Title',
                    message: n.message || n.content || '',
                    time: n.created_at || n.time || new Date().toISOString(),
                    unread: n.unread ?? true,
                    action: n.action || null
                }));

                this.saveNotifications();
            }
        } catch (err) {
            console.error('Error fetching notifications from API:', err);
        }

        this.updateBadge();
    }

    saveNotifications() {
        localStorage.setItem('company_notifications', JSON.stringify(this.notifications));
        this.updateBadge();
    }

    renderNotifications() {
        if (!this.container) return;

        this.container.innerHTML = '';

        if (!this.notifications || this.notifications.length === 0) {
            this.container.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }

        this.notifications
            .sort((a, b) => new Date(b.time) - new Date(a.time))
            .forEach(notification => {
                const card = document.createElement('div');
                card.className = `notification-card ${notification.unread ? 'unread' : ''}`;

                const actionLink = notification.action
                    ? `<div class="notification-card-actions">
                           <a href="${notification.action}" class="notification-action">
                               View Details <i class="fas fa-arrow-right"></i>
                           </a>
                       </div>`
                    : '';

                card.innerHTML = `
                    <div class="notification-card-header">
                        <h3 class="notification-card-title">${notification.title}</h3>
                        <span class="notification-card-time">${this.formatTime(notification.time)}</span>
                    </div>
                    <div class="notification-card-content">
                        <p>${notification.message}</p>
                    </div>
                    ${actionLink}
                `;

                this.container.appendChild(card);
            });
    }

    formatTime(time) {
        const date = new Date(time);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return `${Math.floor(diff/60000)} minutes ago`;
        if (diff < 86400000) return `${Math.floor(diff/3600000)} hours ago`;
        if (diff < 604800000) return `${Math.floor(diff/86400000)} days ago`;
        return date.toLocaleDateString();
    }

    addNotification(notification) {
        const newNotification = {
            ...notification,
            time: notification.created_at || new Date().toISOString(),
            unread: true
        };
        this.notifications.unshift(newNotification);
        this.saveNotifications();
        this.renderNotifications();
    }

    markAsRead(notificationId) {
        const notification = this.notifications.find(n => n.id === notificationId);
        if (notification) {
            notification.unread = false;
            this.saveNotifications();
            this.renderNotifications();
        }
    }

    markAllAsRead() {
        this.notifications.forEach(n => n.unread = false);
        this.saveNotifications();
        this.renderNotifications();
    }

    updateBadge() {
        if (!this.badge) return;
        const unreadCount = this.notifications.filter(n => n.unread).length;
        this.badge.textContent = unreadCount;
        this.badge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
    }

    // Optional: setupRealtime for live notifications
    // setupRealtime() { ... }
}

// Initialize after DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.notificationSystem = new NotificationSystem();
});
