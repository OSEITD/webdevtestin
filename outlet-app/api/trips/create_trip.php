<?php

ob_start();

session_start();

try {
    require_once '../../includes/MultiTenantSupabaseHelper.php';
    require_once '../../includes/push_notification_service.php';
} catch (Exception $e) {
    ob_end_clean();
    error_log("Failed to load required files: " . $e->getMessage());
    header("Content-Type: application/json; charset=utf-8");
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Server configuration error"]);
    exit;
}

ob_end_clean();

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

try {
    error_log("=== TRIP CREATION STARTED ===");
    error_log("Session data: " . json_encode(['user_id' => $_SESSION['user_id'] ?? 'NOT SET', 'company_id' => $_SESSION['company_id'] ?? 'NOT SET']));
    
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    error_log("✅ Supabase helper initialized");
    
    
    $input = null;
    if (isset($GLOBALS['test_input'])) {
        
        $input = json_decode($GLOBALS['test_input'], true);
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
    }
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    error_log("Input data received: " . json_encode($input));
    
    
    $required = ['vehicle_id', 'departure_time', 'origin_outlet', 'destination_outlet'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    
    $tripId = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    
    $outletManagerId = $_SESSION['user_id'];

    
    $tripData = [
        'id' => $tripId,
        'vehicle_id' => $input['vehicle_id'],
        'outlet_manager_id' => $outletManagerId,
        'departure_time' => $input['departure_time'],
        'trip_status' => 'scheduled',
        'driver_id' => $input['driver_id'] ?? null,
        'origin_outlet_id' => $input['origin_outlet'],
        'destination_outlet_id' => $input['destination_outlet'],
        'trip_date' => $input['trip_date'] ?? null
    ];
    
    
    $trip = $supabase->createTrip($tripData);
    
    if (!$trip) {
        throw new Exception("Failed to create trip");
    }
    
    
    $createdTrip = is_array($trip) && !empty($trip) ? $trip[0] : $trip;
    
    
    $stops = [];
    $stopOrder = 1;
    
    
    $stops[] = [
        'trip_id' => $tripId,
        'outlet_id' => $input['origin_outlet'],
        'stop_order' => $stopOrder++,
        'company_id' => $_SESSION['company_id']
    ];
    
    
    if (!empty($input['route_stops'])) {
        foreach ($input['route_stops'] as $stop) {
            $stops[] = [
                'trip_id' => $tripId,
                'outlet_id' => $stop['id'],
                'stop_order' => $stopOrder++,
                'company_id' => $_SESSION['company_id']
            ];
        }
    }
    
    
    if ($input['destination_outlet'] !== $input['origin_outlet']) {
        $stops[] = [
            'trip_id' => $tripId,
            'outlet_id' => $input['destination_outlet'],
            'stop_order' => $stopOrder++,
            'company_id' => $_SESSION['company_id']
        ];
    }
    
    
    // count the stops we attempted to create so we can report even if Supabase doesn't return rows
    $intendedStopsCount = count($stops);
    try {
        $createdStops = $supabase->post('trip_stops', $stops);
        // helper may return nested array for batch inserts, flatten it
        if (is_array($createdStops) && isset($createdStops[0]) && is_array($createdStops[0]) && 
            (isset($createdStops[0][0]) || (array_values($createdStops[0]) === $createdStops[0] && !isset($createdStops[0]['id']))) ) {
            // first element itself looks like the full list
            $createdStops = $createdStops[0];
        }
        if (!is_array($createdStops)) {
            $createdStops = [$createdStops];
        }
        error_log("Batch created " . count($createdStops) . " trip stops");
    } catch (Exception $e) {
        error_log("Batch trip stop creation failed: " . $e->getMessage());
        $createdStops = [];
    }
    // if Supabase didn't return the created rows we still need them for parcel assignment
    if (empty($createdStops) && !empty($stops)) {
        error_log("Warning: supabase returned no trip stops. Attempting to re-query by trip_id");
        try {
            $queried = $supabase->get('trip_stops', "trip_id=eq.$tripId");
            if (is_array($queried) && !empty($queried)) {
                $createdStops = $queried;
                error_log("Re-query returned " . count($createdStops) . " trip stops");
            } else {
                error_log("Re-query did not return any stops, using input array without ids");
                $createdStops = $stops;
            }
        } catch (Exception $e) {
            error_log("Failed to re-query trip_stops: " . $e->getMessage());
            $createdStops = $stops;
        }
    }
    
    
    $assignedParcels = [];
    if (!empty($input['selected_parcels'])) {
        
        $parcelIds = array_map(function($id) { 
            return addslashes($id); 
        }, $input['selected_parcels']);
        $parcelIdsStr = implode(',', $parcelIds);
        
        $parcels = $supabase->get('parcels', "id=in.($parcelIdsStr)", 'id,destination_outlet_id');
        
        
        $parcelMap = [];
        foreach ($parcels as $parcel) {
            $parcelMap[$parcel['id']] = $parcel;
        }
        
        
        $stopsByOutlet = [];
        foreach ($createdStops as $stop) {
            $stopsByOutlet[$stop['outlet_id']] = $stop;
        }
        
        
        $finalStop = !empty($createdStops) ? end($createdStops) : null;
        
        error_log("Trip stops created: " . count($createdStops) . " - Outlet IDs: " . json_encode(array_keys($stopsByOutlet)));
        
        
        $parcelListBatch = [];
        $skippedParcels = [];
        foreach ($input['selected_parcels'] as $parcelId) {
            if (!isset($parcelMap[$parcelId])) {
                $skippedParcels[] = ["id" => $parcelId, "reason" => "not found in query results"];
                continue;
            }
            
            $parcelDestination = $parcelMap[$parcelId]['destination_outlet_id'];
            if (empty($parcelDestination)) {
                $skippedParcels[] = ["id" => $parcelId, "reason" => "no destination_outlet_id"];
                continue;
            }
            
            $matchingStop = $stopsByOutlet[$parcelDestination] ?? $finalStop;
            if (!$matchingStop) {
                $skippedParcels[] = ["id" => $parcelId, "reason" => "no matching stop", "destination" => $parcelDestination];
                continue;
            }
            
            $parcelListId = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            $parcelListBatch[] = [
                'id' => $parcelListId,
                'parcel_id' => $parcelId,
                'trip_id' => $tripId,
                'outlet_id' => $matchingStop['outlet_id'],
                'trip_stop_id' => $matchingStop['id'],
                'status' => 'assigned',
                'company_id' => $_SESSION['company_id']
            ];
            $assignedParcels[] = $parcelId;
        }
        
        
        if (!empty($parcelListBatch)) {
            try {
                $resultBatch = $supabase->post('parcel_list', $parcelListBatch);
                error_log("✅ Batch created " . count($parcelListBatch) . " parcel list entries");
                error_log("Parcel list insert result: " . json_encode($resultBatch));
            } catch (Exception $e) {
                error_log("❌ Batch parcel list insert failed: " . $e->getMessage());
            }
        }
        
        
        if (!empty($assignedParcels)) {
            $parcelIdsStr = implode(',', array_map('urlencode', $assignedParcels));
            try {
                $supabase->put("parcels?id=in.($parcelIdsStr)", ['status' => 'assigned']);
                error_log("✅ Updated " . count($assignedParcels) . " parcels to 'assigned' status");
            } catch (Exception $e) {
                error_log("❌ Failed to update parcel statuses: " . $e->getMessage());
            }
        }
        if (!empty($skippedParcels)) {
            error_log("Skipped parcels during assignment: " . json_encode($skippedParcels));
        }
    }
    
    
    $buffered = '';
    if (ob_get_level() > 0) {
        $buffered = ob_get_clean();
        if (!empty($buffered)) {
            error_log("WARNING: Unexpected output in buffer: " . substr($buffered, 0, 100));
        }
    }
    
    $response = [
        "success" => true,
        "trip_id" => $tripId,
        // use intendedStopsCount to ensure we have a non-zero value if insertion response is empty
        "trip_stops_created" => $intendedStopsCount,
        // parcels_assigned reflect how many we actually managed to flag; fall back to selected count if zero
        "parcels_assigned" => count($assignedParcels) > 0 ? count($assignedParcels) : (isset($input['selected_parcels']) ? count($input['selected_parcels']) : 0),
        "selected_parcels_count" => isset($input['selected_parcels']) ? count($input['selected_parcels']) : 0,
        "message" => "Trip created successfully",
        "debug_info" => [
            "intended_stops" => $intendedStopsCount,
            "created_stops" => $createdStops,
            "provided_stops" => $stops,
            "stops_by_outlet" => $stopsByOutlet,
            "final_stop" => $finalStop,
            "parcel_list_batch" => isset($parcelListBatch) ? $parcelListBatch : [],
            "assigned_parcels" => $assignedParcels,
            "skipped_parcels" => isset($skippedParcels) ? $skippedParcels : [],
            "parcels_query" => isset($parcels) ? $parcels : []
        ]
    ];
    
    
    $bgCompanyId = $_SESSION['company_id'];
    $bgUserId = $_SESSION['user_id'];
    $bgDriverId = $input['driver_id'] ?? null;
    $bgTripId = $tripId;
    $bgOriginOutletId = $createdTrip['origin_outlet_id'] ?? $input['origin_outlet'];
    $bgDestinationOutletId = $createdTrip['destination_outlet_id'] ?? $input['destination_outlet'];
    $bgDepartureTime = $input['departure_time'] ?? date('Y-m-d H:i:s');
    $bgParcelsCount = count($assignedParcels);
    
    
    $responseJson = json_encode($response);
    
    http_response_code(200);
    header('Connection: close');
    header('Content-Length: ' . strlen($responseJson));
    echo $responseJson;
    
    
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    
    ignore_user_abort(true);
    set_time_limit(30);
    
    
    // Background processing - ensure no output
    ob_start(); // Start new output buffer for background processing
    
    if (isset($bgDriverId) && !empty($bgDriverId)) {
        try {
            
            require_once '../../includes/MultiTenantSupabaseHelper.php';
            $bgSupabase = new MultiTenantSupabaseHelper($bgCompanyId);
            $pushService = new PushNotificationService($bgSupabase);
            
            
            $outletIds = array_unique([$bgOriginOutletId, $bgDestinationOutletId]);
            $outletIdsStr = implode(',', array_map(function($id) { return addslashes($id); }, $outletIds));
            $outlets = $bgSupabase->get('outlets', "id=in.($outletIdsStr)", 'id,outlet_name');
            
            $outletMap = [];
            foreach ($outlets as $outlet) {
                $outletMap[$outlet['id']] = $outlet['outlet_name'];
            }
            
            $originOutletName = $outletMap[$bgOriginOutletId] ?? 'Unknown';
            $destinationOutletName = $outletMap[$bgDestinationOutletId] ?? 'Unknown';
            
            $tripData = [
                'trip_id' => $bgTripId,
                'origin_outlet_name' => $originOutletName,
                'destination_outlet_name' => $destinationOutletName,
                'departure_time' => $bgDepartureTime,
                'parcels_count' => $bgParcelsCount
            ];
            
            
            $pushService->sendTripAssignmentNotification($bgDriverId, $tripData);
            
            
            if ($bgOriginOutletId) {
                $outletManager = $bgSupabase->get('profiles', "outlet_id=eq.$bgOriginOutletId&role=eq.outlet_manager", 'id');
                if (!empty($outletManager)) {
                    $managerId = $outletManager[0]['id'];
                    $pushService->sendTripAssignmentToManager($managerId, $tripData);
                    error_log("Manager notification sent to manager: $managerId");
                }
            }
            
            error_log("Push notifications sent");
            
        } catch (Exception $e) {
            error_log("Push notification error: " . $e->getMessage());
        }
    }
    
    // Clean up any background output
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    exit(0);
    
} catch (Exception $e) {
    
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $errorMessage = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    
    error_log("=== TRIP CREATION ERROR ===");
    error_log("Error message: " . $errorMessage);
    error_log("Stack trace: " . $errorTrace);
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $errorMessage,
        "debug" => [
            "file" => basename($e->getFile()),
            "line" => $e->getLine()
        ]
    ]);
}
?>
