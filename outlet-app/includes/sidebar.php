<div class="sidebar" id="sidebar">
    <div class="menu-header">
        <div class="user-profile">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <h3>Outlet Manager: <span id="managerName">Loading...</span></h3>
                <p>Online <span class="online-dot"></span></p>
            </div>
        </div>
        <button class="close-menu" id="closeMenu">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Menu Items -->
    <ul class="menu-items">
        <li><a href="outlet_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'outlet_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        
        <!-- Trip Management Section -->
        <li class="menu-section-header"><i class="fas fa-truck"></i> Trip Management</li>
        <li><a href="trip_wizard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'trip_wizard.php' ? 'active' : ''; ?>"><i class="fas fa-route"></i> Create Trip</a></li>
        <li><a href="trips.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'trips.php' ? 'active' : ''; ?>"><i class="fas fa-truck-loading"></i> All Trips</a></li>
        <li><a href="manager_trips.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manager_trips.php' ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> My Active Trips</a></li>
        
        <!-- Parcel Management Section -->
        <li class="menu-section-header"><i class="fas fa-box"></i> Parcel Management</li>
        <li><a href="parcel_registration.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'parcel_registration.php' ? 'active' : ''; ?>"><i class="fas fa-plus-circle"></i> Register Parcel</a></li>

        <li><a href="parcel_management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'parcel_management.php' ? 'active' : ''; ?>"><i class="fas fa-qrcode"></i> Scanner</a></li>
        <li><a href="parcelpool.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'parcelpool.php' ? 'active' : ''; ?>"><i class="fas fa-swimming-pool"></i> Parcel Pool</a></li>
        <li><a href="notifications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>"><i class="fas fa-bell"></i> Notifications</a></li>
        <li><a href="outlet_settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'outlet_settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Outlet Settings</a></li>
        <li><a href="help.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'help.php' ? 'active' : ''; ?>"><i class="fas fa-question-circle"></i> Help</a></li>
        <li><a href="../logout.php" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="menu-overlay" id="menuOverlay"></div>

<script>
    async function fetchManagerName() {
        try {
            const response = await fetch('../api/outlets/fetch_outlet_manager.php');
            const data = await response.json();
            if (!data.error) {
                document.getElementById('managerName').textContent = data.name;
            } else {
                console.error("Error fetching manager name:", data.error);
                document.getElementById('managerName').textContent = 'Unknown';
            }
        } catch (error) {
            console.error('Error fetching manager name:', error);
            document.getElementById('managerName').textContent = 'Unknown';
        }
    }

    function confirmLogout() {
        return confirm('Are you sure you want to logout?');
    }

    
    document.addEventListener('DOMContentLoaded', fetchManagerName);
</script>
