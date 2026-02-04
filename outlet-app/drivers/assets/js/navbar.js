// Global functions for navbar functionality
function handleSearch(event) {
    event.preventDefault();
    const searchTerm = document.getElementById('globalSearch').value.trim();
    if (searchTerm) {
        // TODO: Implement search functionality
        alert('Search functionality will be implemented soon!\nSearching for: ' + searchTerm);
    }
}

function showNotifications() {
    // TODO: Implement notifications panel
    alert('Notifications panel will be implemented soon!');
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
    
    if (dropdown && !dropdown.contains(event.target) && !menuButton.contains(event.target)) {
        closeDriverMenu();
    }
});

// Initialize notification badge
document.addEventListener('DOMContentLoaded', function() {
    // TODO: Fetch actual notification count from API
    // updateNotificationBadge will be called with the notification count from PHP
});