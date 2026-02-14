<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../../login.php');
    exit();
}

$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routes - Driver App</title>
    <link rel="stylesheet" href="../assets/css/driver-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="manifest" href="../manifest.json">
    <style>
        .route-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        #liveMap {
            width: 100%;
            height: 500px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            z-index: 1;
        }
        
        .map-controls {
            position: absolute;
            top: 80px;
            right: 30px;
            z-index: 1000;
            background: white;
            border-radius: 10px;
            padding: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .map-control-btn {
            display: block;
            width: 40px;
            height: 40px;
            margin: 5px 0;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s ease;
        }
        
        .map-control-btn:hover {
            transform: scale(1.1);
        }
        
        .location-status {
            position: absolute;
            top: 80px;
            left: 30px;
            z-index: 1000;
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .status-text {
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .leaflet-popup-content-wrapper {
            border-radius: 10px;
        }
        
        .driver-popup {
            text-align: center;
        }
        
        .driver-popup h3 {
            margin: 0 0 5px 0;
            color: #667eea;
        }
        
        .driver-popup p {
            margin: 5px 0;
            font-size: 13px;
            color: #4a5568;
        }
        
        .route-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            color: white;
        }
        
        .route-title {
            font-size: 24px;
            font-weight: 700;
        }
        
        .route-actions {
            display: flex;
            gap: 12px;
        }
        
        .action-btn {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .action-btn.primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .action-btn.primary:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .route-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .route-tab {
            padding: 12px 20px;
            border: none;
            background: transparent;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .route-tab.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }
        
        .route-content {
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        
        .route-list {
            padding: 0;
        }
        
        .route-item {
            padding: 20px;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s;
        }
        
        .route-item:last-child {
            border-bottom: none;
        }
        
        .route-item:hover {
            background: #f9fafb;
        }
        
        .route-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .route-info {
            flex: 1;
        }
        
        .route-code {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .route-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-assigned { background: #dbeafe; color: #1e40af; }
        .status-in_progress { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .route-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .route-detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .detail-value {
            font-size: 14px;
            color: #1f2937;
            font-weight: 600;
        }
        
        .route-path {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 16px 0;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .outlet-point {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        
        .outlet-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
        }
        
        .origin-icon {
            background: #10b981;
        }
        
        .destination-icon {
            background: #ef4444;
        }
        
        .route-arrow {
            color: #6b7280;
            font-size: 18px;
        }
        
        .route-actions-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .route-btn {
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            color: #374151;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .route-btn:hover {
            border-color: #2563eb;
            color: #2563eb;
        }
        
        .route-btn.primary {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }
        
        .route-btn.primary:hover {
            background: #1d4ed8;
        }
        
        .route-btn.success {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        
        .route-btn.success:hover {
            background: #059669;
        }
        
        .route-btn.warning {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }
        
        .route-btn.warning:hover {
            background: #d97706;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
            color: #d1d5db;
        }
        
        .empty-state h3 {
            margin-bottom: 8px;
            color: #374151;
        }
        
        .loading-placeholder {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #f3f4f6;
            border-top: 3px solid #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .route-map {
            margin-top: 16px;
            height: 200px;
            background: #f3f4f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-style: italic;
        }
        
        .route-statistics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6b7280;
        }
        
        .route-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .route-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
        }
        
        .close-btn:hover {
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="driver-app">
        <?php include '../includes/navbar.php'; ?>

        <!-- Live Location Map -->
        <div class="location-status">
            <div class="status-indicator"></div>
            <span class="status-text" id="locationStatus">Tracking Location...</span>
        </div>
        
        <div class="map-controls">
            <button class="map-control-btn" onclick="centerOnDriver()" title="Center on my location">
                <i class="fas fa-crosshairs"></i>
            </button>
            <button class="map-control-btn" onclick="toggleTracking()" title="Toggle tracking" id="trackingBtn">
                <i class="fas fa-pause"></i>
            </button>
            <button class="map-control-btn" onclick="refreshMap()" title="Refresh map">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>

        <div class="route-container">
            <!-- Live Map Container -->
            <div id="liveMap"></div>

            <!-- Route Header -->
            <div class="route-header">
                <div>
                    <div class="route-title">Today's Routes</div>
                    <div style="font-size: 14px; opacity: 0.9; margin-top: 4px;">
                        Manage your delivery routes and optimize your schedule
                    </div>
                </div>
                <div class="route-actions">
                    <button class="action-btn primary" onclick="showCurrentLocation()">
                        <i class="fas fa-map-marker-alt"></i>
                        My Location
                    </button>
                    <button class="action-btn primary" onclick="exportRoute()">
                        <i class="fas fa-download"></i>
                        Export Route
                    </button>
                </div>
            </div>

            <!-- Route Statistics -->
            <div class="route-statistics">
                <div class="stat-card">
                    <div class="stat-value" id="totalRoutes">--</div>
                    <div class="stat-label">Total Routes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="activeRoutes">--</div>
                    <div class="stat-label">Active Routes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="totalDistance">--</div>
                    <div class="stat-label">Total Distance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="estimatedTime">--</div>
                    <div class="stat-label">Est. Time</div>
                </div>
            </div>

            <!-- Route Tabs -->
            <div class="route-tabs">
                <button class="route-tab active" data-status="all" onclick="filterRoutes('all')">
                    All Routes
                </button>
                <button class="route-tab" data-status="assigned" onclick="filterRoutes('assigned')">
                    Assigned
                </button>
                <button class="route-tab" data-status="in_progress" onclick="filterRoutes('in_progress')">
                    In Progress
                </button>
                <button class="route-tab" data-status="completed" onclick="filterRoutes('completed')">
                    Completed
                </button>
            </div>

            <!-- Route Content -->
            <div class="route-content">
                <div class="route-list" id="routeList">
                    <!-- Loading placeholder -->
                    <div class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p>Loading routes...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Route Details Modal -->
        <div class="route-modal" id="routeModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Route Details</h3>
                    <button class="close-btn" onclick="closeModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" id="routeModalBody">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Bottom Navigation matching outlet-app -->
        <nav class="bottom-nav">
            <a href="../dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="../pickups.php" class="nav-item">
                <i class="fas fa-box"></i>
                <span>Pickups</span>
            </a>
            <a href="../deliveries.php" class="nav-item">
                <i class="fas fa-truck"></i>
                <span>Deliveries</span>
            </a>
            <a href="route.php" class="nav-item active">
                <i class="fas fa-route"></i>
                <span>Routes</span>
            </a>
            <a href="../profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </nav>
    </div>

    <script>
        class RouteManager {
            constructor() {
                this.currentFilter = 'all';
                this.routesData = [];
                this.init();
            }

            init() {
                this.loadRoutes();
                this.loadStatistics();
                
                
                setInterval(() => {
                    this.loadRoutes();
                    this.loadStatistics();
                }, 60000);
            }

            async loadRoutes() {
                try {
                    const response = await fetch('../api/driver-routes.php');
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const data = await response.json();

                    if (data.success) {
                        this.routesData = data.data;
                        this.renderRoutes();
                    } else {
                        this.showError(data.error || 'Failed to load routes');
                    }
                } catch (error) {
                    console.error('Error loading routes:', error);
                    this.showError('Connection error. Please check your internet connection.');
                }
            }

            async loadStatistics() {
                try {
                    const response = await fetch('../api/driver-route-stats.php');
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const data = await response.json();

                    if (data.success) {
                        this.updateStatistics(data.data);
                    }
                } catch (error) {
                    console.error('Error loading statistics:', error);
                }
            }

            updateStatistics(stats) {
                document.getElementById('totalRoutes').textContent = stats.total_routes || 0;
                document.getElementById('activeRoutes').textContent = stats.active_routes || 0;
                document.getElementById('totalDistance').textContent = (stats.total_distance || 0) + ' km';
                document.getElementById('estimatedTime').textContent = (stats.estimated_time || 0) + ' hrs';
            }

            async renderRoutes() {
                const container = document.getElementById('routeList');
                let filteredRoutes = this.filterRoutesByStatus();

                if (filteredRoutes.length === 0) {
                    container.innerHTML = this.getEmptyState();
                    return;
                }

                
                const routeCards = await Promise.all(filteredRoutes.map(async route => {
                    let routeNames = '-';
                    try {
                        const res = await fetch(`../../api/fetch_trip_route.php?trip_id=${route.trip_id}&company_id=${route.company_id}`);
                        const data = await res.json();
                        if (data.success && data.route) {
                            routeNames = data.route;
                        }
                    } catch (e) {}
                    return this.createRouteCard({ ...route, routeNames });
                }));
                container.innerHTML = routeCards.join('');
            }

            filterRoutesByStatus() {
                if (this.currentFilter === 'all') {
                    return this.routesData;
                }
                return this.routesData.filter(route => route.status === this.currentFilter);
            }

            createRouteCard(route) {
                return `
                    <div class="route-item" data-route-id="${route.trip_id}">
                        <div class="route-item-header">
                            <div class="route-info">
                                <div class="route-code">${route.trip_code}</div>
                                <span class="route-status status-${route.status}">
                                    ${route.status.replace('_', ' ')}
                                </span>
                            </div>
                        </div>
                        <div class="route-details">
                            <div class="route-detail-item">
                                <div class="detail-label">Parcels</div>
                                <div class="detail-value">${route.parcel_count || 0} items</div>
                            </div>
                            <div class="route-detail-item">
                                <div class="detail-label">Distance</div>
                                <div class="detail-value">${route.estimated_distance || 'N/A'}</div>
                            </div>
                            <div class="route-detail-item">
                                <div class="detail-label">Duration</div>
                                <div class="detail-value">${route.estimated_duration || 'N/A'}</div>
                            </div>
                            <div class="route-detail-item">
                                <div class="detail-label">Priority</div>
                                <div class="detail-value">${route.priority || 'Normal'}</div>
                            </div>
                        </div>
                        <div class="route-path">
                            <div class="outlet-point">
                                <div class="outlet-icon origin-icon">
                                    <i class="fas fa-play"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">${route.origin_outlet_name}</div>
                                    <div style="font-size: 12px; color: #6b7280;">${route.origin_location || 'Origin'}</div>
                                </div>
                            </div>
                            <div class="route-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                            <div class="outlet-point">
                                <div class="outlet-icon destination-icon">
                                    <i class="fas fa-flag"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">${route.destination_outlet_name}</div>
                                    <div style="font-size: 12px; color: #6b7280;">${route.destination_location || 'Destination'}</div>
                                </div>
                            </div>
                        </div>
                        <div class="route-detail-item">
                            <div class="detail-label">Route</div>
                            <div class="detail-value">${route.routeNames || '-'}</div>
                        </div>
                        <div class="route-actions-list">
                            ${this.getRouteActions(route)}
                        </div>
                        <div class="route-map">
                            <i class="fas fa-map-marked-alt"></i>
                            Map integration coming soon
                        </div>
                    </div>
                `;
            }

            getRouteActions(route) {
                switch (route.status) {
                    case 'assigned':
                        return `
                            <button class="route-btn primary" onclick="startRoute('${route.trip_id}')">
                                <i class="fas fa-play"></i> Start Route
                            </button>
                            <button class="route-btn" onclick="viewRouteDetails('${route.trip_id}')">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button class="route-btn" onclick="optimizeRoute('${route.trip_id}')">
                                <i class="fas fa-magic"></i> Optimize
                            </button>
                        `;
                    case 'in_progress':
                        return `
                            <button class="route-btn warning" onclick="pauseRoute('${route.trip_id}')">
                                <i class="fas fa-pause"></i> Pause Route
                            </button>
                            <button class="route-btn success" onclick="completeRoute('${route.trip_id}')">
                                <i class="fas fa-check"></i> Complete Route
                            </button>
                            <button class="route-btn" onclick="viewRouteDetails('${route.trip_id}')">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        `;
                    case 'completed':
                        return `
                            <button class="route-btn" onclick="viewRouteDetails('${route.trip_id}')">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button class="route-btn" onclick="downloadRouteReport('${route.trip_id}')">
                                <i class="fas fa-download"></i> Report
                            </button>
                        `;
                    default:
                        return `
                            <button class="route-btn" onclick="viewRouteDetails('${route.trip_id}')">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        `;
                }
            }

            filterRoutes(status) {
                this.currentFilter = status;
                
                
                document.querySelectorAll('.route-tab').forEach(tab => {
                    tab.classList.remove('active');
                    if (tab.dataset.status === status) {
                        tab.classList.add('active');
                    }
                });
                
                this.renderRoutes();
            }

            showError(message) {
                const container = document.getElementById('routeList');
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error Loading Routes</h3>
                        <p>${message}</p>
                        <button class="route-btn primary" onclick="refreshRoutes()">
                            <i class="fas fa-sync-alt"></i> Retry
                        </button>
                    </div>
                `;
            }

            getEmptyState() {
                return `
                    <div class="empty-state">
                        <i class="fas fa-route"></i>
                        <h3>No Routes Found</h3>
                        <p>No routes available for the selected filter.</p>
                        <button class="route-btn primary" onclick="refreshRoutes()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                `;
            }

            async showRouteDetails(tripId) {
                const route = this.routesData.find(r => r.trip_id === tripId);
                if (!route) return;

                const modalBody = document.getElementById('routeModalBody');
                modalBody.innerHTML = `
                    <div class="route-detail-full">
                        <div class="detail-section">
                            <h4>Route Information</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Trip Code:</label>
                                    <span>${route.trip_code}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Status:</label>
                                    <span class="route-status status-${route.status}">${route.status}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Priority:</label>
                                    <span>${route.priority || 'Normal'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Parcels:</label>
                                    <span>${route.parcel_count || 0} items</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Route Path</h4>
                            <div class="route-path">
                                <div class="outlet-point">
                                    <div class="outlet-icon origin-icon">
                                        <i class="fas fa-play"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;">${route.origin_outlet_name}</div>
                                        <div style="font-size: 12px; color: #6b7280;">${route.origin_location || 'Origin'}</div>
                                    </div>
                                </div>
                                <div class="route-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                                <div class="outlet-point">
                                    <div class="outlet-icon destination-icon">
                                        <i class="fas fa-flag"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;">${route.destination_outlet_name}</div>
                                        <div style="font-size: 12px; color: #6b7280;">${route.destination_location || 'Destination'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-actions" style="margin-top: 20px;">
                            ${this.getRouteActions(route)}
                        </div>
                    </div>
                `;

                document.getElementById('routeModal').classList.add('active');
            }
        }

        
        const routeManager = new RouteManager();

        
        function refreshRoutes() {
            routeManager.loadRoutes();
            routeManager.loadStatistics();
        }

        function filterRoutes(status) {
            routeManager.filterRoutes(status);
        }

        function viewRouteDetails(tripId) {
            routeManager.showRouteDetails(tripId);
        }

        function closeModal() {
            document.getElementById('routeModal').classList.remove('active');
        }

        async function startRoute(tripId) {
            if (!confirm('Start this route? This will notify the manager and all customers.')) return;
            
            try {
                
                const response = await fetch('../api/trips/start_trip.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        trip_id: tripId
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    
                    const managerCount = data.notifications?.manager_notifications?.length || 0;
                    const customerCount = data.notifications?.customer_notifications?.length || 0;
                    const totalNotifications = managerCount + customerCount;
                    
                    showNotification(
                        `Route started! ${totalNotifications} notifications sent (${managerCount} manager, ${customerCount} customer).`,
                        'success'
                    );
                    refreshRoutes();
                } else {
                    showNotification(data.error || 'Failed to start route', 'error');
                }
            } catch (error) {
                console.error('Error starting route:', error);
                showNotification('Failed to start route', 'error');
            }
        }

        async function arriveAtStop(stopId, tripId, outletId, button) {
            if (!confirm('Mark arrival at this stop? This will notify the manager and customers.')) return;
            
            const originalText = button ? button.textContent : 'Arrive';
            if (button) {
                button.disabled = true;
                button.textContent = 'Recording...';
            }
            
            try {
                
                const arrivalResponse = await fetch('../api/arrive_at_stop_simple.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        stop_id: stopId,
                        trip_id: tripId,
                        outlet_id: outletId
                    })
                });
                
                const arrivalResult = await arrivalResponse.json();
                
                if (!arrivalResult.success) {
                    throw new Error(arrivalResult.error || 'Failed to record arrival');
                }
                
                
                const notifyResponse = await fetch('../api/trips/arrive_at_outlet.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({trip_id: tripId, outlet_id: outletId})
                });
                
                const notifyResult = await notifyResponse.json();
                
                if (notifyResult.success) {
                    const notifCount = notifyResult.notifications_sent || 0;
                    showNotification(`Arrival recorded! ${notifCount} notifications sent.`, 'success');
                    
                    if (button) {
                        button.textContent = 'Arrived ✓';
                        button.classList.add('completed');
                        button.disabled = true;
                    }
                    
                    
                    viewRouteDetails(tripId);
                } else {
                    showNotification('Arrival recorded (notifications may have failed)', 'warning');
                }
                
            } catch (error) {
                console.error('Error recording arrival:', error);
                if (button) {
                    button.textContent = originalText;
                    button.disabled = false;
                }
                showNotification('Failed to record arrival: ' + error.message, 'error');
            }
        }

        async function completeRoute(tripId) {
            if (!confirm('Mark this route as completed?')) return;
            
            try {
                const response = await fetch('../api/update-trip-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        trip_id: tripId,
                        status: 'completed'
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('Route completed successfully!', 'success');
                    refreshRoutes();
                } else {
                    showNotification(data.error || 'Failed to complete route', 'error');
                }
            } catch (error) {
                console.error('Error completing route:', error);
                showNotification('Failed to complete route', 'error');
            }
        }

        function pauseRoute(tripId) {
            showNotification('Pause route feature coming soon!', 'info');
        }

        function optimizeRoute(tripId) {
            showNotification('Route optimization feature coming soon!', 'info');
        }

        function optimizeAllRoutes() {
            showNotification('Optimize all routes feature coming soon!', 'info');
        }

        function showCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    showNotification(`Current location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`, 'success');
                }, () => {
                    showNotification('Unable to get your location', 'error');
                });
            } else {
                showNotification('Geolocation not supported', 'error');
            }
        }

        function showMapView() {
            showNotification('Map view feature coming soon!', 'info');
        }

        function downloadRouteReport(tripId) {
            showNotification('Route report download feature coming soon!', 'info');
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        
        document.getElementById('routeModal').addEventListener('click', (e) => {
            if (e.target.id === 'routeModal') {
                closeModal();
            }
        });
    </script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Live Location Map Script -->
    <script>
        let map;
        let driverMarker;
        let trackingEnabled = true;
        let watchId = null;
        let locationHistory = [];
        let routePolyline = null;

        
        const driverIcon = L.divIcon({
            html: `<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                   width: 40px; height: 40px; border-radius: 50%; 
                   display: flex; align-items: center; justify-content: center;
                   border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3);">
                   <i class="fas fa-car" style="color: white; font-size: 18px;"></i>
                   </div>`,
            className: 'driver-marker',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });

        
        function initializeMap() {
            
            const defaultCenter = [-15.4167, 28.2833]; 
            
            map = L.map('liveMap', {
                center: defaultCenter,
                zoom: 13,
                zoomControl: false
            });

            
            L.control.zoom({
                position: 'topright'
            }).addTo(map);

            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            
            startLocationTracking();
        }

        
        function startLocationTracking() {
            if (!navigator.geolocation) {
                updateLocationStatus('Geolocation not supported', false);
                return;
            }

            updateLocationStatus('Getting location...', true);

            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude, longitude, accuracy } = position.coords;
                    updateDriverLocation(latitude, longitude, accuracy);
                    updateLocationStatus('Location tracking active', true);
                },
                (error) => {
                    console.error('Error getting location:', error);
                    updateLocationStatus('Location access denied', false);
                }
            );

            
            if (trackingEnabled) {
                watchId = navigator.geolocation.watchPosition(
                    (position) => {
                        const { latitude, longitude, accuracy, speed, heading } = position.coords;
                        updateDriverLocation(latitude, longitude, accuracy, speed, heading);
                        
                        
                        saveLocationToDatabase(latitude, longitude, accuracy, speed, heading);
                    },
                    (error) => {
                        console.error('Error watching location:', error);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            }
        }

        
        function updateDriverLocation(lat, lng, accuracy, speed = null, heading = null) {
            if (!map) return;

            
            if (!driverMarker) {
                map.setView([lat, lng], 15);
            }

            
            if (driverMarker) {
                map.removeLayer(driverMarker);
            }

            
            driverMarker = L.marker([lat, lng], { icon: driverIcon }).addTo(map);
            
            
            let popupContent = `
                <div class="driver-popup">
                    <h3><i class="fas fa-map-marker-alt"></i> Your Location</h3>
                    <p><strong>Lat:</strong> ${lat.toFixed(6)}</p>
                    <p><strong>Lng:</strong> ${lng.toFixed(6)}</p>
                    <p><strong>Accuracy:</strong> ±${Math.round(accuracy)}m</p>
            `;
            
            if (speed !== null && speed > 0) {
                popupContent += `<p><strong>Speed:</strong> ${(speed * 3.6).toFixed(1)} km/h</p>`;
            }
            
            popupContent += `</div>`;
            
            driverMarker.bindPopup(popupContent);

            
            L.circle([lat, lng], {
                radius: accuracy,
                color: '#667eea',
                fillColor: '#667eea',
                fillOpacity: 0.1,
                weight: 1
            }).addTo(map);

            
            locationHistory.push([lat, lng]);
            
            
            if (locationHistory.length > 100) {
                locationHistory.shift();
            }

            
            if (routePolyline) {
                map.removeLayer(routePolyline);
            }
            
            if (locationHistory.length > 1) {
                routePolyline = L.polyline(locationHistory, {
                    color: '#667eea',
                    weight: 3,
                    opacity: 0.7
                }).addTo(map);
            }
        }

        
        async function saveLocationToDatabase(lat, lng, accuracy, speed, heading) {
            try {
                const response = await fetch('../api/save-driver-location.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        latitude: lat,
                        longitude: lng,
                        accuracy: accuracy,
                        speed: speed,
                        heading: heading,
                        timestamp: new Date().toISOString()
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                if (!data.success) {
                    console.error('Failed to save location:', data.error);
                }
            } catch (error) {
                console.error('Error saving location:', error);
            }
        }

        
        function updateLocationStatus(message, isActive) {
            const statusElement = document.getElementById('locationStatus');
            const indicator = document.querySelector('.status-indicator');
            
            if (statusElement) {
                statusElement.textContent = message;
            }
            
            if (indicator) {
                indicator.style.background = isActive ? '#10b981' : '#ef4444';
            }
        }

        
        function centerOnDriver() {
            if (driverMarker) {
                map.setView(driverMarker.getLatLng(), 15);
                driverMarker.openPopup();
            } else {
                showNotification('Location not available yet', 'warning');
            }
        }

        
        function toggleTracking() {
            trackingEnabled = !trackingEnabled;
            const btn = document.getElementById('trackingBtn');
            
            if (trackingEnabled) {
                btn.innerHTML = '<i class="fas fa-pause"></i>';
                startLocationTracking();
                updateLocationStatus('Location tracking active', true);
            } else {
                btn.innerHTML = '<i class="fas fa-play"></i>';
                if (watchId) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }
                updateLocationStatus('Location tracking paused', false);
            }
        }

        
        function refreshMap() {
            if (navigator.geolocation) {
                updateLocationStatus('Refreshing location...', true);
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const { latitude, longitude, accuracy } = position.coords;
                        updateDriverLocation(latitude, longitude, accuracy);
                        centerOnDriver();
                        updateLocationStatus('Location updated', true);
                        showNotification('Location refreshed', 'success');
                    },
                    (error) => {
                        updateLocationStatus('Refresh failed', false);
                        showNotification('Failed to refresh location', 'error');
                    }
                );
            }
        }

        
        function exportRoute() {
            if (locationHistory.length === 0) {
                showNotification('No route data to export', 'warning');
                return;
            }

            const routeData = {
                driver_id: '<?php echo $driverId; ?>',
                date: new Date().toISOString(),
                points: locationHistory,
                total_points: locationHistory.length
            };

            const dataStr = JSON.stringify(routeData, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = `route_${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            
            URL.revokeObjectURL(url);
            showNotification('Route exported successfully', 'success');
        }

        
        document.addEventListener('DOMContentLoaded', () => {
            initializeMap();
        });

        
        window.addEventListener('beforeunload', () => {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
        });
    </script>
    
    <?php include __DIR__ . '/../../includes/pwa_install_button.php'; ?>
    <script src="../../js/pwa-install.js"></script>
</body>
</html>