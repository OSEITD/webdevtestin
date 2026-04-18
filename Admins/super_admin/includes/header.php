<?php
// Require centralized init to configure error reporting, session and output buffering
require_once __DIR__ . '/init.php';

// Calculate the base URL for the admin section - use relative path for portability
$adminBaseUrl = '..';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="admin-base-url" content="<?php echo $adminBaseUrl; ?>">
    <meta name="csrf-token" content="<?php echo CSRFHelper::getToken(); ?>">
    
    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap CSS (utilities & grid used in many admin pages) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 for popups -->
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Admin styles -->
    <link rel="stylesheet" href="<?php echo $adminBaseUrl; ?>/assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="<?php echo $adminBaseUrl; ?>/assets/css/dashboard-improvements.css">
    <link rel="stylesheet" href="<?php echo $adminBaseUrl; ?>/assets/css/view-details.css">
    <link rel="stylesheet" href="<?php echo $adminBaseUrl; ?>/assets/css/form-validation.css">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo $adminBaseUrl; ?>/manifest.json">
    <meta name="theme-color" content="#2e0b3f">
    <link rel="apple-touch-icon" href="<?php echo $adminBaseUrl; ?>/assets/images/icon-192x192.png">
    
    <!-- CORE SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    
    <?php
    require_once __DIR__ . '/../api/supabase-client.php';
    ?>
    <script>
        // Initialize Supabase Client Globally (singleton – only create once)
        window.SUPABASE_URL = '<?php echo $supabaseUrl; ?>';
        window.SUPABASE_ANON_KEY = '<?php echo $supabaseKey; ?>';
        if (!window.supabaseClient) {
            window.supabaseClient = window.supabase.createClient(window.SUPABASE_URL, window.SUPABASE_ANON_KEY);
        }
        // Expose current user ID for presence tracking
        window.CURRENT_USER_ID = '<?php echo $_SESSION['user_id'] ?? ''; ?>';
        window.CURRENT_USER_FULLNAME = '<?php echo addslashes($_SESSION['user_fullname'] ?? ''); ?>';
        window.CURRENT_USER_ROLE = '<?php echo $_SESSION['role'] ?? 'super_admin'; ?>';
    </script>
    
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/presence.js" defer></script>
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/admin-scripts.js" defer></script>
    <!-- Currency Management System -->
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/currency.js?v=<?php echo time(); ?>" defer></script>
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/currency-display.js?v=<?php echo time(); ?>" defer></script>
    
    <script>
        // Initialize currency manager for real-time updates
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                if (typeof window.supabaseClient !== 'undefined' && typeof CurrencyManager !== 'undefined') {
                    const currencyManager = CurrencyManager.getInstance();
                    await currencyManager.init(
                        window.supabaseClient, 
                        '<?php echo $_SESSION['user_id'] ?? ''; ?>'
                    );
                    
                    // Initialize display manager for automatic formatting
                    if (typeof initializeCurrencyDisplays !== 'undefined') {
                        window.displayManager = initializeCurrencyDisplays(currencyManager);
                    }
                    
                    // Expose globally for debugging
                    window.currencyManager = currencyManager;
                    console.log('✓ Currency manager initialized - real-time updates active');
                }
            } catch (error) {
                console.warn('Currency manager initialization skipped:', error.message);
            }
        });
    </script>
    
    <script>
        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                const swPath = '<?php echo $adminBaseUrl; ?>/service-worker.js';
                navigator.serviceWorker.register(swPath)
                    .then(registration => {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
    </script>
    
    <title><?php echo $pageTitle ?? 'Admin Dashboard'; ?></title>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- CSS for Search & Notifications -->
    <link rel="stylesheet" href="<?php echo $adminBaseUrl; ?>/assets/css/search-notifications.css">
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

    <header class="top-header">
        <div class="header-content">
            <img src="<?php echo $adminBaseUrl; ?>/assets/img/Logo.png" alt="Delivery Pro" class="app-logo" onerror="this.onerror=null;this.src='https://placehold.co/100x40?text=Logo+Not+Found';">

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
                            <a href="notifications.php" class="btn-link">
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
    <div id="menuOverlay" class="menu-overlay"></div>

    <!-- JavaScript for Search & Notifications -->
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/search-notifications.js"></script>
    <!-- Admin Notification System is handled by NotificationManager in search-notifications.js -->
    <!-- Admin Push Notification Manager -->
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/admin-push-manager.js"></script>
    
    <?php include __DIR__ . '/sidebar.php'; ?>