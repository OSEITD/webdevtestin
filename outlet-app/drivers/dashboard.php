<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 604800);
    ini_set('session.cookie_lifetime', 604800);
    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once '../includes/env.php';
EnvLoader::load();

error_log("Dashboard access attempt - User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . ", Role: " . ($_SESSION['role'] ?? 'NOT SET'));

if (isset($_GET['debug'])) {
    echo "<div style='background: #f0f0f0; padding: 20px; margin: 10px; border: 1px solid #ccc;'>";
    echo "<h3>Dashboard Session Debug</h3>";
    echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
    echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'NOT SET') . "</p>";
    echo "<p><strong>Role:</strong> " . ($_SESSION['role'] ?? 'NOT SET') . "</p>";
    echo "<p><strong>Email:</strong> " . ($_SESSION['email'] ?? 'NOT SET') . "</p>";
    echo "<p><strong>Company ID:</strong> " . ($_SESSION['company_id'] ?? 'NOT SET') . "</p>";
    echo "<p><strong>Full Session:</strong></p>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    echo "</div>";
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php?role=driver&error=no_session');
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php?role=driver&error=wrong_role');
    exit();
}
$driverName = $_SESSION['full_name'] ?? 'Driver';
$pageTitle = "Driver Dashboard - $driverName";

require_once '../includes/company_helper.php';

$companyInfo = null;
if (isset($_SESSION['company_id']) && !empty($_SESSION['company_id'])) {
    $supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
    $accessToken = $_SESSION['access_token'] ?? '';
    $companyInfo = getCompanyInfo($_SESSION['company_id'], $supabaseUrl, $accessToken);
}

$brandingColors = getCompanyBrandingColors($companyInfo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/driver-dashboard-new.css?v=<?php echo time(); ?>">
    
    <style>
        
        .company-header {
            background: linear-gradient(135deg, <?php echo $brandingColors['primary']; ?> 0%, <?php echo $brandingColors['secondary']; ?> 100%);
            color: white;
            padding: 1.5rem 2rem;
            margin: 0 0 2rem 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .company-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .company-header-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .company-branding {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .company-logo {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .company-logo i {
            font-size: 24px;
            color: white;
        }
        
        .company-info h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .company-subdomain {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .subdomain-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .driver-status {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }
        
        .driver-info {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .driver-info i {
            font-size: 1rem;
        }
        
        
        @media (max-width: 768px) {
            .company-header {
                padding: 1rem;
                margin: 0 0 1.5rem 0;
            }
            
            .company-header-content {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .driver-status {
                align-items: flex-start;
                text-align: left;
            }
            
            .company-info h1 {
                font-size: 1.5rem;
            }
        }
        
        
        .driver-status-display {
            display: none !important;
        }

        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, <?php echo $brandingColors['primary']; ?> 0%, <?php echo $brandingColors['secondary']; ?> 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        .loading-overlay.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loading-container {
            text-align: center;
            color: white;
            max-width: 500px;
            padding: 2rem;
        }

        .loading-animation {
            position: relative;
            width: 200px;
            height: 120px;
            margin: 0 auto 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        
        .delivery-truck {
            width: 120px;
            height: 70px;
            position: relative;
            animation: bounce 1.2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .truck-body {
            width: 80px;
            height: 40px;
            background: white;
            border-radius: 6px;
            position: absolute;
            bottom: 20px;
            left: 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .truck-cabin {
            width: 35px;
            height: 35px;
            background: rgba(255,255,255,0.95);
            position: absolute;
            bottom: 20px;
            right: -5px;
            border-radius: 6px 6px 0 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .truck-cabin::after {
            content: '';
            width: 20px;
            height: 15px;
            background: rgba(102, 126, 234, 0.3);
            position: absolute;
            top: 5px;
            left: 7.5px;
            border-radius: 3px;
        }

        .truck-wheel {
            width: 18px;
            height: 18px;
            background: white;
            border: 4px solid rgba(0,0,0,0.3);
            border-radius: 50%;
            position: absolute;
            bottom: 0;
            animation: rotate 0.8s linear infinite;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .truck-wheel:first-of-type {
            left: 12px;
        }

        .truck-wheel:last-of-type {
            right: 12px;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            letter-spacing: -0.5px;
        }

        .loading-subtitle {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 2.5rem;
            font-weight: 400;
        }

        .loading-progress {
            width: 100%;
            max-width: 300px;
            height: 5px;
            background: rgba(255,255,255,0.25);
            border-radius: 3px;
            overflow: hidden;
            margin: 0 auto 2.5rem;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .loading-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, rgba(255,255,255,0.8), white);
            border-radius: 3px;
            animation: progress 2.5s ease-in-out infinite;
            box-shadow: 0 0 10px rgba(255,255,255,0.5);
        }

        @keyframes progress {
            0% { width: 0%; transform: translateX(0); }
            50% { width: 75%; }
            100% { width: 100%; transform: translateX(0); }
        }

        .loading-steps {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            align-items: center;
        }

        .loading-step {
            opacity: 0.4;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .loading-step i {
            width: 18px;
            text-align: center;
        }

        .loading-step.active {
            opacity: 1;
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .loading-container {
                padding: 1.5rem;
            }

            .loading-text {
                font-size: 1.5rem;
            }

            .loading-subtitle {
                font-size: 0.95rem;
            }
            
            .loading-animation {
                width: 150px;
                height: 90px;
                margin-bottom: 2rem;
            }

            .delivery-truck {
                width: 90px;
                height: 55px;
            }

            .truck-body {
                width: 60px;
                height: 30px;
                bottom: 15px;
            }

            .truck-cabin {
                width: 28px;
                height: 28px;
                bottom: 15px;
            }

            .truck-wheel {
                width: 14px;
                height: 14px;
                border-width: 3px;
            }
            
            .loading-progress {
                max-width: 250px;
                height: 4px;
            }

            .loading-step {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-container">
            <div class="loading-animation">
                <div class="delivery-truck">
                    <div class="truck-body"></div>
                    <div class="truck-cabin"></div>
                    <div class="truck-wheel"></div>
                    <div class="truck-wheel"></div>
                </div>
            </div>
            
            <div class="loading-text">Loading Driver Dashboard</div>
            <div class="loading-subtitle">Preparing your delivery routes...</div>
            
            <div class="loading-progress">
                <div class="loading-progress-bar"></div>
            </div>
            
            <div class="loading-steps">
                <div class="loading-step active"><i class="fas fa-check-circle"></i> Connecting to server...</div>
                <div class="loading-step"><i class="fas fa-check-circle"></i> Loading your trips...</div>
                <div class="loading-step"><i class="fas fa-check-circle"></i> Fetching performance data...</div>
                <div class="loading-step"><i class="fas fa-check-circle"></i> Almost ready...</div>
            </div>
        </div>
    </div>

    <div class="driver-app" id="driverApp">
        <?php include 'includes/navbar.php'; ?>
        <main class="dashboard-main">
            <!-- Professional Company Header -->
            <?php if ($companyInfo): ?>
            <div class="company-header">
                <div class="company-header-content">
                    <div class="company-branding">
                        <div class="company-logo">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="company-info">
                            <h1><?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Company Name'); ?></h1>
            
                        </div>
                    </div>
                    
                    <div class="driver-status">
                        <div class="driver-info">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($driverName); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif (isset($_SESSION['company_name'])): ?>
           
            <div class="company-header">
                <div class="company-header-content">
                    <div class="company-branding">
                        <div class="company-logo">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="company-info">
                            <h1><?php echo htmlspecialchars($_SESSION['company_name']); ?></h1>
                            <div class="company-subdomain">
                                <span>Driver Portal</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="driver-status">
                        <div class="driver-info">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($driverName); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="dashboard-header">
                <h1 class="dashboard-title">Driver Dashboard</h1>
                <p class="welcome-message">Welcome, <?php echo htmlspecialchars($driverName); ?>.</p>
            </div>

            <div class="dashboard-grid">
                <!-- Active Trip Card -->
                <section id="activeTripCard" class="dashboard-card active-trip-card" style="display:none;">
                    <h2 class="section-title"><i class="fas fa-route"></i> Active Trip</h2>
                    <div id="activeTripDetails"></div>
                </section>

                <!-- Upcoming Trips -->
                <section id="upcomingTripsSection" class="dashboard-card">
                    <h2 class="section-title"><i class="fas fa-calendar-alt"></i> Upcoming Trips</h2>
                    <div id="upcomingTripsList"><p>Loading upcoming trips...</p></div>
                    <div id="liveLocationMapContainer" class="live-location-container" style="display:none;">
                        <h3 class="map-title"><i class="fas fa-map-marker-alt"></i> Live Driver Location</h3>
                        <div class="map-wrapper">
                            <div id="liveLocationMap" class="live-location-map"></div>
                            <!-- Map Overlay Controls -->
                            <div class="map-overlay-controls">
                                <button type="button" class="map-overlay-btn route-btn" onclick="event.preventDefault(); window.driverDashboard.toggleRouteStops(); return false;" title="Toggle Route Stops">
                                    <i class="fas fa-map-signs"></i>
                                </button>
                                <button type="button" class="map-overlay-btn center-btn" onclick="event.preventDefault(); window.driverDashboard.centerOnDriver(); return false;" title="Center on My Location">
                                    <i class="fas fa-crosshairs"></i>
                                </button>
                                <button type="button" class="map-overlay-btn fullscreen-btn" onclick="event.preventDefault(); window.driverDashboard.openFullscreenMap(); return false;" title="View Full Map">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Performance Snapshot -->
                <section id="performanceSnapshot" class="dashboard-card">
                    <h2 class="section-title"><i class="fas fa-chart-line"></i> Performance Snapshot</h2>
                    <div id="performanceStats" class="performance-stats-grid">
                        <div class="stat-card stat-green"><i class="fas fa-check-circle"></i> <span id="tripsToday">0</span><br><span class="stat-label">Trips Today</span></div>
                        <div class="stat-card stat-blue"><i class="fas fa-calendar-week"></i> <span id="tripsWeek">0</span><br><span class="stat-label">Trips This Week</span></div>
                        <div class="stat-card stat-green"><i class="fas fa-box"></i> <span id="parcelsDelivered">0</span><br><span class="stat-label">Parcels Delivered</span></div>
                        <div class="stat-card stat-red"><i class="fas fa-undo"></i> <span id="parcelsReturned">0</span><br><span class="stat-label">Parcels Returned</span></div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Fullscreen Map Modal -->
    <div id="fullscreenMapModal" class="fullscreen-modal" style="display: none;">
        <div class="fullscreen-modal-content">
            <div class="fullscreen-map-header">
                <h2 class="fullscreen-map-title">
                    <i class="fas fa-map-marked-alt"></i> 
                    Live Route Navigation
                </h2>
                <div class="fullscreen-map-controls">
                    <button type="button" id="fullscreenToggleRouteBtn" class="btn-secondary" onclick="event.preventDefault(); window.driverDashboard.toggleRouteStops(); return false;">
                        <i class="fas fa-map-signs"></i> <span>Toggle Route</span>
                    </button>
                    <button type="button" id="fullscreenCenterBtn" class="btn-secondary" onclick="event.preventDefault(); window.driverDashboard.centerOnDriver(); return false;">
                        <i class="fas fa-crosshairs"></i> <span>Center</span>
                    </button>
                    <button type="button" id="fullscreenTrackingBtn" class="btn-secondary" onclick="event.preventDefault(); window.driverDashboard.toggleLocationTracking(); return false;">
                        <i class="fas fa-location-arrow"></i> <span>Live Tracking</span>
                    </button>
                    <button type="button" id="closeFullscreenBtn" class="btn-danger" onclick="event.preventDefault(); window.driverDashboard.closeFullscreenMap(); return false;">
                        <i class="fas fa-times"></i> <span>Close</span>
                    </button>
                </div>
            </div>
            <div id="fullscreenMap" class="fullscreen-map"></div>
            <div class="fullscreen-map-footer">
                <div class="map-legend">
                    <div class="legend-item">
                        <div class="legend-marker driver-legend"></div>
                        <span>Your Location</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-marker outlet-legend"></div>
                        <span>Outlet Stops</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-line"></div>
                        <span>Delivery Route</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet Maps JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Professional GPS Tracking System -->
    <script src="../assets/js/location-cache-service.js"></script>
    <script src="../assets/js/professional-gps-tracker.js"></script>
    
    <!-- Push Notification System -->
    <script src="assets/js/push-manager.js"></script>
    
    <!-- Professional JavaScript for Driver Dashboard -->
    <script src="assets/js/driver-dashboard.js"></script>
    
    <!-- Load Performance Statistics -->
    <script>
    async function loadPerformanceStats() {
        // Check if cached stats exist and render immediately to prevent blank cards
        const stored = localStorage.getItem('driverPerformanceSnapshot');
        if (stored) {
            try {
                const stats = JSON.parse(stored);
                console.log('[Performance Stats] Rendering cached data immediately:', stats);
                document.getElementById('tripsToday').textContent = stats.trips_today ?? 0;
                document.getElementById('tripsWeek').textContent = stats.trips_week ?? 0;
                document.getElementById('parcelsDelivered').textContent = stats.parcels_delivered ?? 0;
                document.getElementById('parcelsReturned').textContent = stats.parcels_returned ?? 0;
            } catch(e) {
                console.warn('[Performance Stats] Failed to parse cached data:', e);
            }
        }
        
        try {
            console.log('[Performance Stats] Fetching fresh data from API...');

            // Include credentials to ensure session cookie is sent and handle non-2xx responses
            const response = await fetch('api/performance_stats.php', { credentials: 'same-origin' });
            if (!response.ok) {
                console.warn('[Performance Stats] HTTP Error:', response.status, response.statusText);
                // Try stored snapshot as fallback
                const stored = localStorage.getItem('driverPerformanceSnapshot');
                if (stored) {
                    const stats = JSON.parse(stored);
                    document.getElementById('tripsToday').textContent = stats.trips_today ?? 0;
                    document.getElementById('tripsWeek').textContent = stats.trips_week ?? 0;
                    document.getElementById('parcelsDelivered').textContent = stats.parcels_delivered ?? 0;
                    document.getElementById('parcelsReturned').textContent = stats.parcels_returned ?? 0;
                }
                return;
            }
            const result = await response.json();

            if (result.success && result.stats) {
                console.log('[Performance Stats] Loaded:', result.stats);

                document.getElementById('tripsToday').textContent = result.stats.trips_today || 0;
                document.getElementById('tripsWeek').textContent = result.stats.trips_week || 0;
                document.getElementById('parcelsDelivered').textContent = result.stats.parcels_delivered || 0;
                document.getElementById('parcelsReturned').textContent = result.stats.parcels_returned || 0;

                // Persist successful snapshot for fallback
                try { localStorage.setItem('driverPerformanceSnapshot', JSON.stringify(result.stats)); } catch(e) {}

                console.log('[Performance Stats] UI updated and snapshot saved');
            } else {
                console.warn('[Performance Stats] Failed to load:', result.error || 'Unknown error');
                // Try stored snapshot as fallback
                const stored = localStorage.getItem('driverPerformanceSnapshot');
                if (stored) {
                    const stats = JSON.parse(stored);
                    document.getElementById('tripsToday').textContent = stats.trips_today ?? 0;
                    document.getElementById('tripsWeek').textContent = stats.trips_week ?? 0;
                    document.getElementById('parcelsDelivered').textContent = stats.parcels_delivered ?? 0;
                    document.getElementById('parcelsReturned').textContent = stats.parcels_returned ?? 0;
                }
            }
        } catch (error) {
            console.error('[Performance Stats] Error loading statistics:', error);
            // On error, try last stored snapshot
            const stored = localStorage.getItem('driverPerformanceSnapshot');
            if (stored) {
                const stats = JSON.parse(stored);
                document.getElementById('tripsToday').textContent = stats.trips_today ?? 0;
                document.getElementById('tripsWeek').textContent = stats.trips_week ?? 0;
                document.getElementById('parcelsDelivered').textContent = stats.parcels_delivered ?? 0;
                document.getElementById('parcelsReturned').textContent = stats.parcels_returned ?? 0;
            }
        }
    }

    // Load performance stats on page load
    loadPerformanceStats();
    </script>
    
    <!-- Push Notification Subscription System -->
    <script>
    (async function() {
        console.log('[Driver Push] Initializing push notification system...');
        
        
        console.log('[Driver Push] User role:', '<?php echo $_SESSION['role']; ?>');
        console.log('[Driver Push] Session storage dismissed:', sessionStorage.getItem('notification_prompt_dismissed'));
        console.log('[Driver Push] Notification API available:', 'Notification' in window);
        console.log('[Driver Push] Service Worker API available:', 'serviceWorker' in navigator);
        console.log('[Driver Push] Current notification permission:', Notification.permission);
        
        
        if (window.location.search.includes('reset_notifications=1')) {
            sessionStorage.removeItem('notification_prompt_dismissed');
            console.log('[Driver Push] Cleared notification dismissal flag');
            
            const url = new URL(window.location);
            url.searchParams.delete('reset_notifications');
            window.history.replaceState({}, '', url);
        }
        
        
        const VAPID_PUBLIC_KEY = '<?php echo htmlspecialchars(EnvLoader::get('VAPID_PUBLIC_KEY')); ?>';
        
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');
            
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
        
        async function subscribeToPush(registration, existingSubscription = null, forceNew = false) {
            try {
                let subscription = existingSubscription;
                
                
                if (forceNew || !subscription) {
                    
                    if (existingSubscription && forceNew) {
                        console.log('[Driver Push] Unsubscribing from old subscription...');
                        await existingSubscription.unsubscribe();
                    }
                    
                    console.log('[Driver Push] Creating new subscription with current VAPID keys...');
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                    });
                }
                
                
                const response = await fetch('./api/push/save_subscription.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subscription: subscription.toJSON(),
                        endpoint: subscription.endpoint,
                        keys: {
                            p256dh: subscription.toJSON().keys.p256dh,
                            auth: subscription.toJSON().keys.auth
                        }
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    console.log('✅ Push notifications enabled for driver');
                    localStorage.setItem('driver_push_last_check', Date.now().toString());
                    return true;
                } else {
                    console.error('Failed to save subscription:', result.error);
                    return false;
                }
            } catch (error) {
                console.error('Error subscribing to push:', error);
                return false;
            }
        }
        
        function showSuccessMessage() {
            const message = document.createElement('div');
            message.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
                z-index: 10001;
                display: flex;
                align-items: center;
                gap: 12px;
                animation: slideInRight 0.3s ease-out;
            `;
            message.innerHTML = `
                <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                <div>
                    <div style="font-weight: 600; font-size: 15px;">Notifications Enabled!</div>
                    <div style="font-size: 13px; opacity: 0.95;">You'll receive trip assignment updates</div>
                </div>
            `;
            document.body.appendChild(message);
            
            setTimeout(() => {
                message.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => message.remove(), 300);
            }, 4000);
        }
        
        function showNotificationPrompt(onEnable) {
            console.log('[Driver Push] showNotificationPrompt called');
            
            
            if (sessionStorage.getItem('notification_prompt_dismissed')) {
                console.log('[Driver Push] Prompt was dismissed, not showing');
                return;
            }
            
            console.log('[Driver Push] Creating notification banner...');
            
            
            const banner = document.createElement('div');
            banner.id = 'notification-prompt-banner';
            banner.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 16px 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                animation: slideDown 0.3s ease-out;
            `;
            
            banner.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                    <i class="fas fa-bell" style="font-size: 24px;"></i>
                    <div>
                        <div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">
                            Enable Push Notifications
                        </div>
                        <div style="font-size: 13px; opacity: 0.95;">
                            Get instant alerts when new trips are assigned to you
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button id="enable-notifications-btn" style="
                        background: white;
                        color: #667eea;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 6px;
                        font-weight: 600;
                        cursor: pointer;
                        font-size: 14px;
                        transition: transform 0.2s;
                    ">
                        Enable Now
                    </button>
                    <button id="dismiss-notifications-btn" style="
                        background: transparent;
                        color: white;
                        border: 1px solid rgba(255,255,255,0.5);
                        padding: 10px 16px;
                        border-radius: 6px;
                        font-weight: 500;
                        cursor: pointer;
                        font-size: 14px;
                    ">
                        Later
                    </button>
                </div>
            `;
            
            document.body.insertBefore(banner, document.body.firstChild);
            
            
            if (!document.getElementById('notification-prompt-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-prompt-styles';
                style.textContent = `
                    @keyframes slideDown {
                        from {
                            transform: translateY(-100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateY(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideOutRight {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                    }
                    @keyframes pulse {
                        0%, 100% {
                            transform: scale(1);
                            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
                        }
                        50% {
                            transform: scale(1.02);
                            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
                        }
                    }
                    #enable-notifications-btn:hover {
                        transform: scale(1.05);
                    }
                    #dismiss-notifications-btn:hover {
                        background: rgba(255,255,255,0.1);
                    }
                `;
                document.head.appendChild(style);
            }
            
            
            document.getElementById('enable-notifications-btn').addEventListener('click', async () => {
                banner.remove();
                await onEnable();
            });
            
            
            document.getElementById('dismiss-notifications-btn').addEventListener('click', () => {
                banner.remove();
                sessionStorage.setItem('notification_prompt_dismissed', 'true');
            });
        }
        
        async function initPushNotifications() {
            try {
                console.log('[Driver Push] Starting initialization...');
                console.log('[Driver Push] Notification API available:', 'Notification' in window);
                console.log('[Driver Push] Service Worker API available:', 'serviceWorker' in navigator);
                console.log('[Driver Push] Current permission:', Notification.permission);
                
                
                if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                    console.log('[Driver Push] Push notifications not supported');
                    return;
                }
                
                
                const currentPath = window.location.pathname;
                console.log('[Driver Push] Current path:', currentPath);
                
                // Calculate the base path to outlet-app root
                let basePath = '';
                if (currentPath.includes('/outlet-app/')) {
                    basePath = currentPath.substring(0, currentPath.indexOf('/outlet-app/') + '/outlet-app/'.length - 1);
                } else {
                    // For outlet.localhost setup where outlet-app is at root
                    basePath = '';
                }
                
                const swPath = basePath + '/drivers/sw.js';
                
                console.log('[Driver Push] Base path:', basePath);
                console.log('[Driver Push] Service Worker path:', swPath);
                console.log('[Driver Push] Full SW URL:', new URL(swPath, window.location.href).href);
                
                const registration = await navigator.serviceWorker.register(swPath);
                await navigator.serviceWorker.ready;
                console.log('[Driver Push] Service Worker registered:', registration.scope);
                
                
                const existingSubscription = await registration.pushManager.getSubscription();
                console.log('[Driver Push] Existing subscription:', existingSubscription ? 'YES' : 'NO');
                
                if (existingSubscription) {
                    console.log('[Driver Push] ✅ Already subscribed to push notifications');
                    
                    
                    const lastCheck = localStorage.getItem('driver_push_last_check');
                    const vapidVersion = localStorage.getItem('driver_vapid_version');
                    const currentVapidVersion = '1'; 
                    const now = Date.now();
                    const ONE_DAY = 24 * 60 * 60 * 1000;
                    
                    
                    if (vapidVersion !== currentVapidVersion || !lastCheck || (now - parseInt(lastCheck)) > ONE_DAY) {
                        console.log('[Driver Push] Re-subscribing with current VAPID keys...');
                        console.log('[Driver Push] Reason:', vapidVersion !== currentVapidVersion ? 'VAPID keys changed' : 'Periodic refresh');
                        try {
                            await subscribeToPush(registration, existingSubscription, true);
                            localStorage.setItem('driver_vapid_version', currentVapidVersion);
                        } catch (error) {
                            console.error('[Driver Push] Re-subscription failed:', error);
                        }
                    }
                    return;
                }
                
                
                console.log('[Driver Push] No existing subscription, showing prompt...');
                console.log('[Driver Push] Session storage dismissed:', sessionStorage.getItem('notification_prompt_dismissed'));
                
                if (Notification.permission === 'default' || Notification.permission === 'granted') {
                    console.log('[Driver Push] Calling showNotificationPrompt...');
                    showNotificationPrompt(async () => {
                        try {
                            const permission = await Notification.requestPermission();
                            if (permission === 'granted') {
                                const success = await subscribeToPush(registration);
                                if (success) {
                                    showSuccessMessage();
                                } else {
                                    alert('Failed to save notification subscription. Please try again.');
                                }
                            } else {
                                alert('Please allow notifications to receive trip assignment alerts');
                            }
                        } catch (error) {
                            console.error('Error enabling notifications:', error);
                            alert('Failed to enable notifications. Please try again.');
                        }
                    });
                }
                
            } catch (error) {
                console.error('[Dashboard] Push notification init error:', error);
            }
        }
        
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPushNotifications);
        } else {
            initPushNotifications();
        }
        
        
        const urlParams = new URLSearchParams(window.location.search);
        const highlightTripId = urlParams.get('trip_id');
        
        if (highlightTripId) {
            console.log('[Dashboard] Trip ID from notification:', highlightTripId);
            
            let attempts = 0;
            const maxAttempts = 10;
            
            
            const checkAndHighlight = () => {
                attempts++;
                const tripCard = document.querySelector(`[data-trip-id="${highlightTripId}"]`);
                
                if (tripCard) {
                    console.log(`[Dashboard] Found trip card on attempt ${attempts}, highlighting...`);
                    
                    
                    setTimeout(() => {
                        tripCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        
                        tripCard.style.animation = 'pulse 1.5s ease-in-out 3';
                        tripCard.style.border = '3px solid #667eea';
                        tripCard.style.boxShadow = '0 4px 20px rgba(102, 126, 234, 0.4)';
                        tripCard.style.transition = 'all 0.3s ease';
                        
                        
                        setTimeout(() => {
                            tripCard.style.animation = '';
                            tripCard.style.border = '';
                            tripCard.style.boxShadow = '';
                        }, 5000);
                    }, 300);
                    
                    
                    const cleanUrl = window.location.pathname;
                    window.history.replaceState({}, document.title, cleanUrl);
                } else if (attempts < maxAttempts) {
                    console.log(`[Dashboard] Trip not found yet (attempt ${attempts}/${maxAttempts}), retrying...`);
                    setTimeout(checkAndHighlight, 500);
                } else {
                    console.log('[Dashboard] Trip card not found after max attempts');
                }
            };
            
            
            if (document.readyState === 'complete') {
                setTimeout(checkAndHighlight, 1000);
            } else {
                window.addEventListener('load', () => {
                    setTimeout(checkAndHighlight, 1000);
                });
            }
        }
        
        
        window.addEventListener('beforeunload', async () => {
            
            try {
                await fetch('../api/mark_session_inactive.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        session_id: '<?php echo session_id(); ?>',
                        user_id: '<?php echo $_SESSION["user_id"] ?? ""; ?>'
                    })
                });
            } catch (e) {
                console.log('Session cleanup failed:', e);
            }
        });
    })();
    </script>

    <!-- Loading Overlay Control Script -->
    <script>
        (function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            const steps = document.querySelectorAll('.loading-step');
            let currentStep = 0;
            let stepInterval;
            const startTime = Date.now();
            const minimumLoadTime = 800; 
            let pageLoaded = false;
            let dataLoaded = false;
            
            
            window.markDriverDashboardLoaded = function() {
                console.log('[Loading Overlay] Driver dashboard data loaded');
                dataLoaded = true;
                checkAndHideLoading();
            };
            
            
            function showNextStep() {
                if (currentStep < steps.length) {
                    steps.forEach(step => step.classList.remove('active'));
                    steps[currentStep].classList.add('active');
                    currentStep++;
                }
            }
            
            
            stepInterval = setInterval(showNextStep, 400);
            
            
            function hideLoadingOverlay() {
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, minimumLoadTime - elapsedTime);
                
                console.log('[Loading Overlay] Hiding in ' + remainingTime + 'ms');
                
                
                setTimeout(() => {
                    clearInterval(stepInterval);
                    
                    
                    if (steps.length > 0) {
                        steps.forEach(step => step.classList.remove('active'));
                        steps[steps.length - 1].classList.add('active');
                    }
                    
                    
                    setTimeout(() => {
                        loadingOverlay.classList.add('hidden');
                        
                        
                        setTimeout(() => {
                            if (loadingOverlay && loadingOverlay.parentNode) {
                                loadingOverlay.parentNode.removeChild(loadingOverlay);
                            }
                        }, 500);
                    }, 300);
                }, remainingTime);
            }
            
            
            function checkAndHideLoading() {
                if (pageLoaded && dataLoaded) {
                    console.log('[Loading Overlay] Both page and data ready, hiding overlay');
                    hideLoadingOverlay();
                }
            }
            
            
            function markPageLoaded() {
                if (!pageLoaded) {
                    console.log('[Loading Overlay] Page DOM loaded');
                    pageLoaded = true;
                    
                    
                    setTimeout(() => {
                        if (!dataLoaded) {
                            console.log('[Loading Overlay] Data taking too long, forcing hide');
                            dataLoaded = true;
                            checkAndHideLoading();
                        }
                    }, 2000);
                    
                    checkAndHideLoading();
                }
            }
            
            
            if (document.readyState === 'complete') {
                markPageLoaded();
            } else {
                window.addEventListener('load', markPageLoaded);
            }
            
            
            setTimeout(() => {
                if (!pageLoaded || !dataLoaded) {
                    console.log('[Loading Overlay] Timeout reached, forcing hide. Page:', pageLoaded, 'Data:', dataLoaded);
                    pageLoaded = true;
                    dataLoaded = true;
                    hideLoadingOverlay();
                }
            }, 5000);
            
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', markPageLoaded);
            } else {
                markPageLoaded();
            }
        })();
    </script>
    
    <?php include __DIR__ . '/../includes/pwa_install_button.php'; ?>
    <script src="../js/pwa-install.js"></script>
</body>
</html>