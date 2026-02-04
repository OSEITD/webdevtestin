<?php
require_once '../../includes/ResponseCache.php';
session_start();
header('Content-Type: application/json');
if (!ob_start('ob_gzhandler')) ob_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}
$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
$cache = new ResponseCache(null, 20);
$cacheKey = "active_trip_{$driverId}_{$companyId}";
$cachedResponse = $cache->get($cacheKey);
if ($cachedResponse !== null) {
    echo json_encode($cachedResponse);
    exit();
}
$SUPABASE_URL = "https://xerpchdsykqafrsxbqef.supabase.co";
$SUPABASE_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            "apikey: $SUPABASE_API_KEY",
            "Authorization: Bearer $SUPABASE_API_KEY",
            "Content-Type: application/json"
        ],
        'ignore_errors' => true,
        'timeout' => 5  
    ]
]);
$tripUrl = $SUPABASE_URL . "/rest/v1/trips?select=*,vehicle:vehicle_id(name,plate_number,status),profiles:outlet_manager_id(full_name,outlet_id,outlets:outlet_id(outlet_name))&driver_id=eq.$driverId&company_id=eq.$companyId&trip_status=eq.in_transit&limit=1";
$tripRes = @file_get_contents($tripUrl, false, $context);
$trips = @json_decode($tripRes, true);
if (!is_array($trips) || empty($trips)) {
    echo json_encode(['success' => false, 'error' => 'No active trip found']);
    exit();
}
$trip = $trips[0];
$tripId = $trip['id'] ?? $trip['trip_id'] ?? '';
if (!$tripId) {
    echo json_encode(['success' => false, 'error' => 'Trip ID not found']);
    exit();
}
if (isset($trip['profiles']) && is_array($trip['profiles'])) {
    $trip['dispatcher_name'] = $trip['profiles']['full_name'] ?? 'N/A';
} else {
    $trip['dispatcher_name'] = 'N/A';
}
$stopsUrl = $SUPABASE_URL . "/rest/v1/trip_stops?select=*,outlets:outlet_id(outlet_name,location,address,latitude,longitude)&trip_id=eq.$tripId&company_id=eq.$companyId&order=stop_order.asc";
$parcelsUrl = $SUPABASE_URL . "/rest/v1/parcel_list?trip_id=eq.$tripId&company_id=eq.$companyId";
$mh = curl_multi_init();
$ch1 = curl_init($stopsUrl);
$ch2 = curl_init($parcelsUrl);
$headers = [
    "apikey: $SUPABASE_API_KEY",
    "Authorization: Bearer $SUPABASE_API_KEY",
    "Content-Type: application/json"
];
curl_setopt_array($ch1, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 5
]);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 5
]);
curl_multi_add_handle($mh, $ch1);
curl_multi_add_handle($mh, $ch2);
$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);
$stopsRes = curl_multi_getcontent($ch1);
$parcelsRes = curl_multi_getcontent($ch2);
curl_multi_remove_handle($mh, $ch1);
curl_multi_remove_handle($mh, $ch2);
curl_multi_close($mh);
curl_close($ch1);
curl_close($ch2);
$rawStops = @json_decode($stopsRes, true);
$parcels = @json_decode($parcelsRes, true);
if (!is_array($rawStops)) $rawStops = [];
if (!is_array($parcels)) $parcels = [];
$stops = [];
$routeInfo = [];
$nextStop = null;
$nextStopOutletId = null;
foreach ($rawStops as $stop) {
    $outletName = $stop['outlets']['outlet_name'] ?? null;
    $stop['outlet_name'] = $outletName;
    $stops[] = $stop;
    
    if ($outletName) {
        $routeInfo[] = $outletName;
    }
    
    
    if (!$nextStop && empty($stop['arrival_time'])) {
        $nextStop = $stop;
        $nextStopOutletId = $stop['outlet_id'];
    }
}
$trip['route'] = implode(' → ', $routeInfo);
$trip['route_stops'] = $routeInfo;
$nextStopParcels = [];
$totalParcels = count($parcels);
$deliveredParcels = 0;
$remainingDeliveries = 0;
foreach ($parcels as $parcel) {
    if ($parcel['status'] === 'completed') {
        $deliveredParcels++;
    } elseif ($parcel['status'] !== 'returned') {
        $remainingDeliveries++;
    }
    
    
    if ($nextStopOutletId && 
        $parcel['outlet_id'] == $nextStopOutletId && 
        $parcel['status'] !== 'completed' && 
        $parcel['status'] !== 'returned') {
        $nextStopParcels[] = $parcel;
    }
}
$trip['parcels_count'] = $totalParcels;
$trip['delivered_count'] = $deliveredParcels;
$trip['priority'] = $totalParcels >= 20 ? 'urgent' : 
                   ($totalParcels >= 10 ? 'high' : 
                   ($totalParcels >= 5 ? 'medium' : 'normal'));
$stopCount = count($stops);
$trip['total_distance'] = $stopCount > 0 ? ($stopCount * 5) . ' km' : 'Calculating...';
$trip['estimated_duration'] = $stopCount > 0 ? ($stopCount * 20) . ' min' : 'Calculating...';
$trip['estimated_arrival'] = !empty($trip['departure_time']) ? 
    date('Y-m-d H:i:s', strtotime($trip['departure_time'] . ' +' . ($stopCount * 20) . ' minutes')) : 
    null;
$response = [
    'success' => true,
    'trip' => $trip,
    'stops' => $stops,
    'parcels' => $parcels,
    'next_stop' => $nextStop,
    'next_stop_parcels' => $nextStopParcels,
    'remaining_deliveries' => $remainingDeliveries
];
$cache->set($cacheKey, $response);
echo json_encode($response);
