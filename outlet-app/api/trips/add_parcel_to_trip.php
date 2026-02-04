<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized. Please log in."
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Method not allowed. Use POST."
    ]);
    exit;
}

require_once __DIR__ . '/../../includes/MultiTenantSupabaseHelper.php';

try {
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    $outletId = $_SESSION['outlet_id'] ?? null;

    if (empty($companyId)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Company ID not found in session"
        ]);
        exit;
    }

    if (empty($outletId)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Outlet ID not found in session"
        ]);
        exit;
    }

    
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['trip_id']) || empty($input['trip_id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Trip ID is required"
        ]);
        exit;
    }

    if (!isset($input['parcel_ids']) || !is_array($input['parcel_ids']) || empty($input['parcel_ids'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Parcel IDs array is required and must not be empty"
        ]);
        exit;
    }

    $tripId = $input['trip_id'];
    $parcelIds = $input['parcel_ids'];

    $supabase = new MultiTenantSupabaseHelper($companyId);

    
    $tripFilter = "id=eq." . urlencode($tripId) . "&company_id=eq." . urlencode($companyId);
    $trips = $supabase->get('trips', $tripFilter);

    if (empty($trips)) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Trip not found or access denied"
        ]);
        exit;
    }

    $trip = $trips[0];
    $tripStatus = $trip['trip_status'] ?? 'scheduled';
    
    error_log("DEBUG: Trip data - ID: {$tripId}, Origin: " . ($trip['origin_outlet_id'] ?? 'NULL') . ", Destination: " . ($trip['destination_outlet_id'] ?? 'NULL'));
    error_log("DEBUG: Full trip data: " . json_encode($trip));
    
    
    $parcelStatus = 'assigned';
    $parcelListStatus = 'assigned';
    if ($tripStatus === 'in_transit') {
        $parcelStatus = 'in_transit';
        $parcelListStatus = 'in_transit';
    }

    
    $tripStopsFilter = "trip_id=eq." . urlencode($tripId) . "&outlet_id=eq." . urlencode($outletId);
    $tripStops = $supabase->get('trip_stops', $tripStopsFilter);
    
    $isOriginOutlet = !empty($trip['origin_outlet_id']) && $trip['origin_outlet_id'] === $outletId;
    $isPartOfRoute = !empty($tripStops);

    if (!$isOriginOutlet && !$isPartOfRoute) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "error" => "This outlet is not authorized to add parcels to this trip"
        ]);
        exit;
    }

    
    $allTripStopsFilter = "trip_id=eq." . urlencode($tripId) . "&order=stop_order.asc";
    $allTripStops = $supabase->get('trip_stops', $allTripStopsFilter);
    
    error_log("DEBUG: Found " . count($allTripStops) . " trip stops");
    
    $routeOutlets = [];
    
    
    if (!empty($trip['origin_outlet_id'])) {
        $routeOutlets[] = $trip['origin_outlet_id'];
    }
    
    
    foreach ($allTripStops as $stop) {
        if (!empty($stop['outlet_id'])) {
            $routeOutlets[] = $stop['outlet_id'];
        }
    }
    
    
    if (!empty($trip['destination_outlet_id'])) {
        $routeOutlets[] = $trip['destination_outlet_id'];
    }
    
    $routeOutlets = array_unique($routeOutlets);
    $validDestinations = $routeOutlets; 

    // Fetch parcels - only require destination to be in route (not origin)
    // This allows parcels from any origin to be added as long as they're going to a route outlet
    $parcelsFilter = "id=in.(" . implode(',', array_map('urlencode', $parcelIds)) . ")" .
                     "&destination_outlet_id=in.(" . implode(',', array_map('urlencode', $routeOutlets)) . ")" .
                     "&status=in.(pending,scheduled)";
    
    error_log("DEBUG: Fetching parcels with filter: $parcelsFilter");
    $parcels = $supabase->get('parcels', $parcelsFilter);

    if (count($parcels) !== count($parcelIds)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Some parcels not found, have wrong status, or destination not on trip route. Parcels must have status 'pending' or 'scheduled' and destination must be on the trip route.",
            "route_outlets" => $routeOutlets
        ]);
        exit;
    }

    
    $invalidDestinations = [];
    foreach ($parcels as $parcel) {
        if (empty($parcel['destination_outlet_id'])) {
            $invalidDestinations[] = [
                'track_number' => $parcel['track_number'],
                'reason' => 'Parcel has no destination outlet set'
            ];
        } elseif (!in_array($parcel['destination_outlet_id'], $validDestinations)) {
            $invalidDestinations[] = [
                'track_number' => $parcel['track_number'],
                'reason' => 'Parcel destination is not on this trip route'
            ];
        }
    }

    if (!empty($invalidDestinations)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Some parcels have destinations not on this trip route",
            "invalid_parcels" => $invalidDestinations,
            "trip_destinations" => $validDestinations
        ]);
        exit;
    }

    
    $existingAssignmentsFilter = "parcel_id=in.(" . implode(',', array_map('urlencode', $parcelIds)) . ")" .
                                  "&status=not.in.(cancelled,completed)" .
                                  "&company_id=eq." . urlencode($companyId);
    
    $existingAssignments = $supabase->get('parcel_list', $existingAssignmentsFilter);

    if (!empty($existingAssignments)) {
        $alreadyAssignedIds = array_column($existingAssignments, 'parcel_id');
        $alreadyAssignedTrackNumbers = [];
        foreach ($parcels as $parcel) {
            if (in_array($parcel['id'], $alreadyAssignedIds)) {
                $alreadyAssignedTrackNumbers[] = $parcel['track_number'];
            }
        }
        
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Some parcels are already assigned to active trips",
            "already_assigned" => $alreadyAssignedTrackNumbers
        ]);
        exit;
    }

    

    
    $outletToStopMap = [];
    foreach ($allTripStops as $stop) {
        $outletToStopMap[$stop['outlet_id']] = $stop['id'];
    }

    
    $successfullyAdded = [];
    $failed = [];

    foreach ($parcels as $parcel) {
        try {
            
            $tripStopId = null;
            if (!empty($parcel['destination_outlet_id']) && isset($outletToStopMap[$parcel['destination_outlet_id']])) {
                $tripStopId = $outletToStopMap[$parcel['destination_outlet_id']];
            }

            $parcelListData = [
                'parcel_id' => $parcel['id'],
                'trip_id' => $tripId,
                'outlet_id' => $parcel['destination_outlet_id'], // Destination outlet where parcel will be delivered
                'trip_stop_id' => $tripStopId,
                'status' => $parcelListStatus, 
                'company_id' => $companyId,
                'created_at' => date('Y-m-d\TH:i:s.v\Z'),
                'updated_at' => date('Y-m-d\TH:i:s.v\Z')
            ];

            error_log("DEBUG: Attempting to insert parcel_list - Parcel: {$parcel['track_number']}, Trip: {$tripId}, outlet_id: {$parcel['destination_outlet_id']}, trip_stop_id: " . ($tripStopId ?? 'NULL') . ", Route Outlets: " . json_encode($routeOutlets));
            error_log("DEBUG: outletToStopMap: " . json_encode($outletToStopMap));
            
            $result = $supabase->post('parcel_list', $parcelListData);

            if ($result) {
                
                try {
                    $parcelUpdateData = ['status' => $parcelStatus];
                    $supabase->put("parcels?id=eq." . urlencode($parcel['id']), $parcelUpdateData);
                    error_log("Parcel {$parcel['id']} status updated to $parcelStatus");
                } catch (Exception $e) {
                    error_log("ERROR: Failed to update parcel status: " . $e->getMessage());
                }
                
                $successfullyAdded[] = [
                    'parcel_id' => $parcel['id'],
                    'track_number' => $parcel['track_number'],
                    'status' => $parcelStatus,
                    'parcel_list_entry' => $result
                ];
            } else {
                $failed[] = [
                    'parcel_id' => $parcel['id'],
                    'track_number' => $parcel['track_number'],
                    'reason' => 'Failed to insert into parcel_list'
                ];
            }
        } catch (Exception $e) {
            error_log("Error adding parcel {$parcel['id']} to trip: " . $e->getMessage());
            $failed[] = [
                'parcel_id' => $parcel['id'],
                'track_number' => $parcel['track_number'],
                'reason' => $e->getMessage()
            ];
        }
    }

    $response = [
        "success" => count($successfullyAdded) > 0,
        "added_count" => count($successfullyAdded),
        "failed_count" => count($failed),
        "trip_id" => $tripId,
        "added_parcels" => $successfullyAdded
    ];

    if (!empty($failed)) {
        $response['failed_parcels'] = $failed;
    }

    if (count($successfullyAdded) === 0) {
        http_response_code(400);
        $response['error'] = 'No parcels were successfully added to the trip';
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in add_parcel_to_trip.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to add parcels to trip: " . $e->getMessage()
    ]);
}
?>
