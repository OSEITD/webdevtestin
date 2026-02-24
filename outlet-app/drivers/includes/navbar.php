<?php

$driverName = $_SESSION['full_name'] ?? 'Driver';
$notificationCount = 0; 
?>

<!-- Include External Navbar Styles -->
<link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? '../assets/css/navbar.css' : 'assets/css/navbar.css'; ?>">

<!-- Custom Top Bar -->
<header class="custom-topbar">
    <div class="topbar-content">
        <div class="topbar-left">
            <img src="<?php echo (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? '../img/logo.png' : 'img/logo.png'; ?>" 
                 alt="WEBDEV technologies" class="topbar-logo">
        </div>
        <div class="topbar-center">
            <form class="topbar-search" onsubmit="handleSearch(event);">
                <input type="text" id="globalSearch" placeholder="Search parcels, customers, notifications..." />
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="topbar-right">
            <div class="topbar-bell" onclick="showNotifications()">
                <i class="fas fa-bell"></i>
                <span class="topbar-badge" id="notificationBadge"><?php echo $notificationCount; ?></span>
            </div>
            <button class="topbar-menu" onclick="toggleDriverMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<!-- Driver Menu Dropdown (Hidden by default) -->
<div class="driver-menu-dropdown" id="driverMenuDropdown">
    <div class="driver-menu-header">
        <div class="driver-avatar-small">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="driver-menu-info">
            <h4><?php echo htmlspecialchars($driverName); ?></h4>
            <p>Driver ID: <?php echo substr($_SESSION['user_id'], 0, 8); ?></p>
        </div>
    </div>
    <div class="driver-menu-items">
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? '../dashboard.php' : 'dashboard.php'; ?>" class="menu-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? '../profile.php' : 'pages/profile.php'; ?>" class="menu-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? 'live-tracking.php' : 'pages/live-tracking.php'; ?>" class="menu-item">
            <i class="fas fa-route"></i>
            <span>Live Route</span>
        </a>
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? 'delivery-history.php' : 'pages/delivery-history.php'; ?>" class="menu-item">
            <i class="fas fa-history"></i>
            <span>Delivery History</span>
        </a>
        <div class="menu-divider"></div>
        <?php
            // compute logout path relative to current script location
            $base = dirname($_SERVER['SCRIPT_NAME']);
            if ($base === '/' || $base === '.') {
                $base = '';
            }
            $logoutUrl = $base . '/logout.php';
        ?>
        <a href="<?php echo $logoutUrl; ?>" class="menu-item logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Menu Overlay -->
<div class="menu-overlay" id="menuOverlay" onclick="closeDriverMenu()"></div>

<!-- Include External Navbar JavaScript -->
<script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? '../assets/js/navbar.js' : 'assets/js/navbar.js'; ?>"></script>
<script>

document.addEventListener('DOMContentLoaded', function() {
    updateNotificationBadge(<?php echo $notificationCount; ?>);
});
</script>