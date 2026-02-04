<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$driver_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
require_once '../includes/OutletAwareSupabaseHelper.php';
$supabase = new OutletAwareSupabaseHelper();
try {
   
    $trips = $supabase->get('trips', 'company_id=eq.' . urlencode($company_id) . '&driver_id=eq.' . urlencode($driver_id));
    
   
    $vehicles = $supabase->get('vehicle', 'company_id=eq.' . urlencode($company_id));
    $vehicleMap = [];
    foreach ($vehicles as $vehicle) {
        $vehicleMap[$vehicle['id']] = $vehicle;
    }
  
    $profiles = $supabase->get('profiles', 'company_id=eq.' . urlencode($company_id));
    $profileMap = [];
    foreach ($profiles as $profile) {
        $profileMap[$profile['id']] = $profile;
    }
    
    $outlets = $supabase->get('outlets', 'company_id=eq.' . urlencode($company_id));
    $outletMap = [];
    foreach ($outlets as $outlet) {
        $outletMap[$outlet['id']] = $outlet;
    }
    
    $tripStops = $supabase->get('trip_stops', 'company_id=eq.' . urlencode($company_id));
    
    $parcelCounts = [];
    $deliveredCounts = [];
    $parcelListData = $supabase->get('parcel_list', 'company_id=eq.' . urlencode($company_id));
    foreach ($parcelListData as $parcel) {
        if ($parcel['trip_id']) {
            $tripId = $parcel['trip_id'];
            if (!isset($parcelCounts[$tripId])) {
                $parcelCounts[$tripId] = 0;
                $deliveredCounts[$tripId] = 0;
            }
            $parcelCounts[$tripId]++;
            
            if ($parcel['status'] === 'completed') {
                $deliveredCounts[$tripId]++;
            }
        }
    }
    
    $active = [];
    $upcoming = [];
    $completed = [];
    
    foreach ($trips as $trip) {
     
        if (isset($trip['vehicle_id']) && isset($vehicleMap[$trip['vehicle_id']])) {
            $trip['vehicle'] = $vehicleMap[$trip['vehicle_id']];
        }
        
        if (isset($trip['outlet_manager_id']) && isset($profileMap[$trip['outlet_manager_id']])) {
            $manager = $profileMap[$trip['outlet_manager_id']];
            $trip['dispatcher_name'] = $manager['full_name'] ?? 'N/A';
            
            if (isset($manager['outlet_id']) && isset($outletMap[$manager['outlet_id']])) {
                $trip['outlet_name'] = $outletMap[$manager['outlet_id']]['outlet_name'] ?? '-';
            }
        }
        $routeInfo = [];
        $tripStopData = array_filter($tripStops, function($stop) use ($trip) {
            return $stop['trip_id'] === $trip['id'];
        });
        
        usort($tripStopData, function($a, $b) {
            return $a['stop_order'] <=> $b['stop_order'];
        });
        
        foreach ($tripStopData as $stop) {
            if (isset($stop['outlet_id']) && isset($outletMap[$stop['outlet_id']])) {
                $routeInfo[] = $outletMap[$stop['outlet_id']]['outlet_name'];
            }
        }
        
        $trip['route'] = implode(' → ', $routeInfo);
        $trip['route_stops'] = $routeInfo;
        
        $trip['parcels_count'] = $parcelCounts[$trip['id']] ?? 0;
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
      
        $stopCount = count($tripStopData);
        $trip['total_distance'] = $stopCount > 0 ? ($stopCount * 5) . ' km' : 'Calculating...';
        $trip['estimated_duration'] = $stopCount > 0 ? ($stopCount * 20) . ' min' : 'Calculating...';
        $trip['estimated_arrival'] = $trip['departure_time'] ? 
            date('Y-m-d H:i:s', strtotime($trip['departure_time'] . ' +' . ($stopCount * 20) . ' minutes')) : 
            null;
        
     
        switch ($trip['trip_status']) {
            case 'in_transit': $active[] = $trip; break;
            case 'scheduled': $upcoming[] = $trip; break;
            case 'completed': $completed[] = $trip; break;
        }
    }
   
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $qps = $supabase->get('driver_qps', 'driver_id=eq.' . urlencode($driver_id) . '&company_id=eq.' . urlencode($company_id) . '&date=gte.' . $week_start);
    
    $trips_today = 0;
    $trips_week = 0;
    $parcels_delivered = 0;
    $parcels_returned = 0;
    
    foreach ($qps as $row) {
        if ($row['date'] === $today) $trips_today += $row['trips_completed'];
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
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
