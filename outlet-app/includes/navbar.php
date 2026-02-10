<header class="top-header">
    <div class="header-content">
        <img src="../img/logo.png" alt="Delivery Pro" class="app-logo">

        <!-- Search Bar (visible on all devices) -->
        <div class="search-container" id="searchContainer">
            <div class="search-input-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="globalSearchInput" placeholder="Search..." autocomplete="off">
                <span class="search-shortcut desktop-only-inline">Ctrl+K</span>
            </div>
            <!-- Results moved outside header to avoid stacking context issues -->
        </div>

        <div class="header-icons">
            <!-- Notifications -->
            <div class="notification-container">
                <button class="notification-bell" id="notificationBell">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <div id="notificationPanel" class="notification-panel">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading notifications...</p>
                        </div>
                    </div>
                    <div class="notification-footer">
                        <button class="btn-link" id="markAllRead">
                            <i class="fas fa-check-double"></i> Mark all as read
                        </button>
                        <a href="../pages/notifications.php" class="btn-link">
                            View all <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Menu Toggle -->
            <button class="icon-btn menu-btn" id="menuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

</header>

<!-- Search Results Portal - positioned at body level to avoid z-index stacking issues -->
<div id="searchResults" class="search-results"></div>

<!-- Search Overlay -->
<div id="searchOverlay" class="search-overlay"></div>

<!-- CSS -->
<link rel="stylesheet" href="../assets/css/search-notifications.css">

<!-- JavaScript -->
<script src="../assets/js/search-notifications.js"></script>

<style>

.desktop-only-inline { 
    display: inline-block;
}

@media (max-width: 768px) {
    .desktop-only-inline { 
        display: none;
    }
    
    
    .search-container {
        flex: 1;
        max-width: none;
        margin: 0 10px;
    }
    
    .search-input-wrapper input {
        font-size: 14px;
        padding: 10px 15px 10px 40px;
    }
}

.menu-overlay.show {
    display: block;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}
</style>

<script>

function toggleSidebar() {
    console.log('🎯 Sidebar toggle clicked!');
    const sidebar = document.getElementById('sidebar');
    const menuOverlay = document.getElementById('menuOverlay');

    if (sidebar && menuOverlay) {
        const isOpen = sidebar.classList.contains('show');

        if (isOpen) {
            sidebar.classList.remove('show');
            menuOverlay.classList.remove('show');
            console.log('📤 Sidebar closed');
        } else {
            sidebar.classList.add('show');
            menuOverlay.classList.add('show');
            console.log('📥 Sidebar opened');
        }
    } else {
        console.error('❌ Sidebar elements not found');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const menuOverlay = document.getElementById('menuOverlay');
    const closeMenu = document.getElementById('closeMenu');
    const mobileSearchToggle = document.getElementById('mobileSearchToggle');
    const mobileSearchBar = document.getElementById('mobileSearchBar');
    const mobileSearchClose = document.getElementById('mobileSearchClose');

    
    if (menuOverlay) {
        menuOverlay.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.remove('show');
                menuOverlay.classList.remove('show');
            }
        });
    }

    
    if (closeMenu) {
        closeMenu.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const menuOverlay = document.getElementById('menuOverlay');
            if (sidebar) {
                sidebar.classList.remove('show');
            }
            if (menuOverlay) {
                menuOverlay.classList.remove('show');
            }
        });
    }

    
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            if (window.notificationManager) {
                notificationManager.markAllAsRead();
            }
        });
    }

    // Close search results when clicking the dim overlay
    const searchOverlayEl = document.getElementById('searchOverlay');
    if (searchOverlayEl) {
        searchOverlayEl.addEventListener('click', function() {
            if (window.globalSearch && typeof window.globalSearch.hideResults === 'function') {
                window.globalSearch.hideResults();
            }
        });
    }
});
</script>
