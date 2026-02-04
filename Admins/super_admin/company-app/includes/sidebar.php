<?php
// Sidebar menu HTML to be included in pages
// Ensure session is available for company/user details
if (session_status() === PHP_SESSION_NONE) session_start();

// Try to fetch authoritative company info from the companies table using Supabase
$companyName = null;
$contactName = null;
$avatarUrl = null;
$companyId = $_SESSION['company_id'] ?? $_SESSION['id'] ?? null;
$accessToken = $_SESSION['access_token'] ?? null;
if ($companyId) {
    try {
        require_once __DIR__ . '/../api/supabase-client.php';
        $client = new SupabaseClient();

        // Prefer using the user's session token so RLS applies; fall back to a direct record read
        $companyResp = $accessToken ? $client->getCompany($companyId, $accessToken) : $client->getRecord("companies?id=eq.{$companyId}");

        // Normalize response to a single row array
        $companyRow = null;
        if (is_array($companyResp) && count($companyResp) > 0) {
            $companyRow = $companyResp[0];
        } elseif (is_object($companyResp) && isset($companyResp->data) && is_array($companyResp->data) && count($companyResp->data) > 0) {
            $companyRow = $companyResp->data[0];
        }

        if (is_array($companyRow)) {
            // Common column name fallbacks for company name and contact person
            $companyName = $companyRow['name'] ?? $companyRow['company_name'] ?? $companyRow['display_name'] ?? $companyRow['company'] ?? null;
            $contactName = $companyRow['contact_name'] ?? $companyRow['contact_person'] ?? $companyRow['primary_contact'] ?? $companyRow['owner_name'] ?? $companyRow['manager_name'] ?? null;
            $avatarUrl = $companyRow['logo'] ?? $companyRow['logo_url'] ?? $companyRow['company_logo'] ?? null;

            // Cache into session for faster subsequent loads
            if (!empty($companyName)) $_SESSION['company_name'] = $companyName;
            if (!empty($contactName)) $_SESSION['contact_name'] = $contactName;
            if (!empty($avatarUrl)) $_SESSION['company_logo'] = $avatarUrl;
        }
    } catch (Exception $e) {
        error_log('Sidebar: failed to fetch company info: ' . $e->getMessage());
    }
}

// Final fallbacks to session or defaults
$companyName = $companyName ?? $_SESSION['company_name'] ?? $_SESSION['company'] ?? ($_SESSION['company_name_display'] ?? 'Company');
$contactName = $contactName ?? $_SESSION['contact_name'] ?? $_SESSION['user_fullname'] ?? $_SESSION['name'] ?? 'Company Manager';
$avatarUrl = $avatarUrl ?? $_SESSION['company_logo'] ?? $_SESSION['avatar'] ?? 'https://placehold.co/56x56/FF6B6B/ffffff?text=' . urlencode(substr($companyName, 0, 1));
?>
<div class="sidebar" id="sidebar">
    <div class="menu-header">
        <div class="user-profile">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($companyName, ENT_QUOTES); ?> Logo" style="width:56px;height:56px;border-radius:50%;object-fit:cover;" />
            </div>
            <div>
                <h3 style="margin:0;font-size:2.05rem;line-height:1;"><?php echo htmlspecialchars($companyName, ENT_QUOTES); ?></h3>
                <p style="margin:0;font-size:1.00rem;opacity:0.85;"><?php echo htmlspecialchars($contactName, ENT_QUOTES); ?> <span class="online-dot"></span></p>
            </div>
        </div>
        <button class="close-menu" id="closeMenu">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Menu Items -->
    <ul class="menu-items">
        <li><a href="./dashboard.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'dashboard.php') echo 'active'; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="./outlets.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'outlets.php') echo 'active'; ?>"><i class="fas fa-store"></i> Outlets</a></li>
        <li><a href="./drivers.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'drivers.php') echo 'active'; ?>"><i class="fas fa-truck"></i> Drivers</a></li>
        <li><a href="./company-vehicles.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'company-vehicles.php') echo 'active'; ?>"><i class="fas fa-car"></i> Vehicles</a></li>
        <li><a href="./create-trip.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'create-trip.php') echo 'active'; ?>"><i class="fas fa-route"></i> Create Trip</a></li>
        <li><a href="./trips.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'trips.php') echo 'active'; ?>"><i class="fas fa-map-marked-alt"></i> Trips</a></li>
        <li><a href="./deliveries.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'deliveries.php') echo 'active'; ?>"><i class="fas fa-box-open"></i> Deliveries</a></li>
        <li><a href="./company-reports.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'company-reports.php') echo 'active'; ?>"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="./settings.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'settings.php') echo 'active'; ?>"><i class="fas fa-cog"></i> Settings</a></li>
        <li><a href="./company-help.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'company-help.php') echo 'active'; ?>"><i class="fas fa-question-circle"></i> Help</a></li>
    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>
