<?php
ob_start();

error_reporting(0);
ini_set('display_errors', 0);

session_start();

if (!file_exists('../includes/MultiTenantSupabaseHelper.php')) {
    ob_clean();
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        'success' => false,
        'error' => 'Helper class file not found',
        'assignments' => []
    ]);
    exit;
}

require_once '../includes/MultiTenantSupabaseHelper.php';

ob_clean();

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

try {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
        if (!isset($_SESSION['company_id'])) {
            $_SESSION['company_id'] = 'O-100';
        }
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = 'test-user';
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication error: ' . $e->getMessage(),
        'assignments' => []
    ]);
    exit;
}

function getTripRouteDetails($tripId, $companyId) {
    $supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
    
    try {
        $stopsQuery = [
            'trip_id' => 'eq.' . $tripId,
            'company_id' => 'eq.' . $companyId,
            'order' => 'stop_order.asc',
            'select' => 'outlet_id,stop_order'
        ];
        
        $stopsUrl = $supabaseUrl . "/rest/v1/trip_stops?" . http_build_query($stopsQuery);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $supabaseKey,
                    'apikey: ' . $supabaseKey,
                    'Content-Type: application/json'
                ],
                'ignore_errors' => true,
                'timeout' => 5
            ]
        ]);
        
        $stopsResponse = @file_get_contents($stopsUrl, false, $context);
        
        if ($stopsResponse === false || empty($stopsResponse)) {
            return "Trip " . substr($tripId, 0, 8) . " - No route info";
        }
        
        $stops = @json_decode($stopsResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($stops) || empty($stops)) {
            return "Trip " . substr($tripId, 0, 8) . " - Route unavailable";
        }
        
        $outletIds = array_unique(array_column($stops, 'outlet_id'));
        if (empty($outletIds)) {
            return "Trip " . substr($tripId, 0, 8) . " - No outlets";
        }
        
        $outletsQuery = [
            'id' => 'in.(' . implode(',', $outletIds) . ')',
            'company_id' => 'eq.' . $companyId,
            'select' => 'id,outlet_name'
        ];
        
        $outletsUrl = $supabaseUrl . "/rest/v1/outlets?" . http_build_query($outletsQuery);
        
        $outletsResponse = @file_get_contents($outletsUrl, false, $context);
        
        if ($outletsResponse === false || empty($outletsResponse)) {
            return "Trip " . substr($tripId, 0, 8) . " - Outlets unavailable";
        }
        
        $outlets = @json_decode($outletsResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($outlets)) {
            return "Trip " . substr($tripId, 0, 8) . " - Invalid outlet data";
        }
        
        $outletMap = [];
        foreach ($outlets as $outlet) {
            $outletMap[$outlet['id']] = $outlet['outlet_name'];
        }
        
        $routeStops = [];
        foreach ($stops as $stop) {
            $outletId = $stop['outlet_id'];
            $outletName = isset($outletMap[$outletId]) ? $outletMap[$outletId] : 'Unknown Outlet';
            $routeStops[] = $outletName;
        }
        
        if (empty($routeStops)) {
            return "Trip " . substr($tripId, 0, 8) . " - No route data";
        }
        
        return implode(' → ', $routeStops);
        
    } catch (Exception $e) {
        error_log("Error fetching trip route for assignment display: " . $e->getMessage());
        return "Trip " . substr($tripId, 0, 8) . " - Route error";
    }
}

try {
    $companyId = $_SESSION['company_id'];
    $supabase = new MultiTenantSupabaseHelper($companyId);
    
    $trips = $supabase->get('trips', 'trip_status=in.(scheduled,in_transit)&vehicle_id=not.is.null&order=updated_at.desc', 'id,vehicle_id,trip_status,updated_at,departure_time,company_id');
    
    if (!is_array($trips) || $trips === null) {
        $trips = [];
    }
    
    if (empty($trips)) {
        $trips = $supabase->get('trips', 'trip_status=in.(scheduled,in_transit)&vehicle_id=not.is.null', 'id,vehicle_id,trip_status,updated_at,departure_time,company_id');
        if (!is_array($trips)) {
            $trips = [];
        }
    }
    
    if (empty($trips)) {
        $trips = $supabase->get('trips', 'trip_status=eq.in_transit&vehicle_id=not.is.null', 'id,vehicle_id,trip_status,updated_at,departure_time,company_id');
        if (!is_array($trips)) {
            $trips = [];
        }
    }
    
    if (empty($trips)) {
        $trips = $supabase->get('trips', 'vehicle_id=not.is.null', 'id,vehicle_id,trip_status,updated_at,departure_time,company_id');
        if (!is_array($trips)) {
            $trips = [];
        }
    }
    
    $assignments = [];
    
    foreach ($trips as $trip) {
        $tripId = $trip['id'];
        $vehicleId = $trip['vehicle_id'];
        
        if (!$vehicleId) {
            continue;
        }
        
        $vehicle = $supabase->get('vehicle', "id=eq.$vehicleId", 'id,name,plate_number,status,vehicle_status');
        
        $vehicleData = null;
        if ($vehicle && !empty($vehicle)) {
            $vehicleData = $vehicle[0];
        }
        
        $routeDescription = getTripRouteDetails($tripId, $companyId);
        
        $assignments[] = [
            'trip_id' => $tripId,
            'vehicle_id' => $vehicleId,
            'route_description' => $routeDescription,
            'vehicle_name' => $vehicleData['name'] ?? 'Unknown Vehicle',
            'plate_number' => $vehicleData['plate_number'] ?? 'No Plate',
            'trip_status' => $trip['trip_status'] ?? 'unknown',
            'vehicle_status' => $vehicleData['vehicle_status'] ?? $vehicleData['status'] ?? 'unknown',
            'updated_at' => $trip['updated_at'] ?? $trip['departure_time'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'assignments' => $assignments,
        'message' => empty($assignments) ? 'No active assignments found' : null
    ]);
    
} catch (Exception $e) {
    error_log("Fetch assignments error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch assignments',
        'assignments' => []
    ]);
}
?>
