<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../../login.php');
    exit();
}

$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Route - Driver Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            padding-top: 66px !important;
            margin: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .page-title {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-subtitle {
            color: #718096;
            font-size: 14px;
        }
        
        #map {
            width: 100%;
            height: 500px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            z-index: 1;
        }
        
        .map-controls {
            position: fixed;
            top: 90px;
            right: 30px;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .map-btn {
            width: 45px;
            height: 45px;
            border: none;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            cursor: pointer;
            font-size: 18px;
            color: #667eea;
            transition: all 0.3s;
        }
        
        .map-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .location-info {
            position: fixed;
            bottom: 30px;
            left: 30px;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            z-index: 1001;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .info-text {
            font-size: 14px;
            color: #4a5568;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-map-marked-alt"></i>
                Live Route Tracking
            </h1>
            <p class="page-subtitle">Track your current location and route in real-time</p>
        </div>

        <div id="map"></div>
    </div>

    <div class="map-controls">
        <button class="map-btn" onclick="centerMap()" title="Center on my location">
            <i class="fas fa-crosshairs"></i>
        </button>
        <button class="map-btn" onclick="toggleTracking()" id="trackBtn" title="Toggle tracking">
            <i class="fas fa-pause"></i>
        </button>
        <button class="map-btn" onclick="refreshLocation()" title="Refresh location">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>

    <div class="location-info">
        <div class="status-dot"></div>
        <span class="info-text" id="statusText">Getting location...</span>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/location-cache-service.js"></script>
    <script>
        let map;
        let marker;
        let watchId;
        let isTracking = true;
        let pathCoordinates = [];
        let pathLine;
        let cacheService;

        
        function initMap() {
            map = L.map('map').setView([-15.4167, 28.2833], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            
            cacheService = new LocationCacheService();
            
            
            updateCacheStatus();
            setInterval(updateCacheStatus, 30000); 
            
            
            window.addEventListener('online', () => {
                updateStatus('Back online - syncing cached locations...');
                updateCacheStatus();
            });
            
            window.addEventListener('offline', () => {
                updateStatus('Offline - locations will be cached');
                updateCacheStatus();
            });

            startTracking();
        }

        
        const driverIcon = L.divIcon({
            html: '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3);"><i class="fas fa-car" style="color: white; font-size: 18px;"></i></div>',
            className: '',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });

        
        function startTracking() {
            if (!navigator.geolocation) {
                updateStatus('Geolocation not supported');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                position => {
                    updateLocation(position.coords.latitude, position.coords.longitude);
                    updateStatus('Location tracking active');
                },
                error => {
                    updateStatus('Location access denied');
                    console.error('Location error:', error);
                }
            );

            if (isTracking) {
                watchId = navigator.geolocation.watchPosition(
                    position => {
                        updateLocation(position.coords.latitude, position.coords.longitude);
                        saveLocation(position.coords);
                    },
                    error => {
                        console.error('Watch error:', error);
                        let errorMessage = 'Location tracking error';
                        
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage = 'Location access denied. Please enable location permissions.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage = 'Location unavailable. Check GPS/network.';
                                break;
                            case error.TIMEOUT:
                                errorMessage = 'Location timeout. Retrying...';
                                // Auto-retry after timeout
                                setTimeout(() => {
                                    if (isTracking) {
                                        updateStatus('Retrying location...');
                                        startTracking();
                                    }
                                }, 2000);
                                break;
                        }
                        
                        updateStatus(errorMessage);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 15000, // Increased from 10 to 15 seconds
                        maximumAge: 30000 // Allow cached positions up to 30 seconds old
                    }
                );
            }
        }

        
        function updateLocation(lat, lng) {
            if (marker) {
                map.removeLayer(marker);
            }

            marker = L.marker([lat, lng], { icon: driverIcon }).addTo(map);
            marker.bindPopup(`<b>Your Location</b><br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`);
            
            map.setView([lat, lng], map.getZoom());

            
            pathCoordinates.push([lat, lng]);
            
            if (pathCoordinates.length > 100) {
                pathCoordinates.shift();
            }

            if (pathLine) {
                map.removeLayer(pathLine);
            }

            if (pathCoordinates.length > 1) {
                pathLine = L.polyline(pathCoordinates, {
                    color: '#667eea',
                    weight: 3,
                    opacity: 0.7
                }).addTo(map);
            }
        }

        
        async function saveLocation(coords) {
            const locationData = {
                latitude: coords.latitude,
                longitude: coords.longitude,
                accuracy: coords.accuracy,
                speed: coords.speed,
                heading: coords.heading,
                timestamp: new Date().toISOString()
            };

            try {
                // Cache location locally first
                await cacheService.cacheLocation(locationData);
                
                // Try to sync to server if online
                if (navigator.onLine) {
                    try {
                        const response = await fetch('../api/save-driver-location.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(locationData)
                        });
                        
                        if (response.ok) {
                            updateStatus('Location tracking active');
                        } else {
                            updateStatus('Location cached (sync failed)');
                        }
                    } catch (syncError) {
                        console.warn('Server sync failed:', syncError);
                        updateStatus('Offline - location cached');
                    }
                } else {
                    updateStatus('Offline - location cached');
                }
                
                updateCacheStatus();
            } catch (error) {
                console.error('Error saving location:', error);
                updateStatus('Error: ' + error.message);
            }
        }

        
        async function updateCacheStatus() {
            try {
                const stats = await cacheService.getCacheStats();
                const statusEl = document.getElementById('statusText');
                
                if (stats.unsynced > 0) {
                    statusEl.textContent = `Tracking active (${stats.unsynced} cached locations)`;
                    statusEl.style.color = '#f59e0b'; 
                } else if (navigator.onLine) {
                    statusEl.textContent = 'Location tracking active';
                    statusEl.style.color = '#10b981'; 
                } else {
                    statusEl.textContent = 'Offline mode';
                    statusEl.style.color = '#ef4444'; 
                }
            } catch (error) {
                console.error('Error updating cache status:', error);
            }
        }

        
        function updateStatus(text) {
            document.getElementById('statusText').textContent = text;
        }

        
        function centerMap() {
            if (marker) {
                map.setView(marker.getLatLng(), 15);
                marker.openPopup();
            }
        }

        
        function toggleTracking() {
            isTracking = !isTracking;
            const btn = document.getElementById('trackBtn');
            
            if (isTracking) {
                btn.innerHTML = '<i class="fas fa-pause"></i>';
                startTracking();
                updateStatus('Tracking enabled');
            } else {
                btn.innerHTML = '<i class="fas fa-play"></i>';
                if (watchId) {
                    navigator.geolocation.clearWatch(watchId);
                }
                updateStatus('Tracking paused');
            }
        }

        
        function refreshLocation() {
            if (navigator.geolocation) {
                updateStatus('Refreshing...');
                navigator.geolocation.getCurrentPosition(
                    position => {
                        updateLocation(position.coords.latitude, position.coords.longitude);
                        centerMap();
                        updateStatus('Location updated');
                    },
                    error => {
                        updateStatus('Refresh failed');
                    }
                );
            }
        }

        
        document.addEventListener('DOMContentLoaded', initMap);

        
        window.addEventListener('beforeunload', () => {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
        });
    </script>
</body>
</html>
