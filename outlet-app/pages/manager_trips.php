<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_guard.php';
require_once '../includes/company_helper.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit(); 
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'outlet_manager') {
    header('Location: outlet_dashboard.php');
    exit();
}

$current_user = getCurrentUser();
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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>My Active Trips - Manager Dashboard</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/trips.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/trips_enhanced.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <script>
        if ('caches' in window) {
            caches.keys().then(names => names.forEach(name => caches.delete(name)));
        }
        try { localStorage.clear(); } catch(e) {}
        try { sessionStorage.clear(); } catch(e) {}
    </script>
    
    <style>
        /* header card matching parcel-pool style */
        .page-header {
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin: 20px auto;
            box-shadow: 0 10px 30px rgba(46, 13, 42, 0.3);
            max-width: 1400px;
            text-align: center;
        }
        .page-header h1, .page-header .subtitle {
            color: white;
        }

        /* match trip wizard width */
        .content-container { max-width: 1400px; }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, <?php echo $brandingColors['primary']; ?> 0%, <?php echo $brandingColors['secondary']; ?> 100%);
            display: flex;
            flex-direction: column;
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
        }

        .loading-animation {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 0 auto 2rem;
        }

        
        .route-animation {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 120px;
            height: 120px;
        }

        .route-circle {
            position: absolute;
            border-radius: 50%;
            border: 3px solid white;
            animation: pulse 2s ease-in-out infinite;
        }

        .route-circle:nth-child(1) {
            width: 120px;
            height: 120px;
            top: 0;
            left: 0;
            opacity: 0.3;
        }

        .route-circle:nth-child(2) {
            width: 90px;
            height: 90px;
            top: 15px;
            left: 15px;
            opacity: 0.5;
            animation-delay: 0.5s;
        }

        .route-circle:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 30px;
            left: 30px;
            opacity: 0.7;
            animation-delay: 1s;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.3;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.7;
            }
        }

        
        .center-truck {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 40px;
            color: white;
            animation: truckSpin 3s linear infinite;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        @keyframes truckSpin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        
        .route-point {
            position: absolute;
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            animation: pointBlink 1.5s ease-in-out infinite;
        }

        .route-point:nth-child(1) {
            top: 10%;
            left: 50%;
            animation-delay: 0s;
        }

        .route-point:nth-child(2) {
            top: 50%;
            right: 10%;
            animation-delay: 0.5s;
        }

        .route-point:nth-child(3) {
            bottom: 10%;
            left: 50%;
            animation-delay: 1s;
        }

        .route-point:nth-child(4) {
            top: 50%;
            left: 10%;
            animation-delay: 1.5s;
        }

        @keyframes pointBlink {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.5);
                opacity: 0.5;
            }
        }

        
        .loading-text {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .loading-subtext {
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        
        .loading-spinner {
            width: 50px;
            height: 50px;
            margin: 0 auto;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        
        @media (max-width: 768px) {
            .loading-animation {
                width: 140px;
                height: 140px;
            }

            .route-animation {
                width: 100px;
                height: 100px;
            }

            .center-truck {
                font-size: 32px;
            }

            .loading-text {
                font-size: 1.25rem;
            }

            .loading-subtext {
                font-size: 0.875rem;
            }
        }

        
        .trips-summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .summary-card {
            flex: 1;
            min-width: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .summary-card.status-scheduled {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .summary-card.status-accepted {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        }

        .summary-card.status-in-transit {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .summary-card.status-at-outlet {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .summary-card.status-completed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .summary-card-label {
            font-size: 0.75rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 500;
        }

        .summary-card-value {
            font-size: 1.75rem;
            font-weight: 700;
        }

        
        .filters-container {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .filters-header h3 {
            margin: 0;
            color: #374151;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-toggle-btn {
            background: none;
            border: none;
             color: #3A0E36;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-btn-apply {
            background: #3b82f6;
            color: white;
        }

        .filter-btn-apply:hover {
            background: #2563eb;
        }

        .filter-btn-reset {
            background: #f3f4f6;
            color: #374151;
        }

        .filter-btn-reset:hover {
            background: #e5e7eb;
        }

        
        
        .manager-trip-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #3A0E36;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .manager-trip-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);  
        }
        
        .trip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .trip-id {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .trip-route {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
        }
        
        .route-arrow {
            color: #3A0E36;
            font-size: 1.2rem;
        }
        
        .trip-status {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: capitalize;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .status-scheduled { background: #f59e0b; }
        .status-accepted { background: #06b6d4; }
        .status-in_transit { background: #3b82f6; }
        .status-at_outlet { background: #8b5cf6; }
        .status-completed { background: #10b981; }
        
        .trip-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .trip-details > div {
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 8px;
        }

        .trip-details strong {
            display: block;
            color: #374151;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .trip-details strong i {
            margin-right: 0.25rem;
            color: #6b7280;
        }
        
        .trip-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .btn-accept {
            background: #3A0E36;
        }
        
        .btn-accept:hover {
            background: #290926;
        }
        
        .btn-track {
            background: #3A0E36;
        }
        
        .btn-track:hover {
            background: #3A0E36;
        }
        
        .btn-complete {
            background: #3A0E36;
        }
        
        .btn-complete:hover {
            background: #1c071a;
        }
        
        .btn-details {
            background: #6b7280;
        }
        
        .btn-details:hover {
            background: #4b5563;
        }

        
        .trip-stops-container {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #f3f4f6;
        }

        .trip-stop-card {
            margin-top: 0.75rem;
            padding: 1rem;
            border-radius: 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .trip-stop-card:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .no-trips {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #6b7280;
            background: white;
            border-radius: 12px;
        }
        
        .no-trips i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        .no-trips h3 {
            color: #374151;
            margin: 0.5rem 0;
        }

        .no-trips p {
            color: #6b7280;
            margin: 0;
        }
        
        .live-tracking-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .tracking-map {
            height: 400px;
            border-radius: 8px;
            margin-top: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .action-btn.loading {
            opacity: 0.7;
            pointer-events: none;
            position: relative;
        }

        .action-btn.loading::after {
            content: '';
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.9);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes fadeOut {
            0% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-8px); }
        }

        .fade-out {
            animation: fadeOut 300ms ease forwards;
        }

        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        
        @media (max-width: 768px) {
            .main-content {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
                max-width: 100%;
            }

            .content-container {
                margin: 10px 0.5rem;
                padding: 20px 15px;
                border-radius: 8px;
            }

            .dashboard-header {
                padding: 0 0.5rem;
            }

            .manager-trip-card {
                padding: 1rem;
                border-radius: 8px;
            }

            .trip-details {
                grid-template-columns: 1fr;
            }

            .trip-actions {
                flex-direction: column;
            }

            .trip-actions button {
                width: 100%;
                justify-content: center;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .trips-summary {
                flex-direction: column;
            }

            .summary-card {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-container">
            <div class="loading-animation">
                <!-- Route Points -->
                <div class="route-point"></div>
                <div class="route-point"></div>
                <div class="route-point"></div>
                <div class="route-point"></div>
                
                <!-- Route Circles -->
                <div class="route-animation">
                    <div class="route-circle"></div>
                    <div class="route-circle"></div>
                    <div class="route-circle"></div>
                </div>
                
                <!-- Center Truck Icon -->
                <div class="center-truck">
                    <i class="fas fa-truck"></i>
                </div>
            </div>
            
            <div class="loading-text">Loading Your Trips</div>
            <div class="loading-subtext">Fetching active routes and deliveries...</div>
            
            <div class="loading-spinner"></div>
        </div>
    </div>

    <div class="mobile-dashboard">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="menu-overlay" id="menuOverlay"></div>
        
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-tasks"></i> My Active Trips</h1>
                <p class="subtitle">Manage and track all trips assigned to you as a manager.</p>
            </div>
            <div class="content-container">

                <!-- Summary Cards -->
                <div class="trips-summary" id="tripsSummary" style="display: none;">
                    <div class="summary-card">
                        <span class="summary-card-label">Total Trips</span>
                        <span class="summary-card-value" id="totalTrips">0</span>
                    </div>
                    <div class="summary-card status-scheduled">
                        <span class="summary-card-label">Scheduled</span>
                        <span class="summary-card-value" id="scheduledTrips">0</span>
                    </div>
                    <div class="summary-card status-accepted">
                        <span class="summary-card-label">Accepted</span>
                        <span class="summary-card-value" id="acceptedTrips">0</span>
                    </div>
                    <div class="summary-card status-in-transit">
                        <span class="summary-card-label">In Transit</span>
                        <span class="summary-card-value" id="inTransitTrips">0</span>
                    </div>
                    <div class="summary-card status-at-outlet">
                        <span class="summary-card-label">At Outlet</span>
                        <span class="summary-card-value" id="atOutletTrips">0</span>
                    </div>
                </div>
            
                <!-- Filters -->
                <div class="filters-container">
                    <div class="filters-header">
                        <h3><i class="fas fa-filter"></i> Filter Trips</h3>
                        <button class="filter-toggle-btn" id="filterToggleBtn" onclick="toggleFilters()">
                            <i class="fas fa-chevron-up"></i>
                            <span>Hide Filters</span>
                        </button>
                    </div>
                    <div id="filtersContent">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label><i class="fas fa-info-circle"></i> Status</label>
                                <select id="statusFilter" onchange="filterTrips()">
                                    <option value="">All Statuses</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="accepted">Accepted</option>
                                    <option value="in_transit">In Transit</option>
                                    <option value="at_outlet">At Outlet</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label><i class="fas fa-calendar"></i> Date</label>
                                <input type="date" id="dateFilter" onchange="filterTrips()">
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button class="filter-btn filter-btn-apply" onclick="filterTrips()">
                                <i class="fas fa-check"></i> Apply Filters
                            </button>
                            <button class="filter-btn filter-btn-reset" onclick="resetFilters()">
                                <i class="fas fa-times"></i> Reset
                            </button>
                            <button class="filter-btn filter-btn-reset" onclick="loadActiveTrips()" style="margin-left: auto;">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Active Trips List -->
                <div id="activeTripsContainer">
                    <div class="no-trips">
                        <i class="fas fa-truck"></i>
                        <h3>Loading your active trips...</h3>
                        <p>Please wait while we fetch your assigned trips.</p>
                    </div>
                </div>
                
                <!-- Live Tracking Section -->
                <div id="liveTrackingSection" class="live-tracking-section" style="display: none;">
                    <div class="trip-header">
                        <h3><i class="fas fa-map-marked-alt"></i> Live Trip Tracking</h3>
                        <button class="action-btn btn-details" onclick="closeLiveTracking()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                    <div id="trackingMap" class="tracking-map"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        let activeTrips = [];
        let allTrips = []; 
        let trackingMap = null;
        let currentTrackingTrip = null;

        
        function toggleFilters() {
            const content = document.getElementById('filtersContent');
            const btn = document.getElementById('filterToggleBtn');
            const icon = btn.querySelector('i');
            const text = btn.querySelector('span');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.className = 'fas fa-chevron-up';
                text.textContent = 'Hide Filters';
            } else {
                content.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
                text.textContent = 'Show Filters';
            }
        }

        
        function resetFilters() {
            document.getElementById('statusFilter').value = '';
            document.getElementById('dateFilter').value = '';
            filterTrips();
        }

        
        function updateSummary(trips) {
            const summary = document.getElementById('tripsSummary');
            if (!trips || trips.length === 0) {
                summary.style.display = 'none';
                return;
            }

            summary.style.display = 'flex';
            
            const total = trips.length;
            const scheduled = trips.filter(t => t.trip_status === 'scheduled').length;
            const accepted = trips.filter(t => t.trip_status === 'accepted').length;
            const inTransit = trips.filter(t => t.trip_status === 'in_transit').length;
            const atOutlet = trips.filter(t => t.trip_status === 'at_outlet').length;

            document.getElementById('totalTrips').textContent = total;
            document.getElementById('scheduledTrips').textContent = scheduled;
            document.getElementById('acceptedTrips').textContent = accepted;
            document.getElementById('inTransitTrips').textContent = inTransit;
            document.getElementById('atOutletTrips').textContent = atOutlet;
        }

        async function fetchFullTrips() {
            try {
                const response = await fetch('../api/manager_trips.php', {
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    activeTrips = data.trips || [];
                    allTrips = [...activeTrips]; 
                    displayTrips(activeTrips);
                    updateSummary(activeTrips);
                } else {
                    throw new Error(data.message || 'Failed to load trips');
                }
            } catch (error) {
                console.error('Error loading active trips:', error);
                document.getElementById('activeTripsContainer').innerHTML = `
                    <div class="no-trips">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error Loading Trips</h3>
                        <p>Unable to load your active trips. Please refresh the page.</p>
                    </div>
                `;
            }
        }

        async function loadActiveTrips() {
            const container = document.getElementById('activeTripsContainer');
            container.innerHTML = `
                <div class="no-trips">
                    <i class="fas fa-spinner fa-pulse"></i>
                    <h3>Loading your active trips...</h3>
                    <p>Please wait while we fetch your assigned trips.</p>
                </div>
            `;

            try {
                
                await fetchFullTrips();
            } catch (error) {
                console.error('Error loading trips:', error);
            } finally {
                
                if (typeof window.markTripsDataLoaded === 'function') {
                    window.markTripsDataLoaded();
                }
            }
        }

        function displayTrips(trips) {
            const container = document.getElementById('activeTripsContainer');
            
            if (!trips || trips.length === 0) {
                container.innerHTML = `
                    <div class="no-trips">
                        <i class="fas fa-truck"></i>
                        <h3>No Active Trips</h3>
                        <p>You don't have any active trips assigned at the moment.</p>
                    </div>
                `;
                return;
            }
            
            const tripsHtml = trips.map(trip => `
                <div class="manager-trip-card" data-trip-id="${trip.id}" data-status="${trip.trip_status}" data-date="${trip.trip_date}">
                    <div class="trip-header">
                        <div>
                            <div class="trip-id">Trip ID: ${trip.id.substring(0, 8)}...</div>
                            <div class="trip-route">
                                <strong>${trip.origin_name || 'Origin'}</strong>
                                <i class="fas fa-arrow-right route-arrow"></i>
                                <strong>${trip.destination_name || 'Destination'}</strong>
                            </div>
                        </div>
                        <span class="trip-status status-${trip.trip_status}">${trip.trip_status.replace('_', ' ')}</span>
                    </div>
                    
                    <div class="trip-details">
                        <div>
                            <strong><i class="fas fa-calendar"></i> Trip Date</strong>
                            ${new Date(trip.trip_date).toLocaleDateString()}
                        </div>
                        <div>
                            <strong><i class="fas fa-clock"></i> Departure</strong>
                            ${trip.departure_time ? new Date(trip.departure_time).toLocaleTimeString() : 'Not set'}
                        </div>
                        <div>
                            <strong><i class="fas fa-user"></i> Driver</strong>
                            ${trip.driver_name || 'Not assigned'}
                        </div>
                        <div>
                            <strong><i class="fas fa-truck"></i> Vehicle</strong>
                            ${trip.vehicle_name || 'Not assigned'}
                        </div>
                    </div>
                    
                    <div class="trip-actions">
                        ${['scheduled','accepted'].includes(trip.trip_status) ? `
                            <button class="action-btn btn-accept" onclick="managerStartTrip('${trip.id}')">
                                <i class="fas fa-play"></i> Start Trip
                            </button>
                        ` : ''}
                        
                        ${['accepted', 'in_transit'].includes(trip.trip_status) ? `
                            <button class="action-btn btn-track" onclick="trackTrip('${trip.id}')">
                                <i class="fas fa-map-marker-alt"></i> Live Track
                            </button>
                        ` : ''}
                        
                        ${trip.trip_status === 'at_outlet' ? `
                            <button class="action-btn btn-complete" onclick="completeTrip('${trip.id}')">
                                <i class="fas fa-flag-checkered"></i> Mark Complete
                            </button>
                        ` : ''}
                        
                        <button class="action-btn btn-details" onclick="viewTripDetails('${trip.id}')">
                            <i class="fas fa-eye"></i> Details
                        </button>
                    </div>

                    <div class="trip-stops-container"></div>
                </div>
            `).join('');
            
            container.innerHTML = tripsHtml;
            
            // OPTIMIZATION: Auto-render stops for in_transit and at_outlet trips
            // Use setTimeout to ensure DOM is fully updated
            setTimeout(() => {
                trips.forEach(trip => {
                    console.log('Trip:', trip.id, 'Status:', trip.trip_status, 'Stops:', trip.stops?.length || 0);
                    if (['in_transit', 'at_outlet'].includes(trip.trip_status)) {
                        if (trip.stops && trip.stops.length > 0) {
                            console.log('Rendering stops for trip:', trip.id);
                            renderStopsUI(trip.id, trip.stops);
                        } else {
                            console.log('No stops data for trip:', trip.id, '- fetching from API');
                            renderTripStops(trip.id);
                        }
                    }
                });
            }, 100);
        }

        function filterTrips() {
            const statusFilter = document.getElementById('statusFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            
            let filteredTrips = [...allTrips];
            
            if (statusFilter) {
                filteredTrips = filteredTrips.filter(trip => trip.trip_status === statusFilter);
            }
            
            if (dateFilter) {
                filteredTrips = filteredTrips.filter(trip => {
                    const tripDate = new Date(trip.trip_date).toISOString().split('T')[0];
                    return tripDate === dateFilter;
                });
            }
            
            displayTrips(filteredTrips);
            updateSummary(filteredTrips);
        }
        

        
        async function managerStartTrip(tripId) {
            console.log('managerStartTrip called', tripId);
            if (!confirm('Start this trip now?')) return;
            
            const startCard = document.querySelector(`.manager-trip-card[data-trip-id="${tripId}"]`);
            const startBtn = startCard ? startCard.querySelector('.btn-accept') : null;
            
            try {
                const url = '../api/manager_start_trip.php';
                if (startBtn) startBtn.classList.add('loading');

                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ trip_id: tripId, action: 'start' })
                });
                const data = await resp.json();
                if (startBtn) startBtn.classList.remove('loading');
                if (data.success) {
                    
                    const trip = activeTrips.find(t => t.id === tripId);
                    if (trip) {
                        trip.trip_status = 'in_transit';
                        trip.departure_time = data.departure_time || new Date().toISOString();
                        
                        await renderTripStops(tripId);
                    }
                    alert('Trip started successfully');
                } else {
                    alert('Error starting trip: ' + (data.error || data.message || 'Unknown'));
                }
            } catch (err) {
                console.error('Error starting trip:', err);
                if (startBtn) startBtn.classList.remove('loading');
                alert('Failed to start trip. See console for details.');
            }
        }

        
        const COMPANY_ID = '<?php echo $_SESSION['company_id'] ?? ''; ?>';

        async function renderTripStops(tripId) {
            try {
                // OPTIMIZATION: Use embedded stops data from main API response
                const trip = activeTrips.find(t => t.id === tripId);
                if (!trip || !trip.stops) {
                    // Fallback to API call if data not available
                    const resp = await fetch(`../api/trips/get_trip_route_for_manager.php?trip_id=${encodeURIComponent(tripId)}`, { credentials: 'same-origin' });
                    const data = await resp.json();
                    if (!data.success) return;
                    
                    // Cache the stops data
                    if (trip) {
                        trip.stops = data.stops || [];
                    }
                    
                    renderStopsUI(tripId, data.stops || []);
                } else {
                    // Use cached stops data - no API call needed!
                    renderStopsUI(tripId, trip.stops);
                }
            } catch (err) {
                console.error('Failed to render trip stops', err);
            }
        }
        
        function renderStopsUI(tripId, stops) {
            const card = document.querySelector(`.manager-trip-card[data-trip-id="${tripId}"]`);
            let containerEl = null;
            if (card) {
                containerEl = card.querySelector('.trip-stops-container');
            }

            if (!containerEl) {
                console.error('No stops container found for trip:', tripId);
                return;
            }
            
            if (!stops || stops.length === 0) {
                console.warn('No stops to render for trip:', tripId);
                // Fallback: Render origin and destination from trip object
                const trip = activeTrips.find(t => t.id === tripId);
                if (trip && trip.origin_outlet_name && trip.destination_outlet_name) {
                    console.log('Rendering origin and destination as fallback stops for trip:', tripId);
                    const fallbackStops = [
                        {
                            id: null,
                            outlet_id: trip.origin_outlet_id,
                            outlet_name: trip.origin_outlet_name,
                            stop_order: 1,
                            arrival_time: null,
                            departure_time: trip.trip_status !== 'scheduled' ? trip.departure_time : null
                        },
                        {
                            id: null,
                            outlet_id: trip.destination_outlet_id,
                            outlet_name: trip.destination_outlet_name,
                            stop_order: 2,
                            arrival_time: trip.trip_status === 'completed' ? trip.arrival_time : null,
                            departure_time: null
                        }
                    ];
                    stops = trip.origin_outlet_id !== trip.destination_outlet_id ? fallbackStops : [fallbackStops[0]];
                } else {
                    containerEl.innerHTML = '<div style="padding:1rem;color:#6b7280;">No stops configured for this trip</div>';
                    return;
                }
            }

            console.log('Rendering', stops.length, 'stops for trip:', tripId);

            const stopsHtml = (stops || []).map(s => `
                <div id="stop-${s.id}" class="trip-stop-card">
                    <div>
                        <div style="font-weight:700; color:#374151; margin-bottom:0.25rem;">
                            ${s.stop_order}. ${s.outlet_name}
                        </div>
                        <div style="font-size:0.875rem; color:#6b7280;">
                            Stop ${s.stop_order}${s.parcel_count ? ' • ' + s.parcel_count + ' parcels' : ''}
                        </div>
                    </div>
                    <div style="display:flex; gap:0.75rem; align-items:center;">
                        ${s.arrival_time ? `<span style="font-size:0.875rem; color:#10b981; font-weight:600;"><i class="fas fa-check-circle"></i> Arrived: ${new Date(s.arrival_time).toLocaleTimeString()}</span>` : ''}
                        ${s.departure_time ? `<span style="font-size:0.875rem; color:#3b82f6; font-weight:600;"><i class="fas fa-sign-out-alt"></i> Departed: ${new Date(s.departure_time).toLocaleTimeString()}</span>` : ''}
                        ${s.id && !s.arrival_time ? `<button class="action-btn btn-track" onclick="managerArriveStop('${tripId}','${s.id}','${s.outlet_id}')"><i class="fas fa-map-marker-alt"></i> Arrive</button>` : ''}
                        ${s.id && s.arrival_time && !s.departure_time ? `<button class="action-btn btn-accept" onclick="managerDepartStop('${tripId}','${s.id}','${s.outlet_id}')"><i class="fas fa-route"></i> Depart</button>` : ''}
                        ${!s.id && !s.arrival_time ? `<button class="action-btn btn-track" onclick="managerArriveOutlet('${tripId}','${s.outlet_id}')"><i class="fas fa-map-marker-alt"></i> Arrive</button>` : ''}
                        ${!s.id && s.arrival_time && !s.departure_time ? `<button class="action-btn btn-accept" onclick="managerDepartOutlet('${tripId}','${s.outlet_id}')"><i class="fas fa-route"></i> Depart</button>` : ''}
                    </div>
                </div>
            `).join('');

            containerEl.innerHTML = stopsHtml;
            console.log('Successfully rendered stops for trip:', tripId);
        }

        
        async function managerArriveStop(tripId, stopId, outletId) {
            if (!confirm('Mark arrived at this stop?')) return;
            try {
                const payload = { stop_id: stopId, action: 'arrive', timestamp: new Date().toISOString() };
                
                const card = document.querySelector(`.manager-trip-card[data-trip-id="${tripId}"]`);
                const stopEl = card ? card.querySelector(`#stop-${stopId}`) : null;
                const btn = stopEl ? stopEl.querySelector('button') : null;
                if (btn) btn.classList.add('loading');

                const resp = await fetch('../api/manager_update_trip_stop.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                const data = await resp.json();
                if (data.success) {
                    
                    if (btn) btn.classList.remove('loading');
                    if (data.trip_completed) {
                        const cardEl = document.querySelector(`.manager-trip-card[data-trip-id="${tripId}"]`);
                        if (cardEl) {
                            cardEl.classList.add('fade-out');
                            setTimeout(() => cardEl.remove(), 350);
                        }
                    } else {
                        
                        await renderTripStops(tripId);
                    }
                } else {
                    if (btn) btn.classList.remove('loading');
                    alert('Error: ' + (data.error || 'Failed to mark arrival'));
                }
            } catch (e) {
                
                try { const card = document.querySelector(`.manager-trip-card[data-trip-id="${tripId}"]`); const stopEl = card ? card.querySelector(`#stop-${stopId}`) : null; const btn = stopEl ? stopEl.querySelector('button') : null; if (btn) btn.classList.remove('loading'); } catch (ignore) {}
                console.error(e); alert('Failed to mark arrival');
            }
        }

        async function managerDepartStop(tripId, stopId, outletId) {
            if (!confirm('Mark departed from this stop?')) return;
            try {
                const payload = { stop_id: stopId, action: 'depart', timestamp: new Date().toISOString() };
                
                const card = document.querySelector(`.manager-trip-card[data-trip-id="${tripId}"]`);
                const stopEl = card ? card.querySelector(`#stop-${stopId}`) : null;
                const btn = stopEl ? stopEl.querySelector('button') : null;
                if (btn) btn.classList.add('loading');

                const resp = await fetch('../api/manager_update_trip_stop.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                const data = await resp.json();
                if (btn) btn.classList.remove('loading');
                if (data.success) {
                    
                    if (data.trip_completed) {
                        const cardEl = document.querySelector(`.manager-trip-card[data-trip-id="${tripId}"]`);
                        if (cardEl) {
                            cardEl.classList.add('fade-out');
                            setTimeout(() => cardEl.remove(), 350);
                        }
                    } else {
                        
                        await renderTripStops(tripId);
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to mark depart'));
                }
            } catch (e) {
                try { const card = document.querySelector(`.manager-trip-card[data-trip-id="${tripId}"]`); const stopEl = card ? card.querySelector(`#stop-${stopId}`) : null; const btn = stopEl ? stopEl.querySelector('button') : null; if (btn) btn.classList.remove('loading'); } catch (ignore) {}
                console.error(e); alert('Failed to mark depart');
            }
        }

        // Direct outlet tracking (for origin/destination without trip_stops)
        async function managerArriveOutlet(tripId, outletId) {
            if (!confirm('Mark arrived at this outlet?')) return;
            try {
                const payload = { 
                    outlet_id: outletId, 
                    trip_id: tripId, 
                    action: 'arrive', 
                    timestamp: new Date().toISOString() 
                };
                
                const resp = await fetch('../api/manager_update_trip_stop.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                
                const data = await resp.json();
                if (data.success) {
                    alert('Arrival recorded successfully');
                    await loadManagerTrips();
                } else {
                    alert('Error: ' + (data.error || 'Failed to record arrival'));
                }
            } catch (err) {
                console.error('Arrive outlet error', err);
                alert('Failed to mark arrival: ' + err.message);
            }
        }

        async function managerDepartOutlet(tripId, outletId) {
            if (!confirm('Mark departed from this outlet?')) return;
            try {
                const payload = { 
                    outlet_id: outletId, 
                    trip_id: tripId, 
                    action: 'depart', 
                    timestamp: new Date().toISOString() 
                };
                
                const resp = await fetch('../api/manager_update_trip_stop.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                
                const data = await resp.json();
                if (data.success) {
                    alert('Departure recorded successfully');
                    await loadManagerTrips();
                } else {
                    alert('Error: ' + (data.error || 'Failed to record departure'));
                }
            } catch (err) {
                console.error('Depart outlet error', err);
                alert('Failed to mark departure: ' + err.message);
            }
        }

        async function completeTrip(tripId) {
            const notes = prompt('Add completion notes (optional):');
            if (notes === null) return; 
            
            if (!confirm('Mark this trip as completed?')) return;
            
            try {
                const response = await fetch('../api/manager_complete_trip.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ 
                        trip_id: tripId,
                        notes: notes || ''
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Trip completed successfully!');
                    loadActiveTrips(); 
                } else {
                    alert('Error: ' + (data.message || 'Failed to complete trip'));
                }
            } catch (error) {
                console.error('Error completing trip:', error);
                alert('Failed to complete trip. Please try again.');
            }
        }

        function trackTrip(tripId) {
            console.log('trackTrip opening window for', tripId);
            // open dedicated tracking page in new tab/window
            const url = `manager_trip_tracking.php?trip_id=${encodeURIComponent(tripId)}`;
            window.open(url, '_blank');
        }

        async function loadTripTracking(tripId) {
            try {
                const response = await fetch(`../api/trip_tracking.php?trip_id=${tripId}`, {
                    credentials: 'same-origin'
                });
                
                const data = await response.json();
                
                if (data.success && data.locations) {
                    
                    trackingMap.eachLayer(layer => {
                        if (layer instanceof L.Marker) {
                            trackingMap.removeLayer(layer);
                        }
                    });
                    
                    
                    const pathCoords = [];
                data.locations.forEach(location => {
                        L.marker([location.latitude, location.longitude])
                            .addTo(trackingMap)
                            .bindPopup(`Driver location at ${new Date(location.timestamp).toLocaleTimeString()}`);
                        pathCoords.push([location.latitude, location.longitude]);
                    });
                    
                    // draw polyline showing the route so far
                    if (typeof trackingPolyline !== 'undefined' && trackingPolyline) {
                        trackingMap.removeLayer(trackingPolyline);
                    }
                    if (pathCoords.length > 1) {
                        trackingPolyline = L.polyline(pathCoords, {
                            color: '#3b82f6',
                            weight: 3,
                            opacity: 0.7
                        }).addTo(trackingMap);
                    }
                    
                    if (data.locations.length > 0) {
                        const group = new L.featureGroup(
                            data.locations.map(loc => L.marker([loc.latitude, loc.longitude]))
                        );
                        trackingMap.fitBounds(group.getBounds().pad(0.1));
                    }
                }
            } catch (error) {
                console.error('Error loading tracking data:', error);
            }
        }

        function closeLiveTracking() {
            document.getElementById('liveTrackingSection').style.display = 'none';
            currentTrackingTrip = null;
            if (trackingInterval) {
                clearInterval(trackingInterval);
                trackingInterval = null;
            }
        }

        async function completeTrip(tripId) {
            if (!confirm('Mark this trip as completed?')) return;
            
            try {
                const response = await fetch('../api/manager_complete_trip.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ trip_id: tripId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Trip marked as completed!');
                    loadActiveTrips(); 
                } else {
                    alert('Error: ' + (data.message || 'Failed to complete trip'));
                }
            } catch (error) {
                console.error('Error completing trip:', error);
                alert('Failed to complete trip. Please try again.');
            }
        }

        function viewTripDetails(tripId) {
            
            window.open(`trip_details.php?id=${tripId}`, '_blank');
        }

        
        setInterval(() => {
            if (currentTrackingTrip) {
                loadTripTracking(currentTrackingTrip);
            }
        }, 30000);

        
        document.addEventListener('DOMContentLoaded', () => {
            loadActiveTrips();
            
            
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dateFilter').value = today;
        });
    </script>

    <!-- Loading Overlay Control Script -->
    <script>
        (function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            const startTime = Date.now();
            const minimumLoadTime = 600; 
            let pageLoaded = false;
            let tripsLoaded = false;
            
            
            window.markTripsDataLoaded = function() {
                console.log('[Loading Overlay] Trips data loaded');
                tripsLoaded = true;
                checkAndHideLoading();
            };
            
            
            function hideLoadingOverlay() {
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, minimumLoadTime - elapsedTime);
                
                console.log('[Loading Overlay] Hiding in ' + remainingTime + 'ms');
                
                
                setTimeout(() => {
                    loadingOverlay.classList.add('hidden');
                    
                    
                    setTimeout(() => {
                        if (loadingOverlay && loadingOverlay.parentNode) {
                            loadingOverlay.parentNode.removeChild(loadingOverlay);
                        }
                    }, 500);
                }, remainingTime + 200); 
            }
            
            
            function checkAndHideLoading() {
                if (pageLoaded && tripsLoaded) {
                    console.log('[Loading Overlay] Both page and trips ready, hiding overlay');
                    hideLoadingOverlay();
                }
            }
            
            
            function markPageLoaded() {
                if (!pageLoaded) {
                    console.log('[Loading Overlay] Page DOM loaded');
                    pageLoaded = true;
                    
                    
                    setTimeout(() => {
                        if (!tripsLoaded) {
                            console.log('[Loading Overlay] Trips taking too long, forcing hide');
                            tripsLoaded = true;
                            checkAndHideLoading();
                        }
                    }, 1500);
                    
                    checkAndHideLoading();
                }
            }
            
            
            if (document.readyState === 'complete') {
                markPageLoaded();
            } else {
                window.addEventListener('load', markPageLoaded);
            }
            
            
            setTimeout(() => {
                if (!pageLoaded || !tripsLoaded) {
                    console.log('[Loading Overlay] Timeout reached, forcing hide. Page:', pageLoaded, 'Trips:', tripsLoaded);
                    pageLoaded = true;
                    tripsLoaded = true;
                    hideLoadingOverlay();
                }
            }, 4000);
            
            
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
<script src="../assets/js/sidebar-toggle.js?v=<?php echo time(); ?>" defer></script>
</html>