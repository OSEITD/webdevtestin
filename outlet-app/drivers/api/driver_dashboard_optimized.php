<?php
require_once '../../includes/OutletAwareSupabaseHelper.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$driver_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$CACHE_DURATION = 300;
$supabase = new OutletAwareSupabaseHelper();
try {
    
    $cacheKey = "driver_dashboard_cache_{$company_id}";
    $cacheTime = $_SESSION["{$cacheKey}_time"] ?? 0;
    $currentTime = time();
    
    if (($currentTime - $cacheTime) > $CACHE_DURATION || !isset($_SESSION[$cacheKey])) {
       
        $vehicles = $supabase->get('vehicle', 'company_id=eq.' . urlencode($company_id));
        $profiles = $supabase->get('profiles', 'company_id=eq.' . urlencode($company_id));
        $outlets = $supabase->get('outlets', 'company_id=eq.' . urlencode($company_id));
        
        $vehicleMap = array_column($vehicles, null, 'id');
        $profileMap = array_column($profiles, null, 'id');
        $outletMap = array_column($outlets, null, 'id');
       
        $_SESSION[$cacheKey] = [
            'vehicles' => $vehicleMap,
            'profiles' => $profileMap,
            'outlets' => $outletMap
        ];
        $_SESSION["{$cacheKey}_time"] = $currentTime;
        
        $cachedData = $_SESSION[$cacheKey];
    } else {
       
        $cachedData = $_SESSION[$cacheKey];
    }
    
    $vehicleMap = $cachedData['vehicles'];
    $profileMap = $cachedData['profiles'];
    $outletMap = $cachedData['outlets'];
   
    $trips = $supabase->get('trips', 
        'driver_id=eq.' . urlencode($driver_id) . 
        '&company_id=eq.' . urlencode($company_id) .
        '&order=departure_time.desc'
    );
  
    $tripIds = array_column($trips, 'id');
    $tripIdsStr = implode(',', array_map('urlencode', $tripIds));
    $tripStops = [];
    
    if (!empty($tripIds)) {
        $tripStops = $supabase->get('trip_stops', 
            'trip_id=in.(' . $tripIdsStr . ')&order=trip_id,stop_order.asc&select=*'
        );
    }
    
    $tripStopsMap = [];
    foreach ($tripStops as $stop) {
        if (!isset($tripStopsMap[$stop['trip_id']])) {
            $tripStopsMap[$stop['trip_id']] = [];
        }
        $tripStopsMap[$stop['trip_id']][] = $stop;
    }
   
    $parcelListData = [];
    if (!empty($tripIds)) {
        $parcelListData = $supabase->get('parcel_list', 
            'trip_id=in.(' . $tripIdsStr . ')&select=trip_id,trip_stop_id,status,parcel_id'
        );
    }
    $parcelCounts = [];
    $deliveredCounts = [];
    $stopParcelCounts = [];
    
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
      
        if (isset($parcel['trip_stop_id'])) {
            $stopId = $parcel['trip_stop_id'];
            if (!isset($stopParcelCounts[$stopId])) {
                $stopParcelCounts[$stopId] = 0;
            }
            $stopParcelCounts[$stopId]++;
        }
    }
    
    $active = [];
    $upcoming = [];
    $completed = [];
    
    foreach ($trips as $trip) {
   
        $trip['status'] = $trip['trip_status'];
        
        if (isset($trip['vehicle_id']) && isset($vehicleMap[$trip['vehicle_id']])) {
            $trip['vehicle_name'] = $vehicleMap[$trip['vehicle_id']]['name'] ?? 'N/A';
            $trip['vehicle_plate'] = $vehicleMap[$trip['vehicle_id']]['plate_number'] ?? 'N/A';
        }
        
        if (isset($trip['outlet_manager_id']) && isset($profileMap[$trip['outlet_manager_id']])) {
            $manager = $profileMap[$trip['outlet_manager_id']];
            $trip['dispatcher_name'] = $manager['full_name'] ?? 'N/A';
            
            if (isset($manager['outlet_id']) && isset($outletMap[$manager['outlet_id']])) {
                $trip['outlet_name'] = $outletMap[$manager['outlet_id']]['outlet_name'] ?? '-';
            }
        }
        
      
        $trip['parcels_count'] = $parcelCounts[$trip['id']] ?? 0;
        $trip['parcel_count'] = $trip['parcels_count']; 
        $trip['delivered_count'] = $deliveredCounts[$trip['id']] ?? 0;
        
        
        $parcelCount = $trip['parcels_count'];
        if ($parcelCount >= 20) {
            $trip['priority'] = 'urgent';
        } elseif ($parcelCount >= 10) {
            $trip['priority'] = 'high';
        } elseif ($parcelCount >= 5) {
            $trip['priority'] = 'medium';
        } else {
            $trip['priority'] = 'normal';
        }
        
        $routeInfo = [];
        $routeStopsWithCoords = [];
        $tripStopData = $tripStopsMap[$trip['id']] ?? [];
        
        foreach ($tripStopData as $stop) {
            if (isset($stop['outlet_id']) && isset($outletMap[$stop['outlet_id']])) {
                $outlet = $outletMap[$stop['outlet_id']];
                $routeInfo[] = $outlet['outlet_name'];
                
                $stopParcelCount = $stopParcelCounts[$stop['id']] ?? 0;
                
                $routeStopsWithCoords[] = [
                    'id' => $stop['id'],
                    'stop_id' => $stop['id'], 
                    'outlet_id' => $outlet['id'],
                    'outlet_name' => $outlet['outlet_name'],
                    'address' => $outlet['address'] ?? '',
                    'latitude' => $outlet['latitude'],
                    'longitude' => $outlet['longitude'],
                    'stop_order' => $stop['stop_order'],
                    'arrival_time' => $stop['arrival_time'] ?? null,
                    'departure_time' => $stop['departure_time'] ?? null,
                    'parcel_count' => $stopParcelCount,
                    'status' => ($stop['departure_time'] ? 'completed' : 
                               ($stop['arrival_time'] ? 'at_stop' : 'pending'))
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
            case 'in_transit': 
                $active[] = $trip; 
                break;
            case 'scheduled': 
                $upcoming[] = $trip; 
                break;
            case 'completed': 
                $completed[] = $trip; 
                break;
        }
    }
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    
    $qps = $supabase->get('driver_qps', 
        'driver_id=eq.' . urlencode($driver_id) . 
        '&company_id=eq.' . urlencode($company_id) .
        '&date=gte.' . urlencode($week_start) .
        '&order=date.desc'
    );
    
    $trips_today = 0;
    $trips_week = 0;
    $parcels_delivered = 0;
    $parcels_returned = 0;
    
    foreach ($qps as $row) {
        if ($row['date'] === $today) {
            $trips_today += $row['trips_completed'];
        }
        $trips_week += $row['trips_completed'];
        $parcels_delivered += $row['parcels_handled'];
    }
    
 
    echo json_encode([
        'success' => true,
        'active_trips' => $active,
        'upcoming_trips' => $upcoming,
        'completed_trips' => $completed,
        'performance' => [
            'trips_today' => $trips_today,
            'trips_week' => $trips_week,
            'parcels_delivered' => $parcels_delivered,
            'parcels_returned' => $parcels_returned
        ],
        'cache_status' => [
            'cached' => ($currentTime - $cacheTime) <= $CACHE_DURATION,
            'cache_age' => $currentTime - $cacheTime,
            'cache_duration' => $CACHE_DURATION
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
