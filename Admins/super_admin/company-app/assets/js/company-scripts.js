// ==================== COMMON FUNCTIONALITY ====================

// Mobile menu functionality
function initMenuSystem() {
    const menuBtn = document.getElementById('menuBtn');
    const closeMenu = document.getElementById('closeMenu');
    const sidebar = document.getElementById('sidebar');
    const menuOverlay = document.getElementById('menuOverlay');

    // Ensure sidebar/overlay start closed by default. Some pages or cached scripts
    // may leave the `.show` class set — force a consistent initial state here.
    try {
        if (sidebar && sidebar.classList.contains('show')) sidebar.classList.remove('show');
        if (menuOverlay && menuOverlay.classList.contains('show')) menuOverlay.classList.remove('show');
        document.body.style.overflow = '';
    } catch (err) {
        console.debug('initMenuSystem init cleanup error', err);
    }

    function toggleMenu() {
        sidebar.classList.toggle('show');
        menuOverlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    }

    if (menuBtn) {
        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu();
        });
    }
    if (closeMenu) closeMenu.addEventListener('click', toggleMenu);
    if (menuOverlay) menuOverlay.addEventListener('click', toggleMenu);

    document.querySelectorAll('.menu-items a').forEach(item => {
        item.addEventListener('click', toggleMenu);
    });
}

// Custom message box
function showMessageBox(message) {
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    `;
    overlay.id = 'messageBoxOverlay';

    const messageBox = document.createElement('div');
    messageBox.style.cssText = `
        background-color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center;
        max-width: 90%;
        width: 400px;
    `;

    const messageParagraph = document.createElement('p');
    messageParagraph.textContent = message;
    messageParagraph.style.cssText = `
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
        color: #333;
    `;

    const closeButton = document.createElement('button');
    closeButton.textContent = 'OK';
    closeButton.style.cssText = `
        background-color: #3b82f6;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.375rem;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.2s;
    `;
    closeButton.onmouseover = () => closeButton.style.backgroundColor = '#2563eb';
    closeButton.onmouseout = () => closeButton.style.backgroundColor = '#3b82f6';

    closeButton.addEventListener('click', () => {
        document.body.removeChild(overlay);
    });

    messageBox.appendChild(messageParagraph);
    messageBox.appendChild(closeButton);
    overlay.appendChild(messageBox);
    document.body.appendChild(overlay);
}
// ==================== NOTIFICATION SYSTEM ====================
class NotificationSystem {
    constructor() {
        // Check if required elements exist
        if (!document.querySelector('.notification-btn') ||
            !document.getElementById('notificationSidebar')) {
            return;
        }

        // Initialize the system
        this.setupElements();
        this.setupEventListeners();
        this.loadNotifications();
        this.updateUI();

        // Demo mode - remove in production
        this.startDemo();
    }

    setupElements() {
        // Main elements
        this.notificationBtn = document.querySelector('.notification-btn');
        this.sidebar = document.getElementById('notificationSidebar');
        this.overlay = document.getElementById('notificationOverlay');
        this.notificationList = document.querySelector('.notification-list');
        this.badge = document.querySelector('.notification-btn .badge');
        this.closeBtn = document.querySelector('.notification-close-btn');

        // Initialize empty notifications array
        this.notifications = [];
    }

    loadNotifications() {
        // Try to fetch from server API first. Fallback to localStorage/demo data on error.
        const apiUrl = '../api/fetch_notifications.php?limit=50';
        fetch(apiUrl, { credentials: 'include' })
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(json => {
                if (json && json.success && Array.isArray(json.notifications)) {
                    // Normalize server notification shape to client expectations
                    this.notifications = json.notifications.map(n => ({
                        id: n.id || n.notification_id || Math.floor(Math.random() * 1000000),
                        title: n.title || n.heading || 'Notification',
                        message: n.message || n.content || n.body || '',
                        time: n.created_at ? new Date(n.created_at) : new Date(n.time || Date.now()),
                        isRead: !!n.is_read || !!n.read || false,
                        type: n.type || 'info',
                        // Support multiple possible id field names from different APIs
                        link: (n.id || n.notification_id || n.notificationId || n.notificationID)
                            ? ('pages/company-view-notification.php?id=' + encodeURIComponent(n.id || n.notification_id || n.notificationId || n.notificationID))
                            : (n.link || n.url || 'notifications.php')
                    }));
                    this.saveNotifications();
                    this.updateUI();
                    return;
                }

                // Fallback to localStorage/demo if server returned unexpected payload
                this._loadFromLocalFallback();
            })
            .catch(err => {
                console.debug('Failed to load notifications from server, falling back to local/demo', err);
                this._loadFromLocalFallback();
            });
    }

    _loadFromLocalFallback() {
        const saved = localStorage.getItem('notifications');
        if (saved) {
            this.notifications = JSON.parse(saved).map(n => ({
                ...n,
                time: new Date(n.time) // Convert string back to Date
            }));
        } else {
            // Default sample notifications (kept as fallback)
            this.notifications = [
                {
                    id: 1,
                    title: "New Delivery Assigned",
                    message: "Delivery #1234 has been assigned to you",
                    time: new Date(Date.now() - 3600000), // 1 hour ago
                    isRead: false,
                    type: "info",
                    link: "notifications.php?id=1"
                },
                {
                    id: 2,
                    title: "Delivery Completed",
                    message: "Delivery #1234 has been successfully delivered",
                    time: new Date(Date.now() - 86400000), // 1 day ago
                    isRead: false,
                    type: "success",
                    link: "notifications.php?id=2"
                },
                {
                    id: 3,
                    title: "Urgent: Delivery Issue",
                    message: "There's an issue with delivery #1235",
                    time: new Date(Date.now() - 172800000), // 2 days ago
                    isRead: true,
                    type: "urgent",
                    link: "notifications.php?id=3"
                }
            ];
        }
        this.updateUI();
    }

    saveNotifications() {
        localStorage.setItem('notifications', JSON.stringify(this.notifications));
    }

    setupEventListeners() {
        // When notification button clicked, go to notifications page (open full page)
        this.notificationBtn.addEventListener('click', (e) => {
            e.preventDefault();
            // Navigate to the notifications listing page
            window.location.href = 'notifications.php';
        });

        // Close sidebar when close button or overlay clicked
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', () => this.closeSidebar());
        }

        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.closeSidebar());
        }

        // Mark as read when notification is clicked
        document.addEventListener('click', (e) => {
            const item = e.target.closest('.notification-item');
            if (item) {
                const id = parseInt(item.dataset.id);
                this.markAsRead(id);
            }
        });
    }

    toggleSidebar() {
        this.sidebar.classList.toggle('show');
        this.overlay.classList.toggle('show');

        // Mark all as read when opening
        if (this.sidebar.classList.contains('show')) {
            this.markAllAsRead();
        }
    }

    closeSidebar() {
        this.sidebar.classList.remove('show');
        this.overlay.classList.remove('show');
    }

    formatTime(date) {
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return `${Math.floor(diff / 60000)} min ago`;
        if (diff < 86400000) return `${Math.floor(diff / 3600000)} hours ago`;
        if (diff < 604800000) return `${Math.floor(diff / 86400000)} days ago`;
        return date.toLocaleDateString();
    }

    updateUI() {
        this.renderNotifications();
        this.updateBadge();
    }

    renderNotifications() {
        if (!this.notificationList) return;

        // Clear existing notifications
        this.notificationList.innerHTML = '';

        // Sort by newest first and get first 5
        const recentNotifications = [...this.notifications]
            .sort((a, b) => b.time - a.time)
            .slice(0, 5);

        if (recentNotifications.length === 0) {
            this.notificationList.innerHTML = '<div class="notification-empty">No new notifications</div>';
            return;
        }

        // Add each notification to the list
        recentNotifications.forEach(notification => {
            const item = document.createElement('a');
            item.className = `notification-item ${notification.isRead ? '' : 'unread'} ${notification.type}`;
            item.href = notification.link;
            item.dataset.id = notification.id;
            item.innerHTML = `
                <div class="notification-title">
                    <span>${notification.title}</span>
                    <small class="notification-time">${this.formatTime(notification.time)}</small>
                </div>
                <div class="notification-message">${notification.message}</div>
            `;
            this.notificationList.appendChild(item);
        });
    }

    markAsRead(id) {
        const notification = this.notifications.find(n => n.id === id);
        if (notification && !notification.isRead) {
            notification.isRead = true;
            this.saveNotifications();
            this.updateUI();
        }
    }

    markAllAsRead() {
        let changed = false;

        this.notifications.forEach(notification => {
            if (!notification.isRead) {
                notification.isRead = true;
                changed = true;
            }
        });

        if (changed) {
            this.saveNotifications();
            this.updateUI();
        }
    }

    updateBadge() {
        if (!this.badge) return;

        const unreadCount = this.notifications.filter(n => !n.isRead).length;

        if (unreadCount > 0) {
            this.badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            this.badge.style.display = 'flex';
        } else {
            this.badge.style.display = 'none';
        }
    }

    // DEMO FUNCTION - REMOVE IN PRODUCTION
    startDemo() {
        if (!window.location.pathname.includes('dashboard.php')) return;

        setInterval(() => {
            const types = ['info', 'success', 'warning', 'urgent'];
            const messages = [
                "New delivery request received",
                "Driver has arrived at pickup location",
                "Delivery delayed due to traffic",
                "Payment received for delivery #1234"
            ];

            this.addNotification({
                title: "New Notification",
                message: messages[Math.floor(Math.random() * messages.length)],
                type: types[Math.floor(Math.random() * types.length)],
                link: 'notifications.php'
            });
        }, 30000); // Every 30 seconds
    }

    addNotification(notification) {
        // Generate ID if not provided
        if (!notification.id) {
            notification.id = this.notifications.length > 0
                ? Math.max(...this.notifications.map(n => n.id)) + 1
                : 1;
        }

        // Set defaults
        notification.time = notification.time || new Date();
        notification.isRead = notification.isRead || false;
        notification.type = notification.type || 'info';

        // Add to beginning of array
        this.notifications.unshift(notification);

        // Update UI and save
        this.updateUI();
        this.saveNotifications();

        // Show alert if sidebar is closed
        if (!this.sidebar.classList.contains('show')) {
            this.showNewNotificationAlert(notification);
        }
    }

    showNewNotificationAlert(notification) {
        const alert = document.createElement('div');
        alert.className = 'notification-alert';
        alert.innerHTML = `
            <strong>${notification.title}</strong>
            <p>${notification.message}</p>
        `;

        alert.addEventListener('click', () => {
            this.toggleSidebar();
            alert.remove();
        });

        document.body.appendChild(alert);

        setTimeout(() => {
            alert.classList.add('fade-out');
            setTimeout(() => alert.remove(), 300);
        }, 3000);
    }
}

// Sample notification data
const notifications = [
    {
        type: "delivery",
        title: "Delivery Exception",
        time: "Today, 1:20 PM",
        content: "Delivery TRK901234 could not be completed - address incorrect. Driver Ethan Carter awaiting instructions.",
        action: "company-view-delivery.php",
        unread: true
    },
    {
        type: "driver",
        title: "Driver Offline",
        time: "Today, 12:45 PM",
        content: "Driver Ava Rodriguez has gone offline unexpectedly. Last location: 48.8566° N, 2.3522° E.",
        action: "drivers.php",
        unread: true
    },
    {
        type: "outlet",
        title: "Outlet Activated",
        time: "Yesterday, 5:30 PM",
        content: "Westside Center outlet has been activated and is now available for deliveries.",
        action: "company-view-outlet.php",
        unread: false
    }
];

function initializeNotifications() {
    // Only initialize if we're on a page with notifications
    const container = document.querySelector('.notifications-container');
    if (!container) return; // Skip if not on a notifications page
    const apiUrl = '../api/fetch_notifications.php?limit=100';

    function renderNotificationsList(items) {
        // Clear existing notifications first
        container.innerHTML = '';
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="notification-empty">No notifications</div>';
            return;
        }

        items.forEach(n => {
            // Normalize fields from server or sample shape
            const title = n.title || n.heading || 'Notification';
            const time = n.created_at ? new Date(n.created_at).toLocaleString() : (n.time || '');
            const content = n.message || n.content || n.body || '';
            const action = (n.id || n.notification_id) ? ('company-view-notification.php?id=' + encodeURIComponent(n.id || n.notification_id)) : (n.link || n.url || n.action || 'notifications.php');
            const unread = !!n.is_read || !!n.unread === false ? !!n.unread : !n.is_read;

            const notificationCard = document.createElement('div');
            notificationCard.className = `notification-card ${unread ? 'unread' : ''}`;
            notificationCard.innerHTML = `
                <div class="notification-card-header">
                    <h3 class="notification-card-title">${title}</h3>
                    <span class="notification-card-time">${time}</span>
                </div>
                <div class="notification-card-content">
                    <p>${content}</p>
                </div>
                <div class="notification-card-actions">
                    <a href="${action}">View Details <i class="fas fa-arrow-right"></i></a>
                </div>
            `;
            container.appendChild(notificationCard);
        });
    }

    // Fetch notifications from server; fallback to sample list on error
    fetch(apiUrl, { credentials: 'include' })
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(json => {
            if (json && json.success && Array.isArray(json.notifications)) {
                renderNotificationsList(json.notifications);
                return;
            }
            // Fallback to sample array
            renderNotificationsList(notifications);
        })
        .catch(err => {
            console.debug('Failed to fetch notifications from server, using local sample', err);
            renderNotificationsList(notifications);
        });
}

// ==================== GLOBAL SEARCH ====================
function initGlobalSearch() {
    const input = document.getElementById('globalSearchInput');
    const resultsBox = document.getElementById('globalSearchResults');
    if (!input || !resultsBox) return;

    let debounceTimer = null;
    const minQueryLength = 2;

    // Close results when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-wrapper')) {
            resultsBox.classList.remove('show');
        }
    });

    function escapeHtml(unsafe) {
        if (unsafe == null) return '';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function groupResults(results) {
        const groups = { parcels: [], drivers: [], outlets: [], trips: [], others: [] };
        (results || []).forEach(r => {
            const t = (r.type || r.type_name || '').toLowerCase();
            if (t.includes('parcel') || t === 'parcel') groups.parcels.push(r);
            else if (t.includes('driver')) groups.drivers.push(r);
            else if (t.includes('outlet')) groups.outlets.push(r);
            else if (t.includes('trip')) groups.trips.push(r);
            else groups.others.push(r);
        });
        return groups;
    }

    function renderGroup(title, items, iconClass, itemUrlPrefix) {
        if (!items || items.length === 0) return '';
        return `
            <div class="search-group">
                <div class="search-group-title">${escapeHtml(title)}</div>
                ${items.map(it => `
                    <a href="${escapeHtml(it.link || '#')}" class="search-item">
                        <i class="fas ${iconClass}"></i>
                        <div class="search-item-content">
                            <div class="search-item-title">${escapeHtml(it.title)}</div>
                            <div class="search-item-subtitle">${escapeHtml(it.snippet || '')}</div>
                        </div>
                    </a>
                `).join('')}
            </div>
        `;
    }

    function renderSearchResults(data) {
        const groups = groupResults(data || []);
        if (!groups.parcels.length && !groups.drivers.length && !groups.outlets.length && !groups.trips.length && !groups.others.length) {
            resultsBox.innerHTML = `
                <div class="search-group">
                    <div class="search-item">
                        <div class="search-item-content">
                            <div class="search-item-title">No results found</div>
                        </div>
                    </div>
                </div>`;
            resultsBox.classList.add('show');
            return;
        }

        let html = '';
        html += renderGroup('Parcels', groups.parcels, 'fa-box');
        html += renderGroup('Drivers', groups.drivers, 'fa-user');
        html += renderGroup('Outlets', groups.outlets, 'fa-store');
        html += renderGroup('Trips', groups.trips, 'fa-route');
        if (groups.others.length) html += renderGroup('Other', groups.others, 'fa-ellipsis-h');

        resultsBox.innerHTML = html;
        resultsBox.classList.add('show');
    }

    async function performSearch(query) {
        if (!query || query.trim().length < minQueryLength) { resultsBox.classList.remove('show'); return; }
        try {
            const resp = await fetch('../api/search.php?q=' + encodeURIComponent(query), { credentials: 'include' });
            if (!resp.ok) throw new Error('Search failed');
            const json = await resp.json();
            if (!json.success) throw new Error(json.error || 'Search failed');
            renderSearchResults(json.results || []);
        } catch (err) {
            console.error('Search error:', err);
            resultsBox.innerHTML = `
                <div class="search-group">
                    <div class="search-item">
                        <div class="search-item-content">
                            <div class="search-item-title">Error: ${escapeHtml(err.message)}</div>
                        </div>
                    </div>
                </div>`;
            resultsBox.classList.add('show');
        }
    }

    input.addEventListener('input', (e) => {
        const q = e.target.value.trim();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => performSearch(q), 300);
    });

    // keyboard shortcut to focus search (Ctrl/Cmd+K handled elsewhere) and basic navigation is preserved by anchors
}

// Initialize global search when DOM ready
document.addEventListener('DOMContentLoaded', initGlobalSearch);

// Initialize notifications system
initializeNotifications();




// ==================== DASHBOARD-SPECIFIC CODE ====================

function initDashboardCharts() {
    // Helper to initialize a dashboard chart safely
    function initDashboardChart(id, createChartFn) {
        const canvas = document.getElementById(id);
        if (!canvas) { console.warn('Dashboard chart element not found:', id); return; }
        if (typeof canvas.getContext !== 'function') { console.warn('Canvas does not support getContext:', id); return; }
        try {
            // allow existing instance destruction if present
            const globalName = '__' + id.replace(/[^a-zA-Z0-9_]/g, '') + 'Chart';
            if (window[globalName]) {
                try { window[globalName].destroy(); } catch (e) { /* ignore */ }
                window[globalName] = null;
            }
        } catch (e) { /* ignore cross-origin or readonly errors */ }

        try {
            const ctx = canvas.getContext('2d');
            window['__' + id.replace(/[^a-zA-Z0-9_]/g, '') + 'Chart'] = createChartFn(ctx);
        } catch (err) {
            console.error('Failed to initialize dashboard chart', id, err);
        }
    }

    // Delivery Trends Chart
    initDashboardChart('deliveryTrendChart', (ctx) => new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['1W', '2W', '3W', '4W'],
            datasets: [{
                label: 'Deliveries Over Time',
                data: [10000, 11000, 9500, 12500],
                borderColor: 'rgba(46, 13, 42, 0.8)',
                backgroundColor: 'rgba(46, 13, 42, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: value => value.toLocaleString() } } }
        }
    }));

    // Revenue Trends Chart
    initDashboardChart('revenueTrendChart', (ctx) => new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['1W', '2W', '3W', '4W'],
            datasets: [{
                label: 'Revenue Over Time',
                data: [45000, 48000, 42000, 50000],
                borderColor: 'rgba(76, 175, 80, 0.8)',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: { label: context => `${context.dataset.label}: $${context.parsed.y.toLocaleString()}` }
                }
            },
            scales: {
                x: { grid: { display: false } }, y: { beginAtZero: true }
            }
        }
    }));

}

// ==================== KEYBOARD SHORTCUTS ====================

function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        const isMac = navigator.platform.toUpperCase().includes('MAC');
        const cmdKey = isMac ? e.metaKey : e.ctrlKey;

        // Close sidebar/modal with Esc
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            if (sidebar?.classList.contains('show')) {
                document.getElementById('closeMenu')?.click();
            }
        }

        // Ctrl/Cmd + K → Search
        if (cmdKey && e.key === 'k') {
            e.preventDefault();
            document.querySelector('.search-btn')?.click();
        }

        // Ctrl/Cmd + / → Shortcuts help
        if (cmdKey && e.key === '/') {
            e.preventDefault();
            showMessageBox(`🛠️ Keyboard Shortcuts:\n\n
                ${isMac ? 'Cmd' : 'Ctrl'} + 1: Dashboard\n
                ${isMac ? 'Cmd' : 'Ctrl'} + 2: Outlets\n
                ${isMac ? 'Cmd' : 'Ctrl'} + 3: Drivers\n
                ${isMac ? 'Cmd' : 'Ctrl'} + 4: Deliveries\n
                ${isMac ? 'Cmd' : 'Ctrl'} + 5: Reports\n
                ${isMac ? 'Cmd' : 'Ctrl'} + ,: Settings\n
                ${isMac ? 'Cmd' : 'Ctrl'} + K: Search\n
                Esc: Close menu/modal`);
        }

        // Page navigation (1-5)
        if (cmdKey && e.key >= '1' && e.key <= '5') {
            e.preventDefault();
            const pages = [
                'dashboard.php',
                'outlets.php',
                'drivers.php',
                'deliveries.php',
                'company-reports.php'
            ];
            window.location.href = pages[parseInt(e.key) - 1];
        }

        // Ctrl/Cmd + , → Settings
        if (cmdKey && e.key === ',') {
            e.preventDefault();
            window.location.href = 'settings.php';
        }

        // Alt + N → Notifications
        if (e.altKey && e.key.toLowerCase() === 'n') {
            e.preventDefault();
            document.querySelector('.notification-btn')?.click();
        }

        // Sidebar: Ctrl/Cmd + M
        if (cmdKey && e.key.toLowerCase() === 'm') {
            e.preventDefault();
            const menuBtn = document.getElementById('menuBtn');
            if (menuBtn) menuBtn.click();
        }
    });

    // Back navigation shortcuts
    document.addEventListener('keydown', (e) => {
        const isMac = navigator.platform.toUpperCase().includes('MAC');
        if ((!isMac && e.altKey && e.key === 'ArrowLeft') || (isMac && e.metaKey && e.key === '[')) {
            e.preventDefault();
            window.history.back();
        }
    });
}

document.addEventListener('keydown', function (event) {
    // Check for Ctrl+Alt+N
    if (event.ctrlKey && event.altKey && event.key.toLowerCase() === 'n') {
        event.preventDefault();
        // Open notification.php in the same tab
        window.location.href = 'notifications.php';
    }
});

// ==================== MAIN INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', () => {
    // Initialize common systems
    initMenuSystem();
    initKeyboardShortcuts();

    // Note: NotificationSystem is defined in notifications.js for the dedicated notifications page
    // Skipping instantiation here to avoid duplicate declaration conflicts
    // if (document.querySelector('.notification-btn')) {
    //     new NotificationSystem();
    // }

    // Initialize page-specific functionality
    const path = window.location.pathname;
    if (path.includes('dashboard.php')) {
        initDashboardCharts();
    }
    // Add other page-specific initializers as needed
});