// Global functions for navbar functionality
const driverSearchState = {
    lastQuery: '',
    debounceId: null,
    controller: null
};

function handleSearch(event) {
    event.preventDefault();
    const searchTerm = getSearchInputValue();
    if (searchTerm.length < 2) {
        hideSearchResults();
        return;
    }
    performDriverSearch(searchTerm);
}

function initDriverSearch() {
    const input = document.getElementById('globalSearch');
    if (!input) {
        return;
    }

    input.addEventListener('input', () => {
        const query = getSearchInputValue();
        if (query.length < 2) {
            hideSearchResults();
            return;
        }

        window.clearTimeout(driverSearchState.debounceId);
        driverSearchState.debounceId = window.setTimeout(() => {
            performDriverSearch(query);
        }, 300);
    });

    input.addEventListener('focus', () => {
        const query = getSearchInputValue();
        if (query.length >= 2) {
            performDriverSearch(query);
        }
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideSearchResults();
            input.blur();
        }
    });

    document.addEventListener('click', (event) => {
        const container = getSearchResultsContainer(false);
        const wrapper = document.querySelector('.topbar-center');
        if (!container || !wrapper) {
            return;
        }
        if (!wrapper.contains(event.target)) {
            hideSearchResults();
        }
    });
}

function getSearchInputValue() {
    const input = document.getElementById('globalSearch');
    return input ? input.value.trim() : '';
}

async function performDriverSearch(query) {
    if (!query || query.length < 2) {
        hideSearchResults();
        return;
    }

    if (query === driverSearchState.lastQuery) {
        showSearchResults();
        return;
    }

    driverSearchState.lastQuery = query;
    setSearchResultsLoading(query);

    if (driverSearchState.controller) {
        driverSearchState.controller.abort();
    }
    driverSearchState.controller = new AbortController();

    try {
        const apiUrl = buildDriverUrl(`api/search.php?q=${encodeURIComponent(query)}`);
        const response = await fetch(apiUrl, {
            credentials: 'same-origin',
            signal: driverSearchState.controller.signal
        });

        if (!response.ok) {
            throw new Error('Search request failed');
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Search failed');
        }

        renderSearchResults(query, data.results || {});
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        renderSearchError(query);
    }
}

function setSearchResultsLoading(query) {
    const container = getSearchResultsContainer(true);
    if (!container) {
        return;
    }
    container.innerHTML = `
        <div class="driver-search-loading">
            <span>Searching for "${escapeHtml(query)}"...</span>
        </div>
    `;
    showSearchResults();
}

function renderSearchError(query) {
    const container = getSearchResultsContainer(true);
    if (!container) {
        return;
    }
    container.innerHTML = `
        <div class="driver-search-empty">
            Unable to load results for "${escapeHtml(query)}".
        </div>
    `;
    showSearchResults();
}

function renderSearchResults(query, results) {
    const container = getSearchResultsContainer(true);
    if (!container) {
        return;
    }

    const groups = [
        { key: 'trips', label: 'Trips' },
        { key: 'parcels', label: 'Parcels' },
        { key: 'notifications', label: 'Notifications' }
    ];

    let hasResults = false;
    let html = `<div class="driver-search-header">Results for "${escapeHtml(query)}"</div>`;

    groups.forEach((group) => {
        const items = Array.isArray(results[group.key]) ? results[group.key] : [];
        if (!items.length) {
            return;
        }
        hasResults = true;
        html += `<div class="driver-search-group">`;
        html += `<div class="driver-search-group-title">${group.label}</div>`;
        html += items.map((item) => renderSearchItem(item)).join('');
        html += `</div>`;
    });

    if (!hasResults) {
        html += `<div class="driver-search-empty">No matches found for "${escapeHtml(query)}".</div>`;
    }

    container.innerHTML = html;
    showSearchResults();
}

function renderSearchItem(item) {
    const icon = item.icon || getSearchIcon(item.type);
    const title = escapeHtml(item.title || 'Result');
    const subtitle = escapeHtml(item.subtitle || '');
    const meta = escapeHtml(formatResultMeta(item));
    const url = item.url ? buildDriverUrl(item.url) : '#';
    const metaHtml = meta ? `<span class="driver-search-meta">${meta}</span>` : '';
    const subtitleHtml = subtitle ? `<span class="driver-search-subtitle">${subtitle}</span>` : '';

    return `
        <a class="driver-search-item" href="${url}">
            <span class="driver-search-icon"><i class="fas ${icon}"></i></span>
            <span class="driver-search-text">
                <span class="driver-search-title">${title}</span>
                ${subtitleHtml}
            </span>
            ${metaHtml}
        </a>
    `;
}

function formatResultMeta(item) {
    if (item.status) {
        return formatStatusLabel(item.status);
    }
    if (item.date) {
        const parsed = new Date(item.date);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed.toLocaleDateString();
        }
    }
    return '';
}

function formatStatusLabel(status) {
    if (!status) {
        return '';
    }
    return status.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function getSearchIcon(type) {
    switch (type) {
        case 'parcel':
            return 'fa-box';
        case 'trip':
            return 'fa-route';
        case 'notification':
            return 'fa-bell';
        default:
            return 'fa-magnifying-glass';
    }
}

function getSearchResultsContainer(createIfMissing) {
    const existing = document.getElementById('driverSearchResults');
    if (existing || !createIfMissing) {
        return existing;
    }

    const wrapper = document.querySelector('.topbar-center');
    if (!wrapper) {
        return null;
    }

    const container = document.createElement('div');
    container.id = 'driverSearchResults';
    container.className = 'driver-search-results';
    wrapper.appendChild(container);
    return container;
}

function showSearchResults() {
    const container = getSearchResultsContainer(false);
    if (container) {
        container.classList.add('show');
    }
}

function hideSearchResults() {
    const container = getSearchResultsContainer(false);
    if (container) {
        container.classList.remove('show');
    }
}

function buildDriverUrl(path) {
    if (!path) {
        return '#';
    }
    if (/^https?:\/\//i.test(path)) {
        return path;
    }
    const rootPath = getDriverRootPath();
    return new URL(path, `${window.location.origin}${rootPath}`).toString();
}

function getDriverRootPath() {
    const pathname = window.location.pathname || '';
    const marker = '/drivers/';
    const index = pathname.indexOf(marker);
    if (index === -1) {
        return '/drivers/';
    }
    return pathname.slice(0, index + marker.length);
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function showNotifications() {
    const panel = document.getElementById('driverNotificationPanel');
    if (!panel) {
        console.warn('Driver notification panel not found in DOM.');
        return;
    }
    fetchDriverNotifications();
    panel.classList.toggle('show');
}

function toggleDriverMenu() {
    const dropdown = document.getElementById('driverMenuDropdown');
    const overlay = document.getElementById('menuOverlay');
    
    if (dropdown.classList.contains('active')) {
        closeDriverMenu();
    } else {
        dropdown.classList.add('active');
        overlay.classList.add('active');
    }
}

function closeDriverMenu() {
    const dropdown = document.getElementById('driverMenuDropdown');
    const overlay = document.getElementById('menuOverlay');
    
    dropdown.classList.remove('active');
    overlay.classList.remove('active');
}

// Update notification badge
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        badge.textContent = count;
        if (count > 0) {
            badge.classList.add('show');
        } else {
            badge.classList.remove('show');
        }
    }
}

// Close menu when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('driverMenuDropdown');
    const menuButton = document.querySelector('.topbar-menu');
    const bellButton = document.querySelector('.topbar-bell');
    const panel = document.getElementById('driverNotificationPanel');
    
    if (dropdown && !dropdown.contains(event.target) && !menuButton.contains(event.target)) {
        closeDriverMenu();
    }

    if (panel && bellButton && !panel.contains(event.target) && !bellButton.contains(event.target)) {
        panel.classList.remove('show');
    }
});

// Initialize notification badge
async function fetchDriverNotifications() {
    const apiUrl = buildDriverUrl('api/notifications.php?limit=5');
    try {
        const response = await fetch(apiUrl, {
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Failed to fetch notifications');
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to load notifications');
        }

        const notifications = Array.isArray(data.notifications) ? data.notifications : [];
        const unreadCount = typeof data.unread_count === 'number' ? data.unread_count : 0;

        updateNotificationBadge(unreadCount);
        renderDriverNotificationList(notifications);
    } catch (error) {
        console.warn('Driver notifications failed to load:', error);
    }
}

function renderDriverNotificationList(notifications) {
    const list = document.getElementById('driverNotificationList');
    if (!list) {
        return;
    }

    if (!notifications.length) {
        list.innerHTML = `
            <div class="notification-item notification-empty">
                <div class="notification-icon"><i class="fas fa-bell-slash"></i></div>
                <div class="notification-content">
                    <p class="notification-title">No notifications</p>
                    <p class="notification-text">You don't have any notifications right now.</p>
                </div>
            </div>
        `;
        return;
    }

    list.innerHTML = notifications.map((notification) => {
        const title = notification.title || 'Notification';
        const message = notification.message || '';
        const time = notification.created_at ? new Date(notification.created_at).toLocaleString() : '';
        const unread = notification.status === 'unread';
        return `
            <div class="notification-item${unread ? ' unread' : ''}">
                <div class="notification-icon"><i class="fas fa-bell${unread ? '' : '-slash'}"></i></div>
                <div class="notification-content">
                    <p class="notification-title">${escapeHtml(title)}</p>
                    <p class="notification-text">${escapeHtml(message)}</p>
                    <span class="notification-time">${escapeHtml(time)}</span>
                </div>
            </div>
        `;
    }).join('');
}

document.addEventListener('DOMContentLoaded', function() {
    initDriverSearch();
    fetchDriverNotifications();
});