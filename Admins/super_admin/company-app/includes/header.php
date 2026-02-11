<?php
// Require centralized init to configure error reporting, session and output buffering
require_once __DIR__ . '/init.php';

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
    <title>Company - <?php echo ucfirst(basename($_SERVER['PHP_SELF'], '.php')); ?></title>
    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- External CSS file -->
    <link rel="stylesheet" href="../assets/css/company.css">
    <!-- PWA Manifest - Pointing to super_admin root -->
    <link rel="manifest" href="../../manifest.json">
</head>
<body class="bg-gray-100 min-h-screen">
    <script>
        // Global app config injected from server-side session/company settings
        window.APP_CONFIG = window.APP_CONFIG || {};
        window.APP_CONFIG.currency = '<?php echo htmlspecialchars($appCurrency, ENT_QUOTES); ?>';
        window.APP_CONFIG.currency_symbol = '<?php echo htmlspecialchars($appCurrencySymbol, ENT_QUOTES); ?>';

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
    <div class="mobile-dashboard">
        <!-- Top Header Bar -->
        <header class="top-header">
            <div class="header-content">
<img src="../assets/images/Logo.png" alt="WebDev" class="app-logo">
                <div class="header-search">
                    <div class="search-container">
                        <input id="globalSearchInput" type="text" placeholder="Search companies, parcels, users..." aria-label="Search">
                        <button type="button" id="searchBtn" class="icon-btn search-btn"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="search-results" id="globalSearchResults"></div>
                </div>

                <div class="header-icons">
                    <!-- Link to the full notifications page (no sidebar/overlay) -->
                    <a href="../pages/notifications.php" class="icon-btn notification-link" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge">0</span>
                    </a>
                    <button class="icon-btn menu-btn" id="menuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- The notification sidebar/overlay were removed: notifications open on their dedicated page -->
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Menu overlay (used by toggleMenu and close handlers) -->
        <div id="menuOverlay" class="menu-overlay" aria-hidden="true"></div>

        <script>
            // Close the sidebar when clicking anywhere outside of it.
            (function() {
                // Use capture phase so we run before other handlers
                document.addEventListener('click', function (e) {
                    try {
                        var sidebar = document.getElementById('sidebar');
                        if (!sidebar) return;

                        // Consider sidebar 'open' when it has the 'show' class (used by toggleMenu)
                        var isOpen = sidebar.classList.contains('show');
                        if (!isOpen) return;

                        // If the click is inside the sidebar or on the menu button, ignore
                        var menuBtn = document.getElementById('menuBtn');
                        if (sidebar.contains(e.target) || (menuBtn && menuBtn.contains(e.target))) return;

                        // Otherwise close the sidebar and all overlays on the page
                        sidebar.classList.remove('show');
                        var overlays = Array.from(document.querySelectorAll('.menu-overlay'));
                        overlays.forEach(function(o) { o.classList.remove('show'); });
                        document.body.style.overflow = '';
                    } catch (err) {
                        // swallow errors to avoid breaking pages
                        console.debug('sidebar close handler error', err);
                    }
                }, true); // use capture to run before other handlers
            })();
        </script>
