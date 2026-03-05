<?php
require_once __DIR__ . '/../includes/session_manager.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'outlet_manager') {
    header('Location: ../login.php');
    exit;
}

$tripId = $_GET['trip_id'] ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Live Tracking - Manager</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: 'Segoe UI', sans-serif; overflow: hidden; }

        #trackingMap {
            position: absolute;
            inset: 0;
            z-index: 1;
        }

        /* ── Top bar ─────────────────────────────────────────────── */
        .top-bar {
            position: absolute;
            top: 0; left: 0; right: 0;
            background: #2E0D2A;
            color: white;
            padding: 0.65rem 1rem;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 12px rgba(0,0,0,0.35);
            gap: 0.75rem;
        }
        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 0;
        }
        .top-bar-left i { font-size: 1.05rem; opacity: 0.85; flex-shrink: 0; }
        .top-bar-title {
            font-size: 0.95rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .top-bar-subtitle {
            font-size: 0.7rem;
            opacity: 0.65;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .top-bar-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
        .tb-btn {
            background: rgba(255,255,255,0.12);
            border: 1.5px solid rgba(255,255,255,0.2);
            color: white;
            padding: 0.38rem 0.85rem;
            border-radius: 7px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: background 0.2s;
        }
        .tb-btn:hover { background: rgba(255,255,255,0.22); }
        .tb-btn.active {
            background: rgba(255,255,255,0.28);
            border-color: rgba(255,255,255,0.5);
        }
        .tb-btn-close {
            background: rgba(239,68,68,0.25);
            border-color: rgba(239,68,68,0.4);
        }
        .tb-btn-close:hover { background: rgba(239,68,68,0.5); }

        /* ── Info pill (bottom) ───────────────────────────────────── */
        .info-pill {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(46,13,42,0.92);
            color: white;
            padding: 0.55rem 1.25rem;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1000;
            backdrop-filter: blur(6px);
            white-space: nowrap;
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
            transition: opacity 0.3s;
        }
        .info-pill.hidden { opacity: 0; pointer-events: none; }

        /* ── Leaflet map top offset ──────────────────────────────── */
        .leaflet-top { top: 52px !important; }
    </style>
</head>
<body>

    <div class="top-bar">
        <div class="top-bar-left">
            <i class="fas fa-satellite-dish"></i>
            <div>
                <div class="top-bar-title">Live Trip Tracking</div>
                <div class="top-bar-subtitle" id="tbTripLabel"><?php echo $tripId ? htmlspecialchars(substr($tripId,0,8)).'...' : 'No trip selected'; ?></div>
            </div>
        </div>
        <div class="top-bar-actions">
            <button class="tb-btn" id="satelliteBtn" onclick="toggleSatellite()" title="Toggle satellite view">
                <i class="fas fa-satellite"></i> <span id="satelliteBtnLabel">Satellite</span>
            </button>
            <button class="tb-btn" id="refreshBtn" onclick="manualRefresh()" title="Refresh location">
                <i class="fas fa-sync-alt" id="refreshIcon"></i>
            </button>
            <button class="tb-btn tb-btn-close" onclick="window.close()" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div id="trackingMap"></div>
    <div class="info-pill hidden" id="infoPill">Waiting for location data…</div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const tripId = '<?php echo addslashes($tripId); ?>';

        let trackingMap;
        let isSatellite = false;
        let streetLayer, satelliteLayer, currentLayer;
        let driverMarker = null;
        let routePolyline = null;
        let refreshTimer = null;

        // ── Driver icon ────────────────────────────────────────────
        const driverIcon = L.divIcon({
            className: '',
            html: `<div style="width:36px;height:36px;background:#2E0D2A;border:3px solid white;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px rgba(0,0,0,0.4);">
                       <i class="fas fa-truck" style="color:white;font-size:14px;"></i>
                   </div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -20],
        });

        function getStreetLayer() {
            return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            });
        }

        function getSatelliteLayer() {
            return L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                {
                    attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
                    maxZoom: 19,
                }
            );
        }

        function initMap() {
            trackingMap = L.map('trackingMap', { zoomControl: true }).setView([-15.4067, 28.2871], 13);

            streetLayer    = getStreetLayer();
            satelliteLayer = getSatelliteLayer();

            currentLayer = streetLayer;
            streetLayer.addTo(trackingMap);

            if (tripId) {
                loadTripTracking(tripId);
                refreshTimer = setInterval(() => loadTripTracking(tripId), 10000);
            } else {
                showPill('No trip ID provided', false);
            }
        }

        function toggleSatellite() {
            isSatellite = !isSatellite;
            const btn   = document.getElementById('satelliteBtn');
            const label = document.getElementById('satelliteBtnLabel');

            if (isSatellite) {
                trackingMap.removeLayer(streetLayer);
                satelliteLayer.addTo(trackingMap);
                currentLayer = satelliteLayer;
                btn.classList.add('active');
                label.textContent = 'Street';
            } else {
                trackingMap.removeLayer(satelliteLayer);
                streetLayer.addTo(trackingMap);
                currentLayer = streetLayer;
                btn.classList.remove('active');
                label.textContent = 'Satellite';
            }
        }

        async function manualRefresh() {
            const icon = document.getElementById('refreshIcon');
            icon.classList.add('fa-spin');
            await loadTripTracking(tripId);
            setTimeout(() => icon.classList.remove('fa-spin'), 600);
        }

        function showPill(msg, autoHide = true) {
            const pill = document.getElementById('infoPill');
            pill.textContent = msg;
            pill.classList.remove('hidden');
            if (autoHide) {
                setTimeout(() => pill.classList.add('hidden'), 3500);
            }
        }

        async function loadTripTracking(id) {
            try {
                const response = await fetch(`../api/trip_tracking.php?trip_id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
                const data = await response.json();

                if (!data.success || !data.locations || data.locations.length === 0) {
                    showPill('No location data yet \u2014 waiting for driver\u2026', false);
                    return;
                }

                const locs = data.locations;

                // Remove old route line
                if (routePolyline) { trackingMap.removeLayer(routePolyline); routePolyline = null; }

                // Build coordinate array
                const coords = locs.map(l => [l.latitude, l.longitude]);

                // Draw route polyline
                if (coords.length > 1) {
                    routePolyline = L.polyline(coords, { color: '#2E0D2A', weight: 4, opacity: 0.75, dashArray: '8 4' }).addTo(trackingMap);
                }

                // Move or create driver marker at latest position
                const latest = locs[locs.length - 1];
                const latLng = [latest.latitude, latest.longitude];
                const popupText = `<b>Driver</b><br>Last seen: ${new Date(latest.timestamp).toLocaleTimeString()}`;

                if (driverMarker) {
                    driverMarker.setLatLng(latLng);
                    driverMarker.setPopupContent(popupText);
                } else {
                    driverMarker = L.marker(latLng, { icon: driverIcon }).addTo(trackingMap).bindPopup(popupText);
                }

                // Pan map to driver
                trackingMap.panTo(latLng, { animate: true });

                showPill(`Updated \u00b7 ${new Date(latest.timestamp).toLocaleTimeString()}`);

            } catch (e) {
                console.error('tracking error', e);
                showPill('Connection error \u2014 retrying\u2026', true);
            }
        }

        document.addEventListener('DOMContentLoaded', initMap);
    </script>
</body>
</html>
