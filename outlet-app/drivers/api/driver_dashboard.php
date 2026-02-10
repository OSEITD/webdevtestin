<?php
require_once __DIR__ . '/error_handler.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once '../../includes/OutletAwareSupabaseHelper.php';
require_once '../../includes/ResponseCache.php';
session_start();
if (!ob_start('ob_gzhandler')) ob_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$driver_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$cache = new ResponseCache(null, 15); // Reduced cache time for faster updates
$cacheKey = "driver_dashboard_{$driver_id}_{$company_id}";

// Allow cache bypass with ?nocache=1 (used after trip start for fresh data)
$noCache = isset($_GET['nocache']) && $_GET['nocache'] == '1';

if (!$noCache) {
    $cachedResponse = $cache->get($cacheKey);
    if ($cachedResponse !== null) {
        echo json_encode($cachedResponse);
        exit;
    }
}
$supabase = new OutletAwareSupabaseHelper();
try {
    // OPTIMIZATION: Fetch active trips (accepted,in_transit) and scheduled trips separately
    $baseSelect = 'id,trip_status,departure_time,arrival_time,trip_date,vehicle_id,outlet_manager_id,origin_outlet_id,destination_outlet_id,created_at,updated_at';

    $companyCond = !empty($company_id) ? '&company_id=eq.' . urlencode($company_id) : '';

    $activeFilter = 'driver_id=eq.' . urlencode($driver_id) .
                    $companyCond .
                    '&trip_status=in.(accepted,in_transit)' .
                    '&select=' . $baseSelect;
    $activeTrips = $supabase->get('trips', $activeFilter);

    $scheduledFilter = 'driver_id=eq.' . urlencode($driver_id) .
                       $companyCond .
                       '&trip_status=eq.scheduled' .
                       '&select=' . $baseSelect;
    $scheduledTrips = $supabase->get('trips', $scheduledFilter);

    // Normalize to arrays
    if (!is_array($activeTrips)) $activeTrips = [];
    if (!is_array($scheduledTrips)) $scheduledTrips = [];

    error_log("Driver dashboard - found active trips: " . count($activeTrips) . ", scheduled trips: " . count($scheduledTrips));

    // Combine for enrichment processing
    $trips = array_values(array_merge($activeTrips, $scheduledTrips));

    if (empty($trips)) {
        echo json_encode([
            'success' => true,
            'active_trips' => [],
            'upcoming_trips' => [],
            'completed_trips' => [],
            'performance' => [
                'trips_today' => 0,
                'trips_week' => 0,
                'parcels_delivered' => 0,
                'parcels_returned' => 0
            ]
        ]);
        exit;
    }

    $tripIds = array_column($trips, 'id');
    
    // OPTIMIZATION: Batch fetch all related data in parallel
    $vehicleIds = array_unique(array_filter(array_column($trips, 'vehicle_id')));
    $managerIds = array_unique(array_filter(array_column($trips, 'outlet_manager_id')));
    $outletIds = [];
    
    // Collect origin and destination outlet IDs
    foreach ($trips as $trip) {
        if (!empty($trip['origin_outlet_id'])) {
            $outletIds[] = $trip['origin_outlet_id'];
        }
        if (!empty($trip['destination_outlet_id'])) {
            $outletIds[] = $trip['destination_outlet_id'];
        }
    }
    
    // OPTIMIZATION: Batch fetch trip_stops for all trips in ONE query
    $allTripStops = [];
    $tripStopsMap = [];
    $stopParcelCounts = [];
    
    if (!empty($tripIds)) {
        $tripIdsStr = implode(',', array_map('urlencode', $tripIds));
        $stopsQuery = "trip_id=in.($tripIdsStr)&order=stop_order.asc&select=id,trip_id,outlet_id,stop_order,arrival_time,departure_time";
        $allTripStops = $supabase->get('trip_stops', $stopsQuery);
        
        if (!is_array($allTripStops)) {
            $allTripStops = [];
        }
        
        // Build trip stops map and collect outlet IDs from stops
        foreach ($allTripStops as $stop) {
            $tripStopsMap[$stop['trip_id']][] = $stop;
            if (!empty($stop['outlet_id'])) {
                $outletIds[] = $stop['outlet_id'];
            }
        }
    }
    
    // OPTIMIZATION: Fetch all outlets in one query
    $outletMap = [];
    if (!empty($outletIds)) {
        $outletIdsStr = implode(',', array_map('urlencode', array_unique($outletIds)));
        $outlets = $supabase->get('outlets', "id=in.($outletIdsStr)&select=id,outlet_name,address,latitude,longitude");
        if (is_array($outlets)) {
            foreach ($outlets as $outlet) {
                $outletMap[$outlet['id']] = $outlet;
            }
        }
    }
    
    // OPTIMIZATION: Fetch vehicles in one query
    $vehicleMap = [];
    if (!empty($vehicleIds)) {
        $vehicleIdsStr = implode(',', array_map('urlencode', $vehicleIds));
        $vehicles = $supabase->get('vehicle', "id=in.($vehicleIdsStr)&select=id,name,plate_number,status");
        if (is_array($vehicles)) {
            foreach ($vehicles as $vehicle) {
                $vehicleMap[$vehicle['id']] = $vehicle;
            }
        }
    }
    
    // OPTIMIZATION: Fetch profiles in one query
    $profileMap = [];
    if (!empty($managerIds)) {
        $managerIdsStr = implode(',', array_map('urlencode', $managerIds));
        $profiles = $supabase->get('profiles', "id=in.($managerIdsStr)&select=id,full_name,outlet_id");
        if (is_array($profiles)) {
            foreach ($profiles as $profile) {
                $profileMap[$profile['id']] = $profile;
                if (!empty($profile['outlet_id']) && !isset($outletMap[$profile['outlet_id']])) {
                    $outletIds[] = $profile['outlet_id'];
                }
            }
        }
    }
    
    // Fetch any additional outlets from profiles
    if (!empty($outletIds)) {
        $additionalOutletIds = array_diff(array_unique($outletIds), array_keys($outletMap));
        if (!empty($additionalOutletIds)) {
            $additionalIdsStr = implode(',', array_map('urlencode', $additionalOutletIds));
            $additionalOutlets = $supabase->get('outlets', "id=in.($additionalIdsStr)&select=id,outlet_name,address,latitude,longitude");
            if (is_array($additionalOutlets)) {
                foreach ($additionalOutlets as $outlet) {
                    $outletMap[$outlet['id']] = $outlet;
                }
            }
        }
    }
    
    // OPTIMIZATION: Fetch parcel counts in one query
    $parcelCounts = [];
    $deliveredCounts = [];
    if (!empty($tripIds)) {
        $tripIdsStr = implode(',', array_map('urlencode', $tripIds));
        $parcelListData = $supabase->get('parcel_list', "trip_id=in.($tripIdsStr)&select=trip_id,trip_stop_id,status");
        if (is_array($parcelListData)) {
            foreach ($parcelListData as $parcel) {
                $tripId = $parcel['trip_id'];
                if (!isset($parcelCounts[$tripId])) {
                    $parcelCounts[$tripId] = 0;
                    $deliveredCounts[$tripId] = 0;
                }
                $parcelCounts[$tripId]++;
                if ($parcel['status'] === 'completed') {
                    $deliveredCounts[$tripId]++;
                }
                if (!empty($parcel['trip_stop_id'])) {
                    $stopId = $parcel['trip_stop_id'];
                    $stopParcelCounts[$stopId] = ($stopParcelCounts[$stopId] ?? 0) + 1;
                }
            }
        }
    }
    
    // Build enriched trips
    $active = [];
    $upcoming = [];
    $completed = [];
    foreach ($trips as $trip) {
        $trip['status'] = $trip['trip_status'];
        if (!empty($trip['vehicle_id']) && isset($vehicleMap[$trip['vehicle_id']])) {
            $trip['vehicle'] = $vehicleMap[$trip['vehicle_id']];
        }
        if (!empty($trip['outlet_manager_id']) && isset($profileMap[$trip['outlet_manager_id']])) {
            $manager = $profileMap[$trip['outlet_manager_id']];
            $trip['dispatcher_name'] = $manager['full_name'] ?? 'N/A';
            if (!empty($manager['outlet_id']) && isset($outletMap[$manager['outlet_id']])) {
                $trip['outlet_name'] = $outletMap[$manager['outlet_id']]['outlet_name'] ?? '-';
            }
        }
        $trip['parcels_count'] = $parcelCounts[$trip['id']] ?? 0;
        $trip['delivered_count'] = $deliveredCounts[$trip['id']] ?? 0;
        $parcelCount = $trip['parcels_count'];
        $trip['priority'] = $parcelCount >= 20 ? 'urgent' :
                           ($parcelCount >= 10 ? 'high' :
                           ($parcelCount >= 5 ? 'medium' : 'normal'));
        
        // Add origin and destination outlet information
        if (!empty($trip['origin_outlet_id']) && isset($outletMap[$trip['origin_outlet_id']])) {
            $originOutlet = $outletMap[$trip['origin_outlet_id']];
            $trip['origin_outlet_name'] = $originOutlet['outlet_name'];
            $trip['origin_name'] = $originOutlet['outlet_name'];
            $trip['origin_location'] = $originOutlet['address'] ?? '';
            $trip['origin_latitude'] = $originOutlet['latitude'];
            $trip['origin_longitude'] = $originOutlet['longitude'];
        }
        
        if (!empty($trip['destination_outlet_id']) && isset($outletMap[$trip['destination_outlet_id']])) {
            $destinationOutlet = $outletMap[$trip['destination_outlet_id']];
            $trip['destination_outlet_name'] = $destinationOutlet['outlet_name'];
            $trip['destination_name'] = $destinationOutlet['outlet_name'];
            $trip['destination_location'] = $destinationOutlet['address'] ?? '';
            $trip['destination_latitude'] = $destinationOutlet['latitude'];
            $trip['destination_longitude'] = $destinationOutlet['longitude'];
        }
        
        $routeInfo = [];
        $routeStopsWithCoords = [];
        $tripStopData = $tripStopsMap[$trip['id']] ?? [];
        if (empty($tripStopData) && $trip['trip_status'] === 'in_transit') {
            $tripStopData = createTripStopsFromParcels($trip['id'], $parcelListData, $supabase);
            $tripStopsMap[$trip['id']] = $tripStopData;
        }
        foreach ($tripStopData as $stop) {
            if (!empty($stop['outlet_id']) && isset($outletMap[$stop['outlet_id']])) {
                $outlet = $outletMap[$stop['outlet_id']];
                $routeInfo[] = $outlet['outlet_name'];
                $status = !empty($stop['departure_time']) ? 'completed' :
                         (!empty($stop['arrival_time']) ? 'at_stop' : 'pending');
                $routeStopsWithCoords[] = [
                    'id' => $stop['id'],
                    'outlet_id' => $outlet['id'],
                    'outlet_name' => $outlet['outlet_name'],
                    'address' => $outlet['address'] ?? '',
                    'latitude' => $outlet['latitude'],
                    'longitude' => $outlet['longitude'],
                    'stop_order' => $stop['stop_order'],
                    'arrival_time' => $stop['arrival_time'] ?? null,
                    'departure_time' => $stop['departure_time'] ?? null,
                    'parcel_count' => $stopParcelCounts[$stop['id']] ?? 0,
                    'status' => $status
                ];
            }
        }
        $trip['route'] = implode(' → ', $routeInfo);
        $trip['route_stops'] = $routeInfo;
        $trip['route_stops_with_coords'] = $routeStopsWithCoords;
        $stopCount = count($tripStopData);
        $trip['total_distance'] = $stopCount > 0 ? ($stopCount * 5) . ' km' : 'Calculating...';
        $trip['estimated_duration'] = $stopCount > 0 ? ($stopCount * 20) . ' min' : 'Calculating...';
        $trip['estimated_arrival'] = $trip['departure_time'] ?
            date('Y-m-d H:i:s', strtotime($trip['departure_time'] . ' +' . ($stopCount * 20) . ' minutes')) :
            null;
        switch ($trip['trip_status']) {
            case 'accepted':
            case 'in_transit': $active[] = $trip; break;
            case 'scheduled': $upcoming[] = $trip; break;
            case 'completed': $completed[] = $trip; break;
        }
    }
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $qps = $supabase->get('driver_qps', 'driver_id=eq.' . urlencode($driver_id) . '&company_id=eq.' . urlencode($company_id) . '&date=gte.' . urlencode($week_start));
    $trips_today = 0;
    $trips_week = 0;
    $parcels_delivered = 0;
    $parcels_returned = 0;

    if (is_array($qps) && !empty($qps)) {
        foreach ($qps as $row) {
            if ($row['date'] === $today) $trips_today += intval($row['trips_completed'] ?? 0);
            $trips_week += intval($row['trips_completed'] ?? 0);
            $parcels_delivered += intval($row['parcels_handled'] ?? 0);
        }
    } else {
        // Fallback: compute using detailed queries when driver_qps is empty or unavailable
        try {
            $tripsToday = $supabase->get('trips',
                'driver_id=eq.' . urlencode($driver_id) . '&company_id=eq.' . urlencode($company_id) . '&trip_status=eq.completed&updated_at=gte.' . urlencode($today . "T00:00:00") . '&updated_at=lte.' . urlencode($today . "T23:59:59"),
                'id'
            );
            $trips_today = is_array($tripsToday) ? count($tripsToday) : 0;

            $tripsWeek = $supabase->get('trips',
                'driver_id=eq.' . urlencode($driver_id) . '&company_id=eq.' . urlencode($company_id) . '&trip_status=eq.completed&updated_at=gte.' . urlencode($week_start . "T00:00:00") . '&updated_at=lte.' . urlencode($today . "T23:59:59"),
                'id'
            );
            $trips_week = is_array($tripsWeek) ? count($tripsWeek) : 0;

            $parcelsDelivered = $supabase->get('parcels',
                'driver_id=eq.' . urlencode($driver_id) . '&company_id=eq.' . urlencode($company_id) . '&status=eq.delivered&updated_at=gte.' . urlencode($week_start . "T00:00:00"),
                'id'
            );
            $parcels_delivered = is_array($parcelsDelivered) ? count($parcelsDelivered) : 0;

            $parcelsReturned = $supabase->get('parcels',
                'driver_id=eq.' . urlencode($driver_id) . '&company_id=eq.' . urlencode($company_id) . '&status=eq.returned&updated_at=gte.' . urlencode($week_start . "T00:00:00"),
                'id'
            );
            $parcels_returned = is_array($parcelsReturned) ? count($parcelsReturned) : 0;
        } catch (Exception $e) {
            error_log('Driver dashboard performance fallback error: ' . $e->getMessage());
            // leave as zeros on failure
        }
    }
    $response = [
        'success' => true,
        'active_trips' => $active,
        'upcoming_trips' => $upcoming,
        'completed_trips' => $completed,
        'performance' => [
            'trips_today' => $trips_today,
            'trips_week' => $trips_week,
            'parcels_delivered' => $parcels_delivered,
            'parcels_returned' => $parcels_returned
        ]
    ];
    $cache->set($cacheKey, $response);
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
function createTripStopsFromParcels($tripId, $parcelListData, $supabase) {
    $tripParcels = array_filter($parcelListData, function($parcel) use ($tripId) {
        return $parcel['trip_id'] === $tripId;
    });
    if (empty($tripParcels)) {
        return [];
    }
    $destinationOutlets = [];
    foreach ($tripParcels as $parcel) {
        if ($parcel['outlet_id'] && !in_array($parcel['outlet_id'], $destinationOutlets)) {
            $destinationOutlets[] = $parcel['outlet_id'];
        }
    }
    if (empty($destinationOutlets)) {
        return [];
    }
    $tripData = $supabase->get('trips', 'id=eq.' . urlencode($tripId));
    if (empty($tripData)) {
        return [];
    }
    $companyId = $tripData[0]['company_id'];
    $createdStops = [];
    $stopOrder = 1;
    foreach ($destinationOutlets as $outletId) {
        $stopData = [
            'trip_id' => $tripId,
            'outlet_id' => $outletId,
            'stop_order' => $stopOrder,
            'company_id' => $companyId
        ];
        try {
            $result = $supabase->insert('trip_stops', $stopData);
            if ($result && !empty($result)) {
                $newStop = $result[0];
                $createdStops[] = $newStop;
                $stopId = $newStop['id'];
                foreach ($tripParcels as $parcel) {
                    if ($parcel['outlet_id'] === $outletId) {
                        $supabase->update(
                            'parcel_list',
                            ['trip_stop_id' => $stopId],
                            'id=eq.' . urlencode($parcel['id'])
                        );
                    }
                }
                $stopOrder++;
            }
        } catch (Exception $e) {
            error_log("Failed to create trip stop: " . $e->getMessage());
        }
    }
    return $createdStops;
}
?>
