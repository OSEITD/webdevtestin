<?php
session_start();

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
        body, html { height:100%; margin:0; }
        #trackingMap { width:100%; height:100%; }
        .top-bar { position: absolute; top:0; left:0; right:0; background:#fff; padding:10px 20px; z-index:1000; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
        .top-bar .btn-close { background:none; border:none; font-size:18px; cursor:pointer; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div>Live Trip Tracking<?php echo $tripId ? " &mdash; " . htmlspecialchars($tripId) : ''; ?></div>
        <button class="btn-close" onclick="window.close()"><i class="fas fa-times"></i></button>
    </div>
    <div id="trackingMap"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const tripId = '<?php echo addslashes($tripId); ?>';
        let trackingMap, trackingPolyline;

        function initMap() {
            trackingMap = L.map('trackingMap').setView([-15.4067,28.2871],13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(trackingMap);
            if (tripId) {
                loadTripTracking(tripId);
                setInterval(() => loadTripTracking(tripId), 10000);
            }
        }

        async function loadTripTracking(id) {
            try {
                const response = await fetch(`../api/trip_tracking.php?trip_id=${id}`, { credentials:'same-origin' });
                const data = await response.json();
                console.log('tracking load returned', data);
                if (data.success && data.locations && data.locations.length>0) {
                    trackingMap.eachLayer(l => { if (l instanceof L.Marker || l instanceof L.Polyline) trackingMap.removeLayer(l); });
                    const coords = [];
                    data.locations.forEach(loc => {
                        L.marker([loc.latitude, loc.longitude]).addTo(trackingMap)
                          .bindPopup(`Driver location at ${new Date(loc.timestamp).toLocaleTimeString()}`);
                        coords.push([loc.latitude, loc.longitude]);
                    });
                    if (coords.length>1) {
                        trackingPolyline = L.polyline(coords,{color:'#3b82f6',weight:3,opacity:0.7}).addTo(trackingMap);
                    }
                    if (coords.length>0) {
                        const group = L.featureGroup(coords.map(c=>L.marker(c)));
                        trackingMap.fitBounds(group.getBounds().pad(0.1));
                    }
                } else {
                    console.warn('no location points for trip', id);
                }
            } catch (e) { console.error('tracking error',e); }
        }

        document.addEventListener('DOMContentLoaded', initMap);
    </script>
</body>
</html>