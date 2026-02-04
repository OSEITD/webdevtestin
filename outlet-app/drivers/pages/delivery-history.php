<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../../login.php');
    exit();
}

$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'] ?? null;

require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';
$supabase = new OutletAwareSupabaseHelper();

$completedTrips = [];
try {
    $trips = $supabase->get('trips', "driver_id=eq.{$driverId}&trip_status=eq.completed&order=created_at.desc&limit=20", '*');
    
    foreach ($trips as &$trip) {
        if (!empty($trip['origin_outlet_id'])) {
            $origin = $supabase->get('outlets', "id=eq.{$trip['origin_outlet_id']}", 'outlet_name,address');
            $trip['origin'] = $origin[0] ?? null;
        }
        
        if (!empty($trip['destination_outlet_id'])) {
            $dest = $supabase->get('outlets', "id=eq.{$trip['destination_outlet_id']}", 'outlet_name,address');
            $trip['destination'] = $dest[0] ?? null;
        }
        
        $stops = $supabase->get('trip_stops', "trip_id=eq.{$trip['id']}", 'id');
        $trip['stops_count'] = count($stops);
        
        $parcels = $supabase->get('parcel_list', "trip_id=eq.{$trip['id']}", 'id');
        $trip['parcels_count'] = count($parcels);
    }
    
    $completedTrips = $trips;
} catch (Exception $e) {
    error_log("Error loading deliveries: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery History - Driver Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            padding-top: 76px !important;
            margin: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .page-title {
            font-size: 32px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title i {
            color: #667eea;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .trips-grid {
            display: grid;
            gap: 20px;
        }
        
        .trip-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .trip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .trip-id {
            font-size: 18px;
            font-weight: bold;
            color: #2d3748;
        }
        
        .status-badge {
            background: #c6f6d5;
            color: #22543d;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .trip-route {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f7fafc;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .route-point {
            flex: 1;
        }
        
        .route-label {
            font-size: 12px;
            color: #718096;
            margin-bottom: 5px;
        }
        
        .route-name {
            font-size: 16px;
            color: #2d3748;
            font-weight: 600;
        }
        
        .route-arrow {
            font-size: 24px;
            color: #667eea;
        }
        
        .trip-stats {
            display: flex;
            gap: 20px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-icon {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .icon-blue {
            background: #e6f2ff;
            color: #3182ce;
        }
        
        .icon-green {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .stat-value-small {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .empty-state {
            background: white;
            border-radius: 20px;
            padding: 60px;
            text-align: center;
        }
        
        .empty-icon {
            font-size: 80px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                Delivery History
            </h1>
            <p style="color: #718096; margin-bottom: 20px;">View all your completed deliveries</p>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?php echo count($completedTrips); ?></div>
                    <div class="stat-label">Total Deliveries</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo array_sum(array_column($completedTrips, 'parcels_count')); ?></div>
                    <div class="stat-label">Parcels Delivered</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo array_sum(array_column($completedTrips, 'stops_count')); ?></div>
                    <div class="stat-label">Total Stops</div>
                </div>
            </div>
        </div>

        <div class="trips-grid">
            <?php if (empty($completedTrips)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                    <h2 style="color: #2d3748; margin-bottom: 10px;">No Deliveries Yet</h2>
                    <p style="color: #718096;">Your completed deliveries will appear here</p>
                </div>
            <?php else: ?>
                <?php foreach ($completedTrips as $trip): ?>
                    <div class="trip-card">
                        <div class="trip-header">
                            <div>
                                <div class="trip-id">Trip #<?php echo substr($trip['id'], 0, 8); ?></div>
                                <div style="color: #718096; font-size: 14px; margin-top: 5px;">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y g:i A', strtotime($trip['created_at'])); ?>
                                </div>
                            </div>
                            <span class="status-badge">
                                <i class="fas fa-check-circle"></i> Completed
                            </span>
                        </div>
                        
                        <div class="trip-route">
                            <div class="route-point">
                                <div class="route-label">FROM</div>
                                <div class="route-name"><?php echo htmlspecialchars($trip['origin']['outlet_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="route-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                            <div class="route-point">
                                <div class="route-label">TO</div>
                                <div class="route-name"><?php echo htmlspecialchars($trip['destination']['outlet_name'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        
                        <div class="trip-stats">
                            <div class="stat-item">
                                <div class="stat-icon icon-blue">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: #718096;">Stops</div>
                                    <div class="stat-value-small"><?php echo $trip['stops_count']; ?></div>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon icon-green">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: #718096;">Parcels</div>
                                    <div class="stat-value-small"><?php echo $trip['parcels_count']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
