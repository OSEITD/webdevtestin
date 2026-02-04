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
    
    <!-- Core scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/admin-scripts.js" defer></script>
    <script src="<?php echo $adminBaseUrl; ?>/assets/js/search.js" defer></script>
    
    <title><?php echo $pageTitle ?? 'Admin Dashboard'; ?></title>
</head>
<body class="bg-gray-100 min-h-screen">
<header class="top-header">
            <div class="header-content">
                <div class="header-left">
                    <img src="../assets/img/Logo.png" alt="SwiftShip logo" class="app-logo" onerror="this.onerror=null;this.src='https://placehold.co/100x40?text=Logo+Not+Found';">
                </div>
                <div class="header-search">
                    <div class="search-container">
                        <input type="text" id="globalSearch" placeholder="Search companies, parcels, users...">
                        <button type="button" id="searchBtn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="search-results" id="searchResults"></div>
                </div>
                <div class="header-icons">
                    <a href="notifications.php" class="icon-btn notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="badge" id="notification-count">0</span>
                    </a>
                    <button class="icon-btn menu-toggle" id="menuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </header>

<?php include 'sidebar.php'; ?>
    <!-- Temporary debug status (visible on page for troubleshooting) -->
    <div id="debugStatus" style="position:fixed;bottom:10px;right:10px;z-index:2000;background:rgba(0,0,0,0.75);color:#fff;padding:8px 12px;border-radius:6px;font-size:0.85rem;display:none;max-width:320px;">
        <strong style="display:block;margin-bottom:6px;">Debug</strong>
        <pre id="debugStatusContent" style="white-space:pre-wrap;margin:0;font-size:0.85rem;"></pre>
        <div style="margin-top:6px;text-align:right;"><button id="debugCloseBtn" style="background:transparent;border:1px solid #fff;color:#fff;padding:4px 8px;border-radius:4px;cursor:pointer;">Close</button></div>
    </div>

    <script>
        (function(){
            // Defensive inline toggle: attach only if not already attached
            try {
                var menuBtn = document.getElementById('menuBtn');
                var closeMenu = document.getElementById('closeMenu');
                var sidebar = document.getElementById('sidebar');
                var menuOverlay = document.getElementById('menuOverlay');

                function toggleMenu() {
                    if (!sidebar || !menuOverlay) return;
                    sidebar.classList.toggle('show');
                    menuOverlay.classList.toggle('show');
                    document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
                }

                if (menuBtn && !menuBtn.dataset.toggleAttached) {
                    menuBtn.addEventListener('click', function(e){ e.stopPropagation(); toggleMenu(); });
                    menuBtn.dataset.toggleAttached = '1';
                }
                if (closeMenu && !closeMenu.dataset.toggleAttached) {
                    closeMenu.addEventListener('click', toggleMenu);
                    closeMenu.dataset.toggleAttached = '1';
                }
                if (menuOverlay && !menuOverlay.dataset.toggleAttached) {
                    menuOverlay.addEventListener('click', toggleMenu);
                    menuOverlay.dataset.toggleAttached = '1';
                }

                // Close when clicking menu links
                document.querySelectorAll('.menu-items a').forEach(function(item){
                    if (!item.dataset.toggleAttached) {
                        item.addEventListener('click', function(){ if (sidebar && sidebar.classList.contains('show')) toggleMenu(); });
                        item.dataset.toggleAttached = '1';
                    }
                });
            } catch (e) {
                console.warn('Inline sidebar toggle init failed', e);
            }
        })();
    </script>
    <script>
        // Ensure a single menu overlay exists and that the sidebar starts closed.
        document.addEventListener('DOMContentLoaded', function () {
            try {
                // Remove duplicate elements with id 'menuOverlay', keep the first
                const allOverlays = Array.from(document.querySelectorAll('#menuOverlay'));
                if (allOverlays.length === 0) {
                    const o = document.createElement('div');
                    o.id = 'menuOverlay';
                    o.className = 'menu-overlay';
                    o.setAttribute('aria-hidden', 'true');
                    document.body.appendChild(o);
                } else if (allOverlays.length > 1) {
                    for (let i = 1; i < allOverlays.length; i++) {
                        allOverlays[i].parentNode && allOverlays[i].parentNode.removeChild(allOverlays[i]);
                    }
                }

                const menuOverlay = document.getElementById('menuOverlay');
                const sidebar = document.getElementById('sidebar');

                // Ensure any pre-rendered .show markers are cleared so sidebar starts closed
                if (menuOverlay && menuOverlay.classList.contains('show')) menuOverlay.classList.remove('show');
                if (sidebar && sidebar.classList.contains('show')) sidebar.classList.remove('show');

                // Attach a single click handler to the overlay (if not already attached)
                if (menuOverlay && !menuOverlay.dataset.headerAttached) {
                    menuOverlay.addEventListener('click', function () {
                        if (sidebar) sidebar.classList.remove('show');
                        menuOverlay.classList.remove('show');
                        document.body.style.overflow = '';
                    });
                    menuOverlay.dataset.headerAttached = '1';
                }
            } catch (err) {
                console.warn('menuOverlay init error', err);
            }
        });
    </script>