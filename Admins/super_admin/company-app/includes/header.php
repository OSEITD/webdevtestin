<?php
// Require centralized init to configure error reporting, session and output buffering
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../../includes/csrf-helper.php';

// Determine company currency for frontend usage. Prefer session value, otherwise try to fetch company record.
$appCurrency = $_SESSION['company_currency'] ?? null;
if (empty($appCurrency) && isset($_SESSION['id'])) {
    // Try to fetch company currency using the Supabase client if available
    try {
        require_once __DIR__ . '/../api/supabase-client.php';
        $client = new SupabaseClient();
        $companyId = $_SESSION['id'];
        // Use access token if available to respect RLS, otherwise fall back to anon key
        $accessToken = $_SESSION['access_token'] ?? null;
        $company = $accessToken ? $client->getCompany($companyId, $accessToken) : $client->getRecord("companies?id=eq.{$companyId}");
        // Normalise company response shapes
        if (is_array($company) && isset($company[0]['currency'])) {
            $appCurrency = $company[0]['currency'];
        } elseif (is_object($company) && isset($company->data) && is_array($company->data) && isset($company->data[0]['currency'])) {
            $appCurrency = $company->data[0]['currency'];
        }
        if (!empty($appCurrency)) {
            $_SESSION['company_currency'] = $appCurrency;
        }
    } catch (Exception $e) {
        // ignore and fall back to a default later
    }
}

// Small helper to map common currency codes to symbols
function currency_symbol_for_code($code) {
    $map = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'ZMW' => 'ZK', 'JPY' => '¥'
    ];
    return $map[strtoupper($code) ?? ''] ?? '';
}
$appCurrency = $appCurrency ?? 'USD';
$appCurrencySymbol = currency_symbol_for_code($appCurrency);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?php echo CSRFHelper::getToken(); ?>">
    <title>Company - <?php echo ucfirst(basename($_SERVER['PHP_SELF'], '.php')); ?></title>
    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap CSS (utilities & grid) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 for popups -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- External CSS file -->
    <link rel="stylesheet" href="../assets/css/company.css?v=<?php echo time(); ?>">
    <!-- Search & Notifications CSS -->
    <link rel="stylesheet" href="../assets/css/company-search-notifications.css">
    <!-- Form Validation Styles (shared with super_admin) -->
    <link rel="stylesheet" href="../../assets/css/form-validation.css">
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="shortcut icon" href="/favicon.png" type="image/png">
    <!-- PWA Manifest - Pointing to company-app folder -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#2e0b3f">

    <!-- Supabase JS -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    
    <?php
    require_once __DIR__ . '/../api/supabase-client.php';
    $sbClient = new SupabaseClient();
    $supabaseUrl = $sbClient->getUrl();
    $supabaseKey = $sbClient->getKey();
    ?>
    <script>
        // Initialize Supabase Client Globally
        window.SUPABASE_URL = '<?php echo $supabaseUrl; ?>';
        window.SUPABASE_ANON_KEY = '<?php echo $supabaseKey; ?>';
        if (!window.supabaseClient) {
            window.supabaseClient = window.supabase.createClient(window.SUPABASE_URL, window.SUPABASE_ANON_KEY);
        }
        // Expose current user details for presence tracking
        window.CURRENT_USER_ID = '<?php echo $_SESSION['id'] ?? ''; ?>';
        window.CURRENT_USER_FULLNAME = '<?php echo addslashes($_SESSION['full_name'] ?? ''); ?>';
        window.CURRENT_USER_ROLE = 'company';
    </script>
    
    <script src="../../assets/js/presence.js" defer></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <script>
        // Global app config injected from server-side session/company settings
        window.APP_CONFIG = window.APP_CONFIG || {};
        window.APP_CONFIG.currency = '<?php echo htmlspecialchars($appCurrency ?? '', ENT_QUOTES); ?>';
        window.APP_CONFIG.currency_symbol = '<?php echo htmlspecialchars($appCurrencySymbol ?? '', ENT_QUOTES); ?>';

        // Register Service Worker for PWA - Pointing to super_admin root
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('../../service-worker.js')
                    .then(registration => {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
    </script>

    <!-- Inline responsive helpers for header -->
    <style>
    .desktop-only-inline { display: inline-block; }
    @media (max-width: 768px) {
        .desktop-only-inline { display: none; }
        .search-container { flex: 1; max-width: none; margin: 0 10px; }
        .search-input-wrapper input { font-size: 14px; padding: 10px 15px 10px 40px; }
    }
    /* Menu overlay style match */
    .menu-overlay.show { display: block; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 10999; }
    </style>

    <div class="mobile-dashboard">
        <!-- Top Header Bar -->
        <header class="top-header">
            <div class="header-content">
                <img src="../assets/images/logo.png" alt="WebDev" class="app-logo" onerror="this.onerror=null;this.src='https://placehold.co/100x40?text=Logo+Not+Found';">

                <!-- Search Bar (visible on all devices) -->
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="globalSearchInput" placeholder="Search..." autocomplete="off">
                        <span class="search-shortcut desktop-only-inline">Ctrl+K</span>
                    </div>
                    <div id="searchResults" class="search-results"></div>
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

        <!-- Overlays -->
        <div id="searchOverlay" class="search-overlay"></div>
        <div id="menuOverlay" class="menu-overlay" aria-hidden="true"></div>

        <!-- Search & Notifications JS -->
        <script src="../assets/js/company-search-notifications.js"></script>

        <!-- Include Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
