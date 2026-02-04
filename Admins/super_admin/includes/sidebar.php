<?php
// Sidebar menu HTML to be included in pages
// Ensure session is available for user/admin details
if (session_status() === PHP_SESSION_NONE) session_start();

// Fetch user info from session
$userName = null;
$userRole = null;
$avatarUrl = null;
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$accessToken = $_SESSION['access_token'] ?? null;

if ($userId) {
    try {
        require_once __DIR__ . '/../api/supabase-client.php';
        
        // Get Supabase credentials
        global $supabaseUrl, $supabaseKey;
        $client = new SupabaseClient($supabaseUrl, $supabaseKey);

        // Fetch user details from the users table using Supabase
        $userResp = $client->get("users", "id=eq.{$userId}&select=*");

        // Normalize response to a single row array
        $userRow = null;
        if (is_array($userResp) && count($userResp) > 0) {
            $userRow = $userResp[0];
        } elseif (is_object($userResp) && isset($userResp->data) && is_array($userResp->data) && count($userResp->data) > 0) {
            $userRow = $userResp->data[0];
        }

        if (is_array($userRow)) {
            // Common column name fallbacks for user name and role
            $userName = $userRow['fullname'] ?? $userRow['name'] ?? $userRow['user_fullname'] ?? $userRow['display_name'] ?? null;
            $userRole = $userRow['role'] ?? $_SESSION['role'] ?? null;
            $avatarUrl = $userRow['avatar'] ?? $userRow['avatar_url'] ?? $userRow['profile_picture'] ?? null;

            // Cache into session for faster subsequent loads
            if (!empty($userName)) $_SESSION['user_fullname'] = $userName;
            if (!empty($userRole)) $_SESSION['role'] = $userRole;
            if (!empty($avatarUrl)) $_SESSION['avatar'] = $avatarUrl;
        }
    } catch (Exception $e) {
        error_log('Sidebar: failed to fetch user info: ' . $e->getMessage());
    }
}

// Final fallbacks to session or defaults
$userName = $userName ?? $_SESSION['user_fullname'] ?? $_SESSION['name'] ?? 'Admin';
$userRole = $userRole ?? $_SESSION['role'] ?? 'super_admin';
$avatarUrl = $avatarUrl ?? $_SESSION['avatar'] ?? 'https://placehold.co/56x56/FF6B6B/ffffff?text=' . urlencode(substr($userName, 0, 1));
?>
<div class="sidebar" id="sidebar">
    <div class="menu-header">
        <div class="user-profile">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($userName, ENT_QUOTES); ?> Avatar" style="width:56px;height:56px;border-radius:50%;object-fit:cover;" />
            </div>
            <div>
                <h3 style="margin:0;font-size:2.05rem;line-height:1;"><?php echo htmlspecialchars($userName, ENT_QUOTES); ?></h3>
                <p style="margin:0;font-size:1.00rem;opacity:0.85;text-transform:capitalize;"><?php echo htmlspecialchars($userRole, ENT_QUOTES); ?> <span class="online-dot"></span></p>
            </div>
        </div>
        <button class="close-menu" id="closeMenu">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Menu Items -->
    <ul class="menu-items">
        <li><a href="./dashboard.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'dashboard.php') echo 'active'; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="./companies.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'companies.php') echo 'active'; ?>"><i class="fas fa-building"></i> Companies</a></li>
        <li><a href="./outlets.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'outlets.php') echo 'active'; ?>"><i class="fas fa-store"></i> Outlets</a></li>
        <li><a href="./users.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'users.php') echo 'active'; ?>"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="./reports.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'reports.php') echo 'active'; ?>"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="./settings.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'settings.php') echo 'active'; ?>"><i class="fas fa-cog"></i> Settings</a></li>
        <li><a href="./help.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'help.php') echo 'active'; ?>"><i class="fas fa-question-circle"></i> Help</a></li>
        <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>