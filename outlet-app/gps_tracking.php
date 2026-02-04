<?php

require_once 'includes/auth_guard.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user = getCurrentUser();

$trip_id = $_GET['trip_id'] ?? '';
$driver_name = $_GET['driver_name'] ?? '';

if (empty($trip_id)) {
    header('Location: trips.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Tracking - <?php echo htmlspecialchars($driver_name); ?> - WD Parcel</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow: hidden;
            height: 100vh;
            width: 100vw;
        }

        #map {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 16px 20px;
            z-index: 1000;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-button {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f5f5f5;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .back-button:hover {
            background: #e5e5e5;
            transform: scale(1.05);
        }

        .tracking-title {
            font-size: 18px;
            font-weight: 600;
            color: #000;
        }

        .tracking-subtitle {
            font-size: 14px;
            color: #666;
        }

        .status-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            background: #10b981;
            color: white;
            font-size: 13px;
            font-weight: 500;
        }

        .status-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .info-card {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 24px 24px 0 0;
            box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.12);
            z-index: 1000;
            max-height: 50vh;
            transition: transform 0.3s ease;
        }

        .info-card.minimized {
            transform: translateY(calc(100% - 80px));
        }

        .drag-handle {
            width: 40px;
            height: 4px;
            background: #ddd;
            border-radius: 2px;
            margin: 12px auto;
            cursor: grab;
        }

        .info-content {
            padding: 0 20px 20px;
            overflow-y: auto;
            max-height: calc(50vh - 80px);
        }

        .driver-section {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .driver-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
        }

        .driver-info {
            flex: 1;
        }

        .driver-name {
            font-size: 17px;
            font-weight: 600;
            color: #000;
            margin-bottom: 4px;
        }

        .driver-vehicle {
            font-size: 14px;
            color: #666;
        }

        .route-section {
            padding: 20px 0;
        }

        .route-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .route-title {
            font-size: 15px;
            font-weight: 600;
            color: #000;
        }

        .route-eta {
            font-size: 14px;
            color: #10b981;
            font-weight: 600;
        }

        .progress-bar-container {
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 2px;
            transition: width 0.5s ease;
        }

        .route-stops {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .route-stop {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .stop-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .stop-icon.origin {
            background: #e0f2fe;
            color: #0284c7;
        }

        .stop-icon.destination {
            background: #dcfce7;
            color: #16a34a;
        }

        .stop-details {
            flex: 1;
        }

        .stop-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .stop-address {
            font-size: 15px;
            color: #000;
            font-weight: 500;
        }

        .trip-section {
            padding: 20px 0;
            border-top: 1px solid #f0f0f0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
        }

        .detail-label {
            font-size: 14px;
            color: #666;
        }

        .detail-value {
            font-size: 14px;
            color: #000;
            font-weight: 500;
            text-align: right;
        }

        .location-badge {
            position: absolute;
            top: 80px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .location-icon {
            color: #10b981;
            font-size: 16px;
        }

        .zoom-controls {
            position: absolute;
            right: 20px;
            top: 140px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 999;
        }

        .zoom-button {
            width: 44px;
            height: 44px;
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 18px;
            color: #333;
        }

        .zoom-button:hover {
            background: #f5f5f5;
            transform: scale(1.05);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f0f0f0;
            border-top: 4px solid #10b981;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 16px;
            color: #666;
        }

        .error-message {
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%);
            background: #fee;
            color: #c00;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: none;
        }

        .error-message.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { transform: translate(-50%, -20px); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 12px 16px;
            }

            .tracking-title {
                font-size: 16px;
            }

            .tracking-subtitle {
                display: none;
            }

            .info-card {
                max-height: 60vh;
            }

            .location-badge {
                top: 70px;
                right: 16px;
                font-size: 12px;
                padding: 10px 14px;
            }

            .zoom-controls {
                right: 16px;
                top: 120px;
            }
        }

        .custom-marker {
            background: white;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .vehicle-marker {
            width: 40px;
            height: 40px;
            background: #000;
            color: white;
            border: 3px solid white;
        }

        .outlet-marker {
            width: 32px;
            height: 32px;
            background: white;
            border: 2px solid #10b981;
        }
    </style>
</head>
<body>

    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading tracking information...</div>
    </div>

    <div id="errorMessage" class="error-message"></div>

    <div class="top-bar">
        <div class="top-bar-left">
            <button class="back-button" onclick="window.history.back()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div>
                <span class="tracking-title">GPS Tracking</span>
                <div class="tracking-subtitle"><?php echo htmlspecialchars($driver_name); ?></div>
            </div>
        </div>
        <div class="status-pill" id="statusPill">
            <span class="status-pulse"></span>
            <span>Live</span>
        </div>
    </div>

    <div id="map"></div>

    <div class="location-badge" id="locationBadge" style="display: none;">
        <i class="fas fa-map-marker-alt location-icon"></i>
        <span id="locationText">Updating location...</span>
    </div>

    <div class="zoom-controls">
        <button class="zoom-button" id="zoomIn">
            <i class="fas fa-plus"></i>
        </button>
        <button class="zoom-button" id="zoomOut">
            <i class="fas fa-minus"></i>
        </button>
        <button class="zoom-button" id="centerMap" title="Center on vehicle">
            <i class="fas fa-crosshairs"></i>
        </button>
    </div>

    <div class="info-card" id="infoCard">
        <div class="drag-handle" id="dragHandle"></div>

        <div class="info-content">

            <div class="driver-section" id="driverSection" style="display: none;">
                <div class="driver-avatar" id="driverAvatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="driver-info">
                    <div class="driver-name" id="driverName">Loading...</div>
                    <div class="driver-vehicle" id="driverVehicle">Delivery Driver</div>
                </div>
            </div>

            <div class="route-section">
                <div class="route-header">
                    <span class="route-title">Trip Route</span>
                    <span class="route-eta" id="routeEta">Calculating...</span>
                </div>

                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="progressBar" style="width: 0%;"></div>
                </div>

                <div class="route-stops">
                    <div class="route-stop">
                        <div class="stop-icon origin">
                            <i class="fas fa-circle" style="font-size: 8px;"></i>
                        </div>
                        <div class="stop-details">
                            <div class="stop-label">From</div>
                            <div class="stop-address" id="originAddress">Loading...</div>
                        </div>
                    </div>

                    <div class="route-stop">
                        <div class="stop-icon destination">
                            <i class="fas fa-map-marker-alt" style="font-size: 12px;"></i>
                        </div>
                        <div class="stop-details">
                            <div class="stop-label">To</div>
                            <div class="stop-address" id="destinationAddress">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="trip-section">
                <div class="detail-row">
                    <span class="detail-label">Trip ID</span>
                    <span class="detail-value" id="tripId"><?php echo htmlspecialchars(substr($trip_id, 0, 8)); ?>...</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value" id="tripStatus">---</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Parcels</span>
                    <span class="detail-value" id="parcelCount">---</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Last Update</span>
                    <span class="detail-value" id="lastUpdate">---</span>
                </div>
                <!-- Dynamic rows will be added here by JavaScript -->
            </div>

            <!-- New parcel summary section -->
            <div class="parcels-section" id="parcelsSection" style="display: none; padding: 20px 0; border-top: 1px solid #f0f0f0;">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 16px;">
                    <span style="font-size: 15px; font-weight: 600; color: #000;">Parcels in Trip</span>
                    <button id="toggleParcels" style="background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 13px; font-weight: 600;">
                        <i class="fas fa-chevron-down"></i> Show Details
                    </button>
                </div>
                <div id="parcelsList" style="display: none;"></div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        class OutletGPSTracker {
            constructor() {
                this.map = null;
                this.vehicleMarker = null;
                this.originMarker = null;
                this.destinationMarker = null;
                this.stopMarkers = [];
                this.routeLine = null;
                this.tripId = '<?php echo htmlspecialchars($trip_id); ?>';
                this.refreshInterval = null;
                this.isMinimized = false;

                this.init();
            }

            async init() {
                if (!this.tripId) {
                    this.showError('No trip ID provided');
                    return;
                }

                this.initMap();
                this.setupEventListeners();
                await this.loadTrackingData();
                this.startAutoRefresh();
            }

            initMap() {
                this.map = L.map('map', {
                    zoomControl: false,
                    attributionControl: false
                }).setView([-15.3875, 28.3228], 12);

                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    maxZoom: 19
                }).addTo(this.map);

                L.control.attribution({
                    position: 'bottomleft',
                    prefix: false
                }).addAttribution('© OpenStreetMap').addTo(this.map);
            }

            setupEventListeners() {
                document.getElementById('zoomIn').addEventListener('click', () => {
                    this.map.zoomIn();
                });

                document.getElementById('zoomOut').addEventListener('click', () => {
                    this.map.zoomOut();
                });

                document.getElementById('centerMap').addEventListener('click', () => {
                    if (this.vehicleMarker) {
                        this.map.setView(this.vehicleMarker.getLatLng(), 15, {
                            animate: true,
                            duration: 0.5
                        });
                    }
                });

                
                document.getElementById('toggleParcels').addEventListener('click', () => {
                    const parcelsList = document.getElementById('parcelsList');
                    const toggleBtn = document.getElementById('toggleParcels');
                    const icon = toggleBtn.querySelector('i');
                    
                    if (parcelsList.style.display === 'none') {
                        parcelsList.style.display = 'block';
                        icon.className = 'fas fa-chevron-up';
                        toggleBtn.childNodes[1].textContent = ' Hide Details';
                    } else {
                        parcelsList.style.display = 'none';
                        icon.className = 'fas fa-chevron-down';
                        toggleBtn.childNodes[1].textContent = ' Show Details';
                    }
                });

                const dragHandle = document.getElementById('dragHandle');
                const infoCard = document.getElementById('infoCard');

                let startY = 0;
                let currentY = 0;

                dragHandle.addEventListener('touchstart', (e) => {
                    startY = e.touches[0].clientY;
                });

                dragHandle.addEventListener('touchmove', (e) => {
                    currentY = e.touches[0].clientY;
                    const diff = currentY - startY;

                    if (diff > 50 && !this.isMinimized) {
                        infoCard.classList.add('minimized');
                        this.isMinimized = true;
                    } else if (diff < -50 && this.isMinimized) {
                        infoCard.classList.remove('minimized');
                        this.isMinimized = false;
                    }
                });
            }

            async loadTrackingData() {
                try {
                    const response = await fetch(`api/gps_tracking.php?action=track_trip&trip_id=${encodeURIComponent(this.tripId)}`, {
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const result = await response.json();

                    if (result.success) {
                        this.updateUI(result);
                        this.hideLoading();
                    } else {
                        throw new Error(result.error || 'Failed to load tracking data');
                    }

                } catch (error) {
                    console.error('Tracking error:', error);
                    this.showError(error.message);
                    this.hideLoading();
                }
            }

            updateUI(data) {
                
                if (data.driver) {
                    document.getElementById('driverSection').style.display = 'flex';
                    document.getElementById('driverName').textContent = data.driver.driver_name || 'Unknown Driver';

                    const initials = (data.driver.driver_name || 'U D').split(' ').map(n => n[0]).join('').toUpperCase();
                    document.getElementById('driverAvatar').innerHTML = initials;

                    let vehicleText = 'No Vehicle Assigned';
                    if (data.vehicle && data.vehicle.name) {
                        vehicleText = `${data.vehicle.name}`;
                        if (data.vehicle.plate_number && data.vehicle.plate_number !== 'N/A') {
                            vehicleText += ` (${data.vehicle.plate_number})`;
                        }
                        if (data.driver.status) {
                            vehicleText += ` • ${data.driver.status.charAt(0).toUpperCase() + data.driver.status.slice(1)}`;
                        }
                    }
                    document.getElementById('driverVehicle').textContent = vehicleText;
                }

                
                if (data.company && data.company.name) {
                    const titleElement = document.querySelector('.tracking-title');
                    if (titleElement) {
                        titleElement.textContent = `${data.company.name} - GPS Tracking`;
                    }
                }

                
                document.getElementById('tripStatus').textContent = (data.trip_status || 'unknown').toUpperCase().replace('_', ' ');
                document.getElementById('parcelCount').textContent = `${data.total_parcels || 0} parcel${data.total_parcels !== 1 ? 's' : ''}`;

                
                const tripSection = document.querySelector('.trip-section');
                if (tripSection && data.trip_date) {
                    
                    const existingRows = tripSection.querySelectorAll('.dynamic-row');
                    existingRows.forEach(row => row.remove());

                    
                    if (data.trip_date) {
                        const dateRow = document.createElement('div');
                        dateRow.className = 'detail-row dynamic-row';
                        dateRow.innerHTML = `
                            <span class="detail-label">Trip Date</span>
                            <span class="detail-value">${new Date(data.trip_date).toLocaleDateString()}</span>
                        `;
                        tripSection.appendChild(dateRow);
                    }

                    
                    if (data.departure_time) {
                        const depRow = document.createElement('div');
                        depRow.className = 'detail-row dynamic-row';
                        depRow.innerHTML = `
                            <span class="detail-label">Departure</span>
                            <span class="detail-value">${new Date(data.departure_time).toLocaleString()}</span>
                        `;
                        tripSection.appendChild(depRow);
                    }

                    
                    if (data.arrival_time) {
                        const arrRow = document.createElement('div');
                        arrRow.className = 'detail-row dynamic-row';
                        arrRow.innerHTML = `
                            <span class="detail-label">Arrival</span>
                            <span class="detail-value">${new Date(data.arrival_time).toLocaleString()}</span>
                        `;
                        tripSection.appendChild(arrRow);
                    }

                    
                    if (data.vehicle && data.vehicle.status) {
                        const vehicleRow = document.createElement('div');
                        vehicleRow.className = 'detail-row dynamic-row';
                        vehicleRow.innerHTML = `
                            <span class="detail-label">Vehicle Status</span>
                            <span class="detail-value" style="text-transform: capitalize;">${data.vehicle.status.replace('_', ' ')}</span>
                        `;
                        tripSection.appendChild(vehicleRow);
                    }

                    
                    if (data.stops && data.stops.length > 1) {
                        const distanceRow = document.createElement('div');
                        distanceRow.className = 'detail-row dynamic-row';
                        distanceRow.innerHTML = `
                            <span class="detail-label">Route Stops</span>
                            <span class="detail-value">${data.stops.length} stops</span>
                        `;
                        tripSection.appendChild(distanceRow);
                    }
                }

                
                if (data.route_progress !== undefined) {
                    document.getElementById('progressBar').style.width = `${data.route_progress}%`;
                    let progressText = `${Math.round(data.route_progress)}% Complete`;
                    if (data.estimated_completion) {
                        const eta = new Date(data.estimated_completion);
                        progressText += ` • ETA: ${eta.toLocaleTimeString()}`;
                    }
                    document.getElementById('routeEta').textContent = progressText;
                }

                
                if (data.stops && data.stops.length > 0) {
                    const firstStop = data.stops[0];
                    const lastStop = data.stops[data.stops.length - 1];

                    document.getElementById('originAddress').textContent = 
                        firstStop.outlet_name || firstStop.address || firstStop.location || 'Origin';
                    document.getElementById('destinationAddress').textContent = 
                        lastStop.outlet_name || lastStop.address || lastStop.location || 'Destination';
                }

                
                if (data.last_update) {
                    try {
                        const timestamp = new Date(data.last_update);
                        document.getElementById('lastUpdate').textContent = timestamp.toLocaleTimeString();
                    } catch (e) {
                        document.getElementById('lastUpdate').textContent = 'Just now';
                    }
                }

                
                if (data.current_location) {
                    this.updateMapMarkers(data);
                    let speedText = `Speed: ${Math.round(data.current_location.speed || 0)} km/h`;
                    if (data.current_location.accuracy) {
                        speedText += ` • Accuracy: ${Math.round(data.current_location.accuracy)}m`;
                    }
                    if (data.current_location.source) {
                        speedText += ` • Source: ${data.current_location.source.toUpperCase()}`;
                    }
                    this.showLocationBadge(speedText);
                } else {
                    this.showError('No location data available');
                }

                
                if (data.parcels && data.parcels.length > 0) {
                    document.getElementById('parcelsSection').style.display = 'block';
                    this.updateParcelsList(data.parcels);
                } else {
                    document.getElementById('parcelsSection').style.display = 'none';
                }
            }

            updateParcelsList(parcels) {
                const parcelsList = document.getElementById('parcelsList');
                let html = '<div style="display: grid; gap: 12px;">';
                
                parcels.forEach((parcel, index) => {
                    const statusColors = {
                        'pending': '#f59e0b',
                        'assigned': '#3b82f6', 
                        'in_transit': '#8b5cf6',
                        'completed': '#10b981',
                        'delivered': '#059669',
                        'cancelled': '#ef4444'
                    };
                    const statusColor = statusColors[parcel.parcel_list_status] || '#6b7280';
                    
                    html += `
                        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px;">
                            <div style="display: flex; justify-content: between; align-items: start; margin-bottom: 8px;">
                                <div style="flex: 1;">
                                    <p style="margin: 0; font-weight: 600; font-size: 14px; color: #111;">${parcel.track_number}</p>
                                    <p style="margin: 2px 0; font-size: 12px; color: #666;">
                                        ${parcel.sender_name} → ${parcel.receiver_name}
                                    </p>
                                    ${parcel.receiver_phone ? `<p style="margin: 2px 0; font-size: 12px; color: #666;"><i class="fas fa-phone" style="width: 12px;"></i> ${parcel.receiver_phone}</p>` : ''}
                                </div>
                                <span style="background: ${statusColor}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase;">
                                    ${parcel.parcel_list_status.replace('_', ' ')}
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 11px;">
                                <div style="background: white; padding: 6px; border-radius: 4px; text-align: center;">
                                    <div style="color: #666; margin-bottom: 2px;">Weight</div>
                                    <div style="font-weight: 600; color: #111;">${parcel.parcel_weight || 0} kg</div>
                                </div>
                                <div style="background: white; padding: 6px; border-radius: 4px; text-align: center;">
                                    <div style="color: #666; margin-bottom: 2px;">Fee</div>
                                    <div style="font-weight: 600; color: #111;">ZMW ${parcel.delivery_fee || 0}</div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                parcelsList.innerHTML = html;
            }

            updateMapMarkers(data) {
                if (!data.current_location ||
                    typeof data.current_location.latitude === 'undefined' ||
                    typeof data.current_location.longitude === 'undefined' ||
                    data.current_location.latitude === null ||
                    data.current_location.longitude === null ||
                    isNaN(parseFloat(data.current_location.latitude)) ||
                    isNaN(parseFloat(data.current_location.longitude))) {
                    this.showError('No valid location data available');
                    return;
                }

                const currentLat = parseFloat(data.current_location.latitude);
                const currentLng = parseFloat(data.current_location.longitude);

                
                if (this.vehicleMarker) {
                    this.vehicleMarker.setLatLng([currentLat, currentLng]);
                } else {
                    const vehicleIcon = L.divIcon({
                        html: `<div class="custom-marker vehicle-marker">
                            <i class="fas fa-truck" style="font-size: 18px;"></i>
                        </div>`,
                        className: '',
                        iconSize: [40, 40],
                        iconAnchor: [20, 20]
                    });
                    this.vehicleMarker = L.marker([currentLat, currentLng], { icon: vehicleIcon })
                        .addTo(this.map);
                }

                
                if (data.stops && Array.isArray(data.stops)) {
                    
                    this.stopMarkers.forEach(marker => this.map.removeLayer(marker));
                    this.stopMarkers = [];

                    data.stops.forEach((stop, index) => {
                        if (stop.latitude !== null && stop.longitude !== null &&
                            !isNaN(parseFloat(stop.latitude)) && !isNaN(parseFloat(stop.longitude))) {

                            const isOrigin = index === 0;
                            const isDestination = index === data.stops.length - 1;

                            let iconHtml = '';
                            let iconColor = '#2563eb';
                            if (isOrigin) {
                                iconHtml = '<i class="fas fa-circle" style="font-size: 8px; color: #0284c7;"></i>';
                                iconColor = '#0284c7';
                            } else if (isDestination) {
                                iconHtml = '<i class="fas fa-map-marker-alt" style="font-size: 12px; color: #16a34a;"></i>';
                                iconColor = '#16a34a';
                            } else {
                                iconHtml = `<span style="font-size:12px;color:#2563eb;font-weight:bold;">${stop.stop_order || index + 1}</span>`;
                            }

                            const stopIcon = L.divIcon({
                                html: `<div class="custom-marker outlet-marker" style="border-color: ${iconColor};">${iconHtml}</div>`,
                                className: '',
                                iconSize: [32, 32],
                                iconAnchor: [16, 16]
                            });

                            
                            let popupContent = `<div style="min-width: 200px;">`;
                            popupContent += `<h4 style="margin: 0 0 8px 0; color: #111; font-size: 14px; font-weight: 600;">`;
                            if (isOrigin) {
                                popupContent += `🏁 Origin Stop`;
                            } else if (isDestination) {
                                popupContent += `🎯 Final Destination`;
                            } else {
                                popupContent += `🛑 Stop ${stop.stop_order || index + 1}`;
                            }
                            popupContent += `</h4>`;
                            
                            if (stop.outlet_name) {
                                popupContent += `<p style="margin: 4px 0; font-weight: 600; color: #333;">${stop.outlet_name}</p>`;
                            }
                            
                            if (stop.address) {
                                popupContent += `<p style="margin: 4px 0; color: #666; font-size: 13px;">${stop.address}</p>`;
                            }
                            
                            if (stop.contact_person) {
                                popupContent += `<p style="margin: 4px 0; color: #666; font-size: 12px;"><i class="fas fa-user"></i> ${stop.contact_person}</p>`;
                            }
                            
                            if (stop.contact_phone) {
                                popupContent += `<p style="margin: 4px 0; color: #666; font-size: 12px;"><i class="fas fa-phone"></i> ${stop.contact_phone}</p>`;
                            }
                            
                            if (stop.arrival_time) {
                                const arrivalTime = new Date(stop.arrival_time).toLocaleString();
                                popupContent += `<p style="margin: 4px 0; color: #10b981; font-size: 12px;"><i class="fas fa-clock"></i> Arrived: ${arrivalTime}</p>`;
                            }
                            
                            if (stop.departure_time) {
                                const departureTime = new Date(stop.departure_time).toLocaleString();
                                popupContent += `<p style="margin: 4px 0; color: #ef4444; font-size: 12px;"><i class="fas fa-clock"></i> Departed: ${departureTime}</p>`;
                            }
                            
                            popupContent += `</div>`;

                            const marker = L.marker([parseFloat(stop.latitude), parseFloat(stop.longitude)], { icon: stopIcon })
                                .addTo(this.map)
                                .bindPopup(popupContent);

                            this.stopMarkers.push(marker);
                        }
                    });
                }

                
                if (data.stops && data.stops.length > 1) {
                    if (this.routeLine) {
                        this.map.removeLayer(this.routeLine);
                    }

                    const routePoints = data.stops
                        .filter(stop => stop.latitude !== null && stop.longitude !== null)
                        .map(stop => [parseFloat(stop.latitude), parseFloat(stop.longitude)]);

                    if (routePoints.length > 1) {
                        
                        routePoints.splice(1, 0, [currentLat, currentLng]);

                        this.routeLine = L.polyline(routePoints, {
                            color: '#10b981',
                            weight: 4,
                            opacity: 0.7,
                            smoothFactor: 1
                        }).addTo(this.map);

                        const bounds = L.latLngBounds(routePoints);
                        this.map.fitBounds(bounds, { padding: [80, 80] });
                    } else {
                        this.map.setView([currentLat, currentLng], 14);
                    }
                } else {
                    this.map.setView([currentLat, currentLng], 14);
                }
            }

            showLocationBadge(text) {
                const badge = document.getElementById('locationBadge');
                document.getElementById('locationText').textContent = text;
                badge.style.display = 'flex';
            }

            showError(message) {
                const errorEl = document.getElementById('errorMessage');
                errorEl.textContent = message;
                errorEl.classList.add('show');

                setTimeout(() => {
                    errorEl.classList.remove('show');
                }, 5000);
            }

            hideLoading() {
                document.getElementById('loadingOverlay').style.display = 'none';
            }

            startAutoRefresh() {
                this.refreshInterval = setInterval(() => {
                    this.loadTrackingData();
                }, 10000); 
            }

            destroy() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
            }
        }

        
        let tracker;
        document.addEventListener('DOMContentLoaded', () => {
            tracker = new OutletGPSTracker();
        });

        
        window.addEventListener('beforeunload', () => {
            if (tracker) {
                tracker.destroy();
            }
        });
    </script>
</body>
</html>
