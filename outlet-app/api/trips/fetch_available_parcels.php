<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

    $supabase = new MultiTenantSupabaseHelper($companyId);

    
    $tripId = $_GET['trip_id'] ?? null;
    
    
    $input = file_get_contents('php://input');
    $postData = json_decode($input, true);
    $outletFilter = $postData['outlet_filter'] ?? [];
    
    error_log("Fetching available parcels - Outlet ID: $outletId, Trip ID: $tripId, Outlet Filter: " . json_encode($outletFilter));
    
    
    $routeOutlets = [];
    
    if (!empty($outletFilter)) {
        
        $routeOutlets = array_filter($outletFilter);
        error_log("Using outlet_filter from POST: " . json_encode($routeOutlets));
    } else if ($tripId) {
        
        $tripFilter = "id=eq." . urlencode($tripId) . "&company_id=eq." . urlencode($companyId);
        $trips = $supabase->get('trips', $tripFilter);
        
        if (!empty($trips)) {
            $trip = $trips[0];
            
            
            if (!empty($trip['origin_outlet_id'])) {
                $routeOutlets[] = $trip['origin_outlet_id'];
            }
            
            
            $stopsFilter = "trip_id=eq." . urlencode($tripId) . "&order=stop_order.asc";
            $stops = $supabase->get('trip_stops', $stopsFilter);
            
            
            if (!empty($stops)) {
                foreach ($stops as $stop) {
                    if (!empty($stop['outlet_id'])) {
                        $routeOutlets[] = $stop['outlet_id'];
                    }
                }
            }
            
            
            if (!empty($trip['destination_outlet_id'])) {
                $routeOutlets[] = $trip['destination_outlet_id'];
            }
            
            $routeOutlets = array_unique($routeOutlets);
            error_log("Trip route outlets: " . json_encode($routeOutlets));
        }
    }
    
    
    if (empty($routeOutlets)) {
        $routeOutlets = [$outletId];
        error_log("No route defined, using current outlet only: $outletId");
    }
    
    // Fetch parcels - only require destination to be in route (not origin)
    // This allows parcels from any origin to be added as long as they're going to a route outlet
    $parcelsFilter = "destination_outlet_id=in.(" . implode(',', array_map('urlencode', $routeOutlets)) . ")" .
                     "&status=in.(pending,scheduled)" .
                     "&company_id=eq." . urlencode($companyId);
    
    $parcels = $supabase->get('parcels', $parcelsFilter);
    
    error_log("Found " . count($parcels) . " parcels with status pending/scheduled on route");

    if (empty($parcels)) {
        // Debug: Check if there are any parcels with any status going to route outlets
        $debugFilter = "destination_outlet_id=in.(" . implode(',', array_map('urlencode', $routeOutlets)) . ")" . 
                      "&company_id=eq." . urlencode($companyId) . 
                      "&select=id,track_number,status,origin_outlet_id,destination_outlet_id";
        $allParcels = $supabase->get('parcels', $debugFilter);
        error_log("DEBUG: Total parcels on route: " . count($allParcels));
        
        echo json_encode([
            "success" => true,
            "parcels" => [],
            "message" => "No parcels with status 'pending' or 'scheduled' found on this route",
            "debug_info" => [
                "outlet_id" => $outletId,
                "route_outlets" => $routeOutlets,
                "total_parcels_on_route" => count($allParcels),
                "parcel_statuses" => array_count_values(array_column($allParcels, 'status'))
            ]
        ]);
        exit;
    }

    
    $parcelIds = array_column($parcels, 'id');
    $parcelIdsFilter = "parcel_id=in.(" . implode(',', array_map('urlencode', $parcelIds)) . ")" .
                       "&status=not.in.(cancelled,completed)" .
                       "&company_id=eq." . urlencode($companyId);
    
    $assignedParcels = $supabase->get('parcel_list', $parcelIdsFilter);
    
    
    $assignedParcelIds = $assignedParcels ? array_column($assignedParcels, 'parcel_id') : [];

    
    $availableParcels = array_filter($parcels, function($parcel) use ($assignedParcelIds) {
        
        return !in_array($parcel['id'], $assignedParcelIds);
    });

    
    $enrichedParcels = [];
    
    
    $originOutletIds = array_filter(array_unique(array_column($availableParcels, 'origin_outlet_id')));
    $destinationOutletIds = array_filter(array_unique(array_column($availableParcels, 'destination_outlet_id')));
    $allOutletIds = array_unique(array_merge($originOutletIds, $destinationOutletIds));
    
    
    $outletsMap = [];
    if (!empty($allOutletIds)) {
        $outletsFilter = "id=in.(" . implode(',', array_map('urlencode', $allOutletIds)) . ")";
        $outletsData = $supabase->get('outlets', $outletsFilter);
        foreach ($outletsData as $outlet) {
            $outletsMap[$outlet['id']] = $outlet;
        }
    }

    foreach ($availableParcels as $parcel) {
        
        $originOutlet = null;
        if (!empty($parcel['origin_outlet_id']) && isset($outletsMap[$parcel['origin_outlet_id']])) {
            $outlet = $outletsMap[$parcel['origin_outlet_id']];
            $originOutlet = [
                'id' => $outlet['id'],
                'outlet_name' => $outlet['outlet_name'] ?? 'Unknown',
                'location' => $outlet['location'] ?? null,
                'address' => $outlet['address'] ?? null
            ];
        }
        
        
        $destinationOutlet = null;
        if (!empty($parcel['destination_outlet_id']) && isset($outletsMap[$parcel['destination_outlet_id']])) {
            $outlet = $outletsMap[$parcel['destination_outlet_id']];
            $destinationOutlet = [
                'id' => $outlet['id'],
                'outlet_name' => $outlet['outlet_name'] ?? 'Unknown',
                'location' => $outlet['location'] ?? null,
                'address' => $outlet['address'] ?? null
            ];
        }

        $enrichedParcels[] = [
            'id' => $parcel['id'],
            'track_number' => $parcel['track_number'],
            'status' => $parcel['status'],
            'sender_name' => $parcel['sender_name'] ?? 'N/A',
            'sender_email' => $parcel['sender_email'] ?? null,
            'sender_phone' => $parcel['sender_phone'] ?? null,
            'sender_address' => $parcel['sender_address'] ?? null,
            'receiver_name' => $parcel['receiver_name'] ?? 'N/A',
            'receiver_address' => $parcel['receiver_address'] ?? null,
            'receiver_phone' => $parcel['receiver_phone'] ?? null,
            'package_details' => $parcel['package_details'] ?? null,
            'parcel_weight' => $parcel['parcel_weight'] ?? 0,
            'delivery_fee' => $parcel['delivery_fee'] ?? 0,
            'parcel_value' => $parcel['parcel_value'] ?? 0,
            'cod_amount' => $parcel['cod_amount'] ?? 0,
            'delivery_option' => $parcel['delivery_option'] ?? 'standard',
            'origin_outlet_id' => $parcel['origin_outlet_id'],
            'origin_outlet' => $originOutlet,
            'destination_outlet_id' => $parcel['destination_outlet_id'],
            'destination_outlet' => $destinationOutlet,
            'created_at' => $parcel['created_at'],
            'barcode_url' => $parcel['barcode_url'] ?? null
        ];
    }

    
    usort($enrichedParcels, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    echo json_encode([
        "success" => true,
        "parcels" => array_values($enrichedParcels),
        "total_available" => count($enrichedParcels),
        "outlet_id" => $outletId,
        "trip_id" => $tripId,
        "filtered_by_route" => true,
        "route_outlets" => $routeOutlets,
        "valid_destinations" => $routeOutlets
    ]);

} catch (Exception $e) {
    error_log("Error in fetch_available_parcels.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to fetch available parcels: " . $e->getMessage()
    ]);
}
?>
