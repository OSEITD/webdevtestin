<?php
require_once __DIR__ . '/includes/config.php';
session_start();

$track_number = $_GET['track'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Parcel - WebDev Parcel Service</title>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .tracking-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .tracking-form {
            padding: 30px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .form-group {
            display: flex;
            gap: 10px;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-track {
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-track:hover {
            transform: translateY(-2px);
        }

        .btn-track:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .content {
            padding: 30px;
        }

        .status-banner {
            display: none;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            align-items: center;
            gap: 15px;
        }

        .status-banner.show {
            display: flex;
        }

        .status-icon {
            font-size: 2.5rem;
        }

        .status-details h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .status-details p {
            opacity: 0.8;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .info-card h3 {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-card p {
            font-size: 1.125rem;
            color: #111827;
            font-weight: 500;
        }

        #map {
            width: 100%;
            height: 500px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .timeline {
            position: relative;
            padding-left: 40px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 18px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }

        .timeline-icon {
            position: absolute;
            left: -30px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .timeline-icon.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }

        .timeline-content h3 {
            font-size: 1.125rem;
            margin-bottom: 5px;
        }

        .timeline-content p {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .timeline-time {
            color: #9ca3af;
            font-size: 0.75rem;
            margin-top: 5px;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .error-message {
            background: #fee;
            color: #c00;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .loading {
            text-align: center;
            padding: 40px;
            display: none;
        }

        .loading.show {
            display: block;
        }

        .loading i {
            font-size: 3rem;
            color: #667eea;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .destination-marker {
            background: #10b981;
            color: white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .driver-marker {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5);
            }
            50% {
                box-shadow: 0 4px 20px rgba(102, 126, 234, 0.8);
            }
        }

        .tracking-info-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 15px;
        }

        .tracking-info-banner.show {
            display: flex;
        }

        .tracking-info-banner i {
            font-size: 1.5rem;
        }

        .tracking-info-banner p {
            margin: 0;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            #map {
                height: 350px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="tracking-card">
            <div class="header">
                <h1><i class="fas fa-box"></i> Track Your Parcel</h1>
                <p>Enter your tracking number to see your parcel's destination and status</p>
            </div>

            <div class="tracking-form">
                <form id="trackingForm" class="form-group">
                    <input 
                        type="text" 
                        id="trackingInput" 
                        placeholder="Enter tracking number (e.g., WDP123456)" 
                        value="<?php echo htmlspecialchars($track_number); ?>"
                        required
                    >
                    <button type="submit" class="btn-track">
                        <i class="fas fa-search"></i> Track
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="enable-notifications.php<?php echo $track_number ? '?track=' . urlencode($track_number) : ''; ?>" 
                       style="display: inline-flex; align-items: center; gap: 8px; color: #667eea; text-decoration: none; font-weight: 500; padding: 10px 20px; background: white; border-radius: 8px; transition: all 0.3s;"
                       onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'"
                       onmouseout="this.style.background='white'; this.style.transform='translateY(0)'">
                        <i class="fas fa-bell"></i>
                        <span>Enable Push Notifications</span>
                    </a>
                </div>
            </div>

            <div class="content">
                <div id="errorMessage" class="error-message"></div>
                <div id="loading" class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading tracking information...</p>
                </div>

                <div id="results" style="display: none;">
                    <div id="statusBanner" class="status-banner"></div>
                    
                    <div id="trackingInfoBanner" class="tracking-info-banner">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <p><strong>Live Tracking Active</strong></p>
                            <p style="font-size: 0.85rem; opacity: 0.9;">You can see your driver's real-time location until the parcel arrives at the destination.</p>
                        </div>
                    </div>

                    <div id="infoGrid" class="info-grid"></div>

                    <div id="mapContainer" style="display: none;">
                        <h2 style="margin-bottom: 15px;">
                            <i class="fas fa-map-marker-alt"></i> <span id="mapTitle">Live Tracking</span>
                        </h2>
                        <div id="map"></div>
                    </div>

                    <div id="timelineContainer" style="display: none;">
                        <h2 style="margin-bottom: 20px;">
                            <i class="fas fa-history"></i> Tracking History
                        </h2>
                        <div id="timeline" class="timeline"></div>
                    </div>
                </div>

                <div id="noResults" class="no-results" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h2>No Results</h2>
                    <p>Enter a tracking number above to track your parcel</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        let map = null;
        let destinationMarker = null;
        let driverMarker = null;
        let routeLine = null;
        let autoRefreshInterval = null;

        document.getElementById('trackingForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const trackNumber = document.getElementById('trackingInput').value.trim();
            
            if (!trackNumber) {
                showError('Please enter a tracking number');
                return;
            }

            await trackParcel(trackNumber);
        });

        window.addEventListener('DOMContentLoaded', () => {
            const trackNumber = document.getElementById('trackingInput').value.trim();
            if (trackNumber) {
                trackParcel(trackNumber);
            } else {
                document.getElementById('noResults').style.display = 'block';
            }
        });

        window.addEventListener('beforeunload', () => {
            stopAutoRefresh();
        });

        async function trackParcel(trackNumber) {
            showLoading();
            hideError();
            document.getElementById('results').style.display = 'none';
            document.getElementById('noResults').style.display = 'none';

            try {
                const response = await fetch(`api/customer_tracking.php?track_number=${encodeURIComponent(trackNumber)}`);
                const data = await response.json();

                hideLoading();

                if (!data.success) {
                    showError(data.error || 'Failed to load tracking information');
                    document.getElementById('noResults').style.display = 'block';
                    return;
                }

                displayResults(data);
                
                if (data.show_driver_info && data.parcel.is_in_transit) {
                    startAutoRefresh(trackNumber);
                } else {
                    stopAutoRefresh();
                }

            } catch (error) {
                hideLoading();
                console.error('Tracking error:', error);
                showError('Failed to connect to tracking service. Please try again.');
                document.getElementById('noResults').style.display = 'block';
            }
        }

        function displayResults(data) {
            const { parcel, destination, driver_location, show_driver_info, history } = data;

            document.getElementById('results').style.display = 'block';

            displayStatusBanner(parcel);
            
            const trackingBanner = document.getElementById('trackingInfoBanner');
            if (show_driver_info && parcel.is_in_transit) {
                trackingBanner.classList.add('show');
            } else {
                trackingBanner.classList.remove('show');
            }

            displayInfoCards(parcel, destination, driver_location, show_driver_info);

            if ((show_driver_info && driver_location) || (destination && destination.latitude && destination.longitude)) {
                displayMap(destination, driver_location, parcel, show_driver_info);
            }

            if (history && history.length > 0) {
                displayTimeline(history);
            }
        }

        function displayStatusBanner(parcel) {
            const banner = document.getElementById('statusBanner');
            const status = parcel.status_display;

            banner.className = 'status-banner show';
            banner.style.background = status.color + '20';
            banner.style.borderLeft = `5px solid ${status.color}`;

            banner.innerHTML = `
                <div class="status-icon" style="color: ${status.color};">
                    <i class="fas fa-${status.icon}"></i>
                </div>
                <div class="status-details">
                    <h2 style="color: ${status.color};">${status.label}</h2>
                    <p style="color: #374151;">${status.description}</p>
                </div>
            `;
        }

        function displayInfoCards(parcel, destination, driver_location, show_driver_info) {
            const grid = document.getElementById('infoGrid');
            grid.innerHTML = '';

            const cards = [
                {
                    title: 'Tracking Number',
                    value: parcel.track_number,
                    icon: 'barcode'
                },
                {
                    title: 'Receiver',
                    value: parcel.receiver_name,
                    icon: 'user'
                },
                {
                    title: 'Destination',
                    value: destination?.outlet_name || 'N/A',
                    icon: 'map-marker-alt'
                },
                {
                    title: show_driver_info && driver_location ? 'Live Tracking' : 'Estimated Arrival',
                    value: show_driver_info && driver_location ? 
                        '🚚 Driver en route' : 
                        (parcel.estimated_delivery_date ? new Date(parcel.estimated_delivery_date).toLocaleDateString() : 'TBD'),
                    icon: show_driver_info && driver_location ? 'truck' : 'calendar'
                }
            ];

            cards.forEach(card => {
                const cardEl = document.createElement('div');
                cardEl.className = 'info-card';
                cardEl.innerHTML = `
                    <h3><i class="fas fa-${card.icon}"></i> ${card.title}</h3>
                    <p>${card.value}</p>
                `;
                grid.appendChild(cardEl);
            });
        }

        function displayMap(destination, driver_location, parcel, show_driver_info) {
            const mapContainer = document.getElementById('mapContainer');
            const mapTitle = document.getElementById('mapTitle');
            mapContainer.style.display = 'block';
            
            if (show_driver_info && driver_location) {
                mapTitle.textContent = 'Live Tracking';
            } else {
                mapTitle.textContent = 'Destination Location';
            }

            if (!map) {
                map = L.map('map').setView([-15.3875, 28.3228], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
            }

            if (destinationMarker) {
                map.removeLayer(destinationMarker);
            }
            if (driverMarker) {
                map.removeLayer(driverMarker);
            }
            if (routeLine) {
                map.removeLayer(routeLine);
            }

            const bounds = L.latLngBounds([]);

            if (destination && destination.latitude && destination.longitude) {
                const destIcon = L.divIcon({
                    className: 'destination-marker',
                    html: '<i class="fas fa-store"></i>',
                    iconSize: [50, 50],
                    iconAnchor: [25, 25]
                });

                destinationMarker = L.marker([destination.latitude, destination.longitude], {
                    icon: destIcon
                }).addTo(map);

                bounds.extend([destination.latitude, destination.longitude]);

                const destPopupContent = `
                    <div style="font-family: Poppins; padding: 10px; min-width: 200px;">
                        <h3 style="margin: 0 0 10px 0; color: #10b981;">
                            <i class="fas fa-store"></i> ${destination.outlet_name}
                        </h3>
                        <p style="margin: 5px 0; color: #6b7280;">
                            <i class="fas fa-map-marker-alt"></i> ${destination.address || 'No address available'}
                        </p>
                        ${destination.contact_phone ? `
                            <p style="margin: 5px 0; color: #6b7280;">
                                <i class="fas fa-phone"></i> ${destination.contact_phone}
                            </p>
                        ` : ''}
                        ${parcel.has_arrived ? `
                            <p style="margin: 10px 0 0 0; padding: 8px; background: #d1fae5; color: #065f46; border-radius: 6px; font-weight: 600;">
                                <i class="fas fa-check-circle"></i> Your parcel is ready for pickup!
                            </p>
                        ` : `
                            <p style="margin: 10px 0 0 0; padding: 8px; background: #dbeafe; color: #1e40af; border-radius: 6px;">
                                <i class="fas fa-info-circle"></i> Your parcel will arrive here
                            </p>
                        `}
                    </div>
                `;

                destinationMarker.bindPopup(destPopupContent);
            }

            if (show_driver_info && driver_location && driver_location.latitude && driver_location.longitude) {
                const driverIcon = L.divIcon({
                    className: 'driver-marker',
                    html: '<i class="fas fa-truck"></i>',
                    iconSize: [50, 50],
                    iconAnchor: [25, 25]
                });

                driverMarker = L.marker([driver_location.latitude, driver_location.longitude], {
                    icon: driverIcon
                }).addTo(map);

                bounds.extend([driver_location.latitude, driver_location.longitude]);

                const lastUpdate = new Date(driver_location.timestamp);
                const minutesAgo = Math.floor((new Date() - lastUpdate) / 60000);
                
                const driverPopupContent = `
                    <div style="font-family: Poppins; padding: 10px; min-width: 200px;">
                        <h3 style="margin: 0 0 10px 0; color: #667eea;">
                            <i class="fas fa-truck"></i> Your Driver
                        </h3>
                        <p style="margin: 5px 0; color: #6b7280;">
                            <i class="fas fa-location-arrow"></i> Current location
                        </p>
                        ${driver_location.speed ? `
                            <p style="margin: 5px 0; color: #6b7280;">
                                <i class="fas fa-tachometer-alt"></i> ${Math.round(driver_location.speed * 3.6)} km/h
                            </p>
                        ` : ''}
                        <p style="margin: 5px 0; color: #6b7280;">
                            <i class="fas fa-clock"></i> Updated ${minutesAgo < 1 ? 'just now' : minutesAgo + ' min ago'}
                        </p>
                        <p style="margin: 10px 0 0 0; padding: 8px; background: #ede9fe; color: #5b21b6; border-radius: 6px;">
                            <i class="fas fa-shipping-fast"></i> En route to destination
                        </p>
                    </div>
                `;

                driverMarker.bindPopup(driverPopupContent);

                if (destination && destination.latitude && destination.longitude) {
                    routeLine = L.polyline([
                        [driver_location.latitude, driver_location.longitude],
                        [destination.latitude, destination.longitude]
                    ], {
                        color: '#667eea',
                        weight: 3,
                        opacity: 0.7,
                        dashArray: '10, 10'
                    }).addTo(map);
                }

                driverMarker.openPopup();
            }

            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }

        function displayTimeline(history) {
            const container = document.getElementById('timelineContainer');
            const timeline = document.getElementById('timeline');
            
            container.style.display = 'block';
            timeline.innerHTML = '';

            history.forEach((item, index) => {
                const isLatest = index === history.length - 1;
                const status = item.status_display;

                const timelineItem = document.createElement('div');
                timelineItem.className = 'timeline-item';
                timelineItem.innerHTML = `
                    <div class="timeline-icon ${isLatest ? 'active' : ''}" style="${isLatest ? `background: ${status.color}; border-color: ${status.color}; color: white;` : ''}">
                        <i class="fas fa-${status.icon}"></i>
                    </div>
                    <div class="timeline-content">
                        <h3>${status.label}</h3>
                        <p>${status.description}</p>
                        <div class="timeline-time">
                            <i class="far fa-clock"></i> ${new Date(item.timestamp).toLocaleString()}
                        </div>
                    </div>
                `;
                timeline.appendChild(timelineItem);
            });
        }

        function showLoading() {
            document.getElementById('loading').classList.add('show');
        }

        function hideLoading() {
            document.getElementById('loading').classList.remove('show');
        }

        function showError(message) {
            const errorEl = document.getElementById('errorMessage');
            errorEl.textContent = message;
            errorEl.classList.add('show');
        }

        function hideError() {
            document.getElementById('errorMessage').classList.remove('show');
        }

        function startAutoRefresh(trackNumber) {
            stopAutoRefresh();
            
            autoRefreshInterval = setInterval(async () => {
                console.log('🔄 Auto-refreshing tracking data...');
                
                try {
                    const response = await fetch(`api/customer_tracking.php?track_number=${encodeURIComponent(trackNumber)}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        displayResults(data);
                        
                        if (!data.show_driver_info || !data.parcel.is_in_transit) {
                            console.log('✓ Parcel arrived - stopping auto-refresh');
                            stopAutoRefresh();
                        }
                    }
                } catch (error) {
                    console.error('Auto-refresh error:', error);
                }
            }, 15000);
            
            console.log('✓ Auto-refresh started (every 15 seconds)');
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                console.log('✓ Auto-refresh stopped');
            }
        }
    </script>
</body>
</html>
