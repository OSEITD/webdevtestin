<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ERROR | E_PARSE);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$supabaseCandidates = [
    __DIR__ . '/../includes/supabase-helper.php',
    __DIR__ . '/../includes/OutletAwareSupabaseHelper.php',
    __DIR__ . '/../includes/supabase-client.php',
    __DIR__ . '/../includes/supabase-helper-bypass.php',
    __DIR__ . '/../../customer-app/includes/supabase.php'
];
$loaded = false;
foreach ($supabaseCandidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    throw new Exception('Supabase helper not found in includes');
}

try {
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'outlet_manager') {
        throw new Exception('Only outlet managers can access this endpoint');
    }
    
    $managerId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    $outletId = $_SESSION['outlet_id'] ?? null;
    
    if (!$companyId) {
        throw new Exception('Company ID not found in session');
    }
    
    if (!$outletId) {
        throw new Exception('Outlet ID not found in session');
    }

    $supabase = new SupabaseHelper();

    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['page_size']) ? min(200, max(1, intval($_GET['page_size']))) : 100;
    $offset = ($page - 1) * $pageSize;

    
    // OPTIMIZATION: Get trip IDs from stops first (single query)
    $tripStopsQuery = "outlet_id=eq.$outletId&company_id=eq.$companyId&select=trip_id";
    $tripStops = $supabase->get('trip_stops', $tripStopsQuery);
    $tripIdsFromStops = !empty($tripStops) ? array_unique(array_column($tripStops, 'trip_id')) : [];
    
    // OPTIMIZATION: Build efficient OR query letting database filter
    if (empty($tripIdsFromStops)) {
        // No intermediate stops, use simple origin/destination query
        $tripQuery = "company_id=eq.$companyId&trip_status=in.(scheduled,accepted,in_transit,at_outlet)&or=(origin_outlet_id.eq.$outletId,destination_outlet_id.eq.$outletId)&order=created_at.desc&limit=$pageSize&offset=$offset";
        $trips = $supabase->get('trips', $tripQuery);
    } else {
        // Has intermediate stops - combine with origin/destination
        $tripIdsStr = implode(',', $tripIdsFromStops);
        // Get trips where: outlet is stop OR origin OR destination
        $tripQuery = "company_id=eq.$companyId&trip_status=in.(scheduled,accepted,in_transit,at_outlet)&or=(id.in.($tripIdsStr),origin_outlet_id.eq.$outletId,destination_outlet_id.eq.$outletId)&order=created_at.desc";
        $allRelevantTrips = $supabase->get('trips', $tripQuery);
        
        // Remove duplicates and apply pagination
        $uniqueTrips = [];
        $seen = [];
        foreach ($allRelevantTrips as $trip) {
            if (!isset($seen[$trip['id']])) {
                $uniqueTrips[] = $trip;
                $seen[$trip['id']] = true;
            }
        }
        $trips = array_slice($uniqueTrips, $offset, $pageSize);
    }
    
    if (!is_array($trips)) {
        throw new Exception("Failed to fetch trips from database");
    }

    // OPTIMIZATION: Batch fetch ALL trip stops in ONE query (eliminates N+1)
    $tripIds = array_column($trips, 'id');
    $allTripStops = [];
    if (!empty($tripIds)) {
        $tripIdsForStops = implode(',', $tripIds);
        $stopsQuery = "trip_id=in.($tripIdsForStops)&order=stop_order.asc&select=id,trip_id,outlet_id,stop_order,arrival_time,departure_time";
        $allTripStops = $supabase->get('trip_stops', $stopsQuery);
        
        // Ensure it's an array
        if (!is_array($allTripStops)) {
            error_log("Failed to fetch trip stops - got non-array response");
            $allTripStops = [];
        }
        
        error_log("Fetched " . count($allTripStops) . " trip stops for " . count($tripIds) . " trips");
    }

    $driverIds = [];
    $vehicleIds = [];
    $originOutletIds = [];
    $destinationOutletIds = [];
    foreach ($trips as $t) {
        if (!empty($t['driver_id'])) $driverIds[] = $t['driver_id'];
        if (!empty($t['vehicle_id'])) $vehicleIds[] = $t['vehicle_id'];
        if (!empty($t['origin_outlet_id'])) $originOutletIds[] = $t['origin_outlet_id'];
        if (!empty($t['destination_outlet_id'])) $destinationOutletIds[] = $t['destination_outlet_id'];
    }
    // OPTIMIZATION: Also collect outlet IDs from stops
    foreach ($allTripStops as $stop) {
        if (!empty($stop['outlet_id'])) {
            $originOutletIds[] = $stop['outlet_id'];
        }
    }
    $driverIds = array_unique($driverIds);
    $vehicleIds = array_unique($vehicleIds);
    $originOutletIds = array_unique($originOutletIds);
    $destinationOutletIds = array_unique($destinationOutletIds);

    $driversMap = [];
    $vehiclesMap = [];
    $outletsMap = [];

    if (count($driverIds) > 0) {
        $in = implode(',', $driverIds);
        $drivers = $supabase->get('drivers', "id=in.($in)");
        if (is_array($drivers)) {
            foreach ($drivers as $d) {
                if (isset($d['id'])) $driversMap[$d['id']] = $d;
            }
        }
    }

    if (count($vehicleIds) > 0) {
        $in = implode(',', $vehicleIds);
        $vehicles = $supabase->get('vehicle', "id=in.($in)");
        if (is_array($vehicles)) {
            foreach ($vehicles as $v) {
                if (isset($v['id'])) $vehiclesMap[$v['id']] = $v;
            }
        }
    }

    $allOutletIds = array_unique(array_merge($originOutletIds, $destinationOutletIds));
    if (count($allOutletIds) > 0) {
        $in = implode(',', $allOutletIds);
        $outlets = $supabase->get('outlets', "id=in.($in)");
        if (is_array($outlets)) {
            foreach ($outlets as $o) {
                if (isset($o['id'])) $outletsMap[$o['id']] = $o;
            }
        }
    }
    
    // OPTIMIZATION: Build stop lookup map to include in response
    $tripStopsMap = [];
    foreach ($allTripStops as $stop) {
        $tid = $stop['trip_id'];
        if (!isset($tripStopsMap[$tid])) {
            $tripStopsMap[$tid] = [];
        }
        $tripStopsMap[$tid][] = [
            'id' => $stop['id'],
            'outlet_id' => $stop['outlet_id'],
            'outlet_name' => isset($outletsMap[$stop['outlet_id']]) ? $outletsMap[$stop['outlet_id']]['outlet_name'] : 'Unknown',
            'stop_order' => $stop['stop_order'],
            'arrival_time' => $stop['arrival_time'],
            'departure_time' => $stop['departure_time']
        ];
    }
    
    error_log("Built stops map with " . count($tripStopsMap) . " trip entries");
    
    $formattedTrips = [];
    
    foreach ($trips as $trip) {
        
        $driverName = 'Not assigned';
        $driverPhone = '';
        if (!empty($trip['driver_id']) && isset($driversMap[$trip['driver_id']])) {
            $d = $driversMap[$trip['driver_id']];
            $driverName = $d['driver_name'] ?? ($d['full_name'] ?? 'Unknown');
            $driverPhone = $d['driver_phone'] ?? ($d['phone_number'] ?? '');
        }
        
        
        $vehicleName = 'Not assigned';
        $vehiclePlate = '';
        if (!empty($trip['vehicle_id']) && isset($vehiclesMap[$trip['vehicle_id']])) {
            $v = $vehiclesMap[$trip['vehicle_id']];
            $vehicleName = $v['name'] ?? ($v['vehicle_name'] ?? 'Unknown');
            $vehiclePlate = $v['plate_number'] ?? ($v['license_plate'] ?? '');
        }
        
        
        $originOutletName = 'Unknown';
        $destinationOutletName = 'Unknown';
        
        if (!empty($trip['origin_outlet_id']) && isset($outletsMap[$trip['origin_outlet_id']])) {
            $originOutletName = $outletsMap[$trip['origin_outlet_id']]['outlet_name'] ?? 'Unknown';
        }
        
        if (!empty($trip['destination_outlet_id']) && isset($outletsMap[$trip['destination_outlet_id']])) {
            $destinationOutletName = $outletsMap[$trip['destination_outlet_id']]['outlet_name'] ?? 'Unknown';
        }
        
        $formattedTrips[] = [
            'id' => $trip['id'],
            'trip_status' => $trip['trip_status'],
            'departure_time' => $trip['departure_time'],
            'arrival_time' => $trip['arrival_time'],
            'trip_date' => $trip['trip_date'],
            'created_at' => $trip['created_at'],
            'driver_id' => $trip['driver_id'],
            'vehicle_id' => $trip['vehicle_id'],
            'outlet_manager_id' => $trip['outlet_manager_id'],
            'origin_outlet_id' => $trip['origin_outlet_id'],
            'destination_outlet_id' => $trip['destination_outlet_id'],
            'driver_name' => $driverName,
            'driver_phone' => $driverPhone,
            'vehicle_name' => $vehicleName,
            'vehicle_plate' => $vehiclePlate,
            'origin_outlet_name' => $originOutletName,
            'destination_outlet_name' => $destinationOutletName,
            
            'origin_name' => $originOutletName,
            'destination_name' => $destinationOutletName,
            'is_manager_assigned' => $trip['outlet_manager_id'] === $managerId,
            // OPTIMIZATION: Include stops data to avoid separate API calls
            'stops' => $tripStopsMap[$trip['id']] ?? [],
            'stop_count' => count($tripStopsMap[$trip['id']] ?? [])
        ];
    }
    
    
    usort($formattedTrips, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    echo json_encode([
        'success' => true,
        'trips' => $formattedTrips,
        'total_count' => count($formattedTrips),
        'manager_id' => $managerId
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'manager_trips_error'
    ]);
}

?>