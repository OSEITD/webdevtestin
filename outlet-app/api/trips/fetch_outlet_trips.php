<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

header("Cache-Control: private, max-age=30"); 
header("Vary: Cookie"); 

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
    
    error_log("Fetch outlet trips API called");
    
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    $outletId = $_SESSION['outlet_id'] ?? null;

    error_log("Session data: userId=$userId, companyId=$companyId, outletId=$outletId");

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

    
    $profileQuery = $supabase->get('profiles', 'id=eq.' . urlencode($userId) . '&select=id,role,outlet_id');
    $profile = !empty($profileQuery) ? $profileQuery[0] : null;
    $userRole = $_SESSION['role'] ?? ($profile['role'] ?? null);

    
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $page_size = intval($_GET['page_size'] ?? 50);
    if ($page_size < 10) $page_size = 10;
    if ($page_size > 200) $page_size = 200;
    $offset = ($page - 1) * $page_size;

    // ── Collect relevant trip IDs from ALL three sources ────────────────────
    // 1. Trips passing through this outlet as an intermediate stop
    $stopTripIds = [];
    $tripStops = $supabase->get(
        'trip_stops',
        "outlet_id=eq." . urlencode($outletId) .
        "&company_id=eq." . urlencode($companyId) .
        "&select=trip_id"
    );
    if (!empty($tripStops)) {
        $stopTripIds = array_column($tripStops, 'trip_id');
    }

    // 2. Trips that originate from OR are destined for this outlet
    $directTrips = $supabase->get(
        'trips',
        "company_id=eq." . urlencode($companyId) .
        "&or=(origin_outlet_id.eq." . urlencode($outletId) .
        ",destination_outlet_id.eq." . urlencode($outletId) . ")" .
        "&select=id"
    );
    $directTripIds = !empty($directTrips) ? array_column($directTrips, 'id') : [];

    // 3. Merge and deduplicate
    $relevantTripIds = array_values(array_unique(array_merge($stopTripIds, $directTripIds)));

    if (empty($relevantTripIds)) {
        echo json_encode([
            "success" => true,
            "trips"   => [],
            "message" => "No trips involving this outlet were found."
        ]);
        exit;
    }

    // Fetch full trip records with pagination
    $allMatchedTrips = $supabase->get(
        'trips',
        "id=in.(" . implode(',', array_map('urlencode', $relevantTripIds)) . ")" .
        "&company_id=eq." . urlencode($companyId) .
        "&order=created_at.desc"
    );

    $trips = array_slice($allMatchedTrips ?? [], $offset, $page_size);

    if (empty($trips)) {
        echo json_encode([
            "success" => true,
            "trips" => [],
            "message" => "No trips data found"
        ]);
        exit;
    }

    
    $tripIds = array_column($trips, 'id');

    
    
    
    $allStopsFilter = "trip_id=in.(" . implode(',', array_map('urlencode', $tripIds)) . ")&order=trip_id.asc,stop_order.asc";
    $allStops = $supabase->get('trip_stops', $allStopsFilter);
    
    
    $stopsByTrip = [];
    $allOutletIds = [];
    foreach ($allStops as $stop) {
        $stopsByTrip[$stop['trip_id']][] = $stop;
        $allOutletIds[] = $stop['outlet_id'];
    }
    
    // Also include origin and destination outlets from trips (in case they don't have trip_stops)
    foreach ($trips as $trip) {
        if (!empty($trip['origin_outlet_id'])) {
            $allOutletIds[] = $trip['origin_outlet_id'];
        }
        if (!empty($trip['destination_outlet_id'])) {
            $allOutletIds[] = $trip['destination_outlet_id'];
        }
    }
    
    $allOutletIds = array_unique($allOutletIds);
    
    
    $outletsMap = [];
    if (!empty($allOutletIds)) {
        $outletsFilter = "id=in.(" . implode(',', array_map('urlencode', $allOutletIds)) . ")";
        $outletsData = $supabase->get('outlets', $outletsFilter);
        foreach ($outletsData as $outlet) {
            $outletsMap[$outlet['id']] = $outlet;
        }
    }
    
    
    $driverIds = array_filter(array_unique(array_column($trips, 'driver_id')));
    $driversMap = [];
    if (!empty($driverIds)) {
        $driversFilter = "id=in.(" . implode(',', array_map('urlencode', $driverIds)) . ")";
        $driversData = $supabase->get('drivers', $driversFilter);
        foreach ($driversData as $driver) {
            $driversMap[$driver['id']] = $driver;
        }
    }
    
    
    $vehicleIds = array_filter(array_unique(array_column($trips, 'vehicle_id')));
    $vehiclesMap = [];
    if (!empty($vehicleIds)) {
        $vehiclesFilter = "id=in.(" . implode(',', array_map('urlencode', $vehicleIds)) . ")";
        $vehiclesData = $supabase->get('vehicle', $vehiclesFilter);
        foreach ($vehiclesData as $vehicle) {
            $vehiclesMap[$vehicle['id']] = $vehicle;
        }
    }
    
    
    $parcelListFilter = "trip_id=in.(" . implode(',', array_map('urlencode', $tripIds)) . ")" .
                        "&company_id=eq." . urlencode($companyId) .
                        "&select=id,trip_id,status,parcel_id,outlet_id,trip_stop_id";
    $allParcelListEntries = $supabase->get('parcel_list', $parcelListFilter);
    
    
    $parcelListByTrip = [];
    $allParcelIds = [];
    foreach ($allParcelListEntries as $entry) {
        $parcelListByTrip[$entry['trip_id']][] = $entry;
        if (!empty($entry['parcel_id'])) {
            $allParcelIds[] = $entry['parcel_id'];
        }
    }
    $allParcelIds = array_unique($allParcelIds);
    
    
    $parcelsMap = [];
    if (!empty($allParcelIds)) {
        $parcelsFilter = "id=in.(" . implode(',', array_map('urlencode', $allParcelIds)) . ")" .
                        "&select=id,track_number,status,sender_name,receiver_name,receiver_address,receiver_phone," .
                        "parcel_weight,delivery_fee,origin_outlet_id,destination_outlet_id,barcode_url";
        $parcelsData = $supabase->get('parcels', $parcelsFilter);
        foreach ($parcelsData as $parcel) {
            $parcelsMap[$parcel['id']] = $parcel;
        }
    }
    
    
    $enrichedTrips = [];
    
    foreach ($trips as $trip) {
        $tripId = $trip['id'];
        
        
        $enrichedStops = [];
        $stopsForTrip = $stopsByTrip[$tripId] ?? [];
        
        // If no stops exist in trip_stops table, create default stops from origin/destination
        if (empty($stopsForTrip)) {
            $stopOrder = 1;
            
            // Add origin outlet as first stop
            if (!empty($trip['origin_outlet_id'])) {
                $originOutlet = $outletsMap[$trip['origin_outlet_id']] ?? null;
                if ($originOutlet) {
                    $enrichedStops[] = [
                        'id' => null, // No actual trip_stop record exists
                        'stop_order' => $stopOrder++,
                        'arrival_time' => $trip['created_at'] ?? null, // Mark as "arrived" since trip starts here
                        'departure_time' => $trip['departure_time'] ?? null, // From trips table
                        'outlet' => [
                            'id' => $originOutlet['id'],
                            'outlet_name' => $originOutlet['outlet_name'] ?? 'Unknown Outlet',
                            'location' => $originOutlet['location'] ?? null,
                            'address' => $originOutlet['address'] ?? null
                        ]
                    ];
                }
            }
            
            // Add destination outlet as final stop (if different from origin)
            if (!empty($trip['destination_outlet_id']) && $trip['destination_outlet_id'] !== $trip['origin_outlet_id']) {
                $destOutlet = $outletsMap[$trip['destination_outlet_id']] ?? null;
                if ($destOutlet) {
                    $enrichedStops[] = [
                        'id' => null, // No actual trip_stop record exists
                        'stop_order' => $stopOrder++,
                        'arrival_time' => $trip['arrival_time'] ?? null, // From trips table
                        'departure_time' => null, // Trip ends here, no departure
                        'outlet' => [
                            'id' => $destOutlet['id'],
                            'outlet_name' => $destOutlet['outlet_name'] ?? 'Unknown Outlet',
                            'location' => $destOutlet['location'] ?? null,
                            'address' => $destOutlet['address'] ?? null
                        ]
                    ];
                }
            }
        } else {
            // Process existing stops from trip_stops table
            foreach ($stopsForTrip as $stop) {
                $outlet = $outletsMap[$stop['outlet_id']] ?? null;
                $enrichedStops[] = [
                    'id' => $stop['id'],
                    'stop_order' => $stop['stop_order'],
                    'arrival_time' => $stop['arrival_time'],
                    'departure_time' => $stop['departure_time'],
                    'outlet' => $outlet ? [
                        'id' => $outlet['id'],
                        'outlet_name' => $outlet['outlet_name'] ?? 'Unknown Outlet',
                        'location' => $outlet['location'] ?? null,
                        'address' => $outlet['address'] ?? null
                    ] : null
                ];
            }
        }
        
        
        $driverInfo = null;
        if (!empty($trip['driver_id']) && isset($driversMap[$trip['driver_id']])) {
            $driver = $driversMap[$trip['driver_id']];
            $driverInfo = [
                'id' => $driver['id'],
                'driver_name' => $driver['driver_name'] ?? 'Unknown Driver',
                'driver_phone' => $driver['driver_phone'] ?? null,
                'status' => $driver['status'] ?? 'unknown'
            ];
        }
        
        
        $vehicleInfo = null;
        if (!empty($trip['vehicle_id']) && isset($vehiclesMap[$trip['vehicle_id']])) {
            $vehicle = $vehiclesMap[$trip['vehicle_id']];
            $vehicleInfo = [
                'id' => $vehicle['id'],
                'name' => $vehicle['name'] ?? 'Unknown Vehicle',
                'plate_number' => $vehicle['plate_number'] ?? null,
                'status' => $vehicle['status'] ?? 'unknown'
            ];
        }
        
        
        $parcels = [];
        $parcelListEntries = $parcelListByTrip[$tripId] ?? [];
        foreach ($parcelListEntries as $entry) {
            if (!empty($entry['parcel_id']) && isset($parcelsMap[$entry['parcel_id']])) {
                $parcel = $parcelsMap[$entry['parcel_id']];
                
                
                $originOutletName = 'Unknown';
                if (!empty($parcel['origin_outlet_id']) && isset($outletsMap[$parcel['origin_outlet_id']])) {
                    $originOutletName = $outletsMap[$parcel['origin_outlet_id']]['outlet_name'] ?? 'Unknown';
                }
                
                
                $destOutletName = 'Unknown';
                if (!empty($parcel['destination_outlet_id']) && isset($outletsMap[$parcel['destination_outlet_id']])) {
                    $destOutletName = $outletsMap[$parcel['destination_outlet_id']]['outlet_name'] ?? 'Unknown';
                }
                
                $parcels[] = [
                    'id' => $parcel['id'],
                    'parcel_list_id' => $entry['id'] ?? null, // ID from parcel_list table for removal
                    'track_number' => $parcel['track_number'] ?? 'N/A',
                    'status' => $parcel['status'] ?? 'pending',
                    'sender_name' => $parcel['sender_name'] ?? 'N/A',
                    'receiver_name' => $parcel['receiver_name'] ?? 'N/A',
                    'receiver_address' => $parcel['receiver_address'] ?? null,
                    'receiver_phone' => $parcel['receiver_phone'] ?? null,
                    'parcel_weight' => $parcel['parcel_weight'] ?? 0,
                    'delivery_fee' => $parcel['delivery_fee'] ?? 0,
                    'origin_outlet_id' => $parcel['origin_outlet_id'] ?? null,
                    'destination_outlet_id' => $parcel['destination_outlet_id'] ?? null,
                    'origin_outlet_name' => $originOutletName,
                    'destination_outlet_name' => $destOutletName,
                    'parcel_list_status' => $entry['status'] ?? 'pending',
                    'barcode_url' => $parcel['barcode_url'] ?? null
                ];
            }
        }
        
        
        $isOriginOutlet = (!empty($trip['origin_outlet_id']) && $trip['origin_outlet_id'] === $outletId);

        
        $isPartOfRoute = false;
        
        foreach ($enrichedStops as $stop) {
            if (!empty($stop['outlet']) && $stop['outlet']['id'] === $outletId) {
                $isPartOfRoute = true;
                break;
            }
        }
        
        if (!$isPartOfRoute && !empty($trip['destination_outlet_id']) && $trip['destination_outlet_id'] === $outletId) {
            $isPartOfRoute = true;
        }

        $filteredParcels = $parcels;

        // Determine which stop number this outlet is on the route
        $outletStopOrder = null;
        foreach ($enrichedStops as $s) {
            if (!empty($s['outlet']['id']) && $s['outlet']['id'] === $outletId) {
                $outletStopOrder = $s['stop_order'];
                break;
            }
        }

        $enrichedTrips[] = [
            'id' => $trip['id'],
            'trip_status' => $trip['trip_status'] ?? 'scheduled',
            'departure_time' => $trip['departure_time'] ?? null,
            'arrival_time' => $trip['arrival_time'] ?? null,
            'trip_date' => $trip['trip_date'] ?? null,
            'created_at' => $trip['created_at'] ?? null,
            'updated_at' => $trip['updated_at'] ?? null,
            // Verification fields
            'driver_completed'    => (bool)($trip['driver_completed'] ?? false),
            'driver_completed_at' => $trip['driver_completed_at'] ?? null,
            'manager_verified'    => (bool)($trip['manager_verified'] ?? false),
            'manager_verified_at' => $trip['manager_verified_at'] ?? null,
            'manager_verified_by' => $trip['manager_verified_by'] ?? null,
            //
            'outlet_manager_id' => $trip['outlet_manager_id'] ?? null,
            'driver' => $driverInfo,
            'vehicle' => $vehicleInfo,
            'stops' => $enrichedStops,
            'parcels' => $filteredParcels,
            'total_parcels' => count($filteredParcels),
            'is_origin_outlet'  => $isOriginOutlet,
            'is_part_of_route'  => $isPartOfRoute,
            'outlet_stop_order' => $outletStopOrder
        ];
    }

    
    usort($enrichedTrips, function($a, $b) {
        $timeA = strtotime($a['departure_time'] ?? '1970-01-01');
        $timeB = strtotime($b['departure_time'] ?? '1970-01-01');
        return $timeB - $timeA;
    });

    
    $returnedCount  = count($enrichedTrips);
    $totalMatched   = count($allMatchedTrips ?? []);
    $isLastPage     = ($offset + $returnedCount) >= $totalMatched;

    
    foreach ($enrichedTrips as &$et) {
        
        $et['authorized_actions'] = [
            'can_start' => false,
            'can_complete' => false
        ];

        
        $tripAllowed = false;
        
        if ($profile && isset($profile['role']) && $profile['role'] === 'outlet_manager') {
            
            foreach ($et['stops'] as $scheck) {
                if (isset($scheck['outlet']) && isset($scheck['outlet']['id']) && $scheck['outlet']['id'] == ($profile['outlet_id'] ?? null)) {
                    $tripAllowed = true;
                    break;
                }
            }
        }

        
        if (!$tripAllowed) {
            foreach ($et['stops'] as $scheck) {
                if (isset($scheck['outlet']) && isset($scheck['outlet']['manager_id']) && $scheck['outlet']['manager_id'] == $userId) {
                    $tripAllowed = true;
                    break;
                }
            }
        }

        
        if (!$tripAllowed && isset($et['outlet_manager_id']) && $et['outlet_manager_id'] == $userId) {
            $tripAllowed = true;
        }

        
        if (!$tripAllowed && $userRole === 'super_admin') {
            $tripAllowed = true;
        }

        
        if (!empty($et['is_origin_outlet']) && in_array($et['trip_status'], ['scheduled','accepted']) && $tripAllowed) {
            $et['authorized_actions']['can_start'] = true;
        }

        
        if ($tripAllowed && $et['trip_status'] !== 'completed') {
            $et['authorized_actions']['can_complete'] = true;
        }

        // Per-stop authorization: a manager can arrive/depart only at stops that
        // belong to their own outlet (or super_admin can act on any stop).
        foreach ($et['stops'] as &$s) {
            $stopOutletId = $s['outlet']['id'] ?? null;
            $stopAllowed  = $userRole === 'super_admin'
                         || ($stopOutletId && $stopOutletId === $outletId)
                         || $tripAllowed; // trip-level authorization also grants stop access

            $s['authorized_actions'] = [
                'stop_allowed' => $stopAllowed,
                'can_arrive'   => $stopAllowed && !empty($s['id']) && empty($s['arrival_time']),
                'can_depart'   => $stopAllowed && !empty($s['id']) && !empty($s['arrival_time']) && empty($s['departure_time']),
            ];
        }
        unset($s);
    }

    echo json_encode([
        "success"        => true,
        "trips"          => $enrichedTrips,
        "outlet_id"      => $outletId,
        "page"           => $page,
        "page_size"      => $page_size,
        "returned"       => $returnedCount,
        "total_matching" => $totalMatched,
        "is_last_page"   => $isLastPage
    ]);

} catch (Exception $e) {
    error_log("Error in fetch_outlet_trips.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to fetch trips: " . $e->getMessage()
    ]);
}
?>
