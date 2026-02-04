<?php
require_once '../../includes/OutletAwareSupabaseHelper.php';
require_once '../../includes/ResponseCache.php';
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    session_start();
    
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header('Content-Type: application/json');
}
$startTime = microtime(true);
$supabase = new OutletAwareSupabaseHelper();
$cache = new ResponseCache();
try {
    
    $activeTrips = $supabase->get('trips', 'trip_status=in.(scheduled,accepted,in_transit)&select=driver_id,company_id');
    
    if (empty($activeTrips)) {
        $result = [
            'success' => true,
            'message' => 'No active drivers found',
            'drivers_warmed' => 0,
            'execution_time' => round(microtime(true) - $startTime, 3)
        ];
        
        if ($isCLI) {
            echo "Cache Warming Complete\n";
            echo "No active drivers found\n";
        } else {
            echo json_encode($result);
        }
        exit;
    }
    
    
    $driverCompanies = [];
    foreach ($activeTrips as $trip) {
        if (!empty($trip['driver_id']) && !empty($trip['company_id'])) {
            $key = $trip['driver_id'] . '_' . $trip['company_id'];
            $driverCompanies[$key] = [
                'driver_id' => $trip['driver_id'],
                'company_id' => $trip['company_id']
            ];
        }
    }
    
    $warmed = 0;
    $errors = [];
    
    if ($isCLI) {
        echo "Warming cache for " . count($driverCompanies) . " active drivers...\n";
    }
    
    foreach ($driverCompanies as $dc) {
        $driverId = $dc['driver_id'];
        $companyId = $dc['company_id'];
        
        try {
            
            $_SESSION['user_id'] = $driverId;
            $_SESSION['company_id'] = $companyId;
            $_SESSION['role'] = 'driver';
            
            
            $dashboardKey = "driver_dashboard_{$driverId}_{$companyId}";
            $tripKey = "active_trip_{$driverId}_{$companyId}";
            
            
            $existingDashboard = $cache->get($dashboardKey);
            $existingTrip = $cache->get($tripKey);
            
            if ($existingDashboard === null || $existingTrip === null) {
                
                $trips = $supabase->get('trips', 'driver_id=eq.' . urlencode($driverId) . '&company_id=eq.' . urlencode($companyId));
                
                if (!empty($trips)) {
                    
                    $cacheData = [
                        'success' => true,
                        'driver_id' => $driverId,
                        'company_id' => $companyId,
                        'trip_count' => count($trips),
                        'cached_at' => date('Y-m-d H:i:s')
                    ];
                    
                    
                    if ($existingDashboard === null) {
                        $cache->set($dashboardKey, $cacheData, 30);
                    }
                    if ($existingTrip === null) {
                        $cache->set($tripKey, $cacheData, 20);
                    }
                    
                    $warmed++;
                    
                    if ($isCLI) {
                        echo "✓ Warmed cache for driver $driverId\n";
                    }
                }
            } else {
                if ($isCLI) {
                    echo "- Cache already warm for driver $driverId\n";
                }
            }
            
        } catch (Exception $e) {
            $errors[] = [
                'driver_id' => $driverId,
                'error' => $e->getMessage()
            ];
            
            if ($isCLI) {
                echo "✗ Failed to warm cache for driver $driverId: " . $e->getMessage() . "\n";
            }
        }
    }
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 3);
    
    $result = [
        'success' => true,
        'total_drivers' => count($driverCompanies),
        'drivers_warmed' => $warmed,
        'already_cached' => count($driverCompanies) - $warmed - count($errors),
        'errors' => count($errors),
        'error_details' => $errors,
        'execution_time' => $executionTime
    ];
    
    if ($isCLI) {
        echo "\nCache Warming Complete:\n";
        echo "- Total drivers: " . count($driverCompanies) . "\n";
        echo "- Newly warmed: $warmed\n";
        echo "- Already cached: " . ($result['already_cached']) . "\n";
        echo "- Errors: " . count($errors) . "\n";
        echo "- Execution time: {$executionTime}s\n";
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    $result = [
        'success' => false,
        'error' => $e->getMessage(),
        'execution_time' => round(microtime(true) - $startTime, 3)
    ];
    
    if ($isCLI) {
        echo "ERROR: " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
}
