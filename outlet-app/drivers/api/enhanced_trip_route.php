<?php
require_once '../../includes/OutletAwareSupabaseHelper.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$company_id = $_SESSION['company_id'];
$trip_id = $_GET['trip_id'] ?? null;
if (!$trip_id) {
    echo json_encode(['success' => false, 'error' => 'Missing trip_id parameter']);
    exit;
}
$supabase = new OutletAwareSupabaseHelper();
try {
    $trip = $supabase->get('trips', 'id=eq.' . urlencode($trip_id) . '&company_id=eq.' . urlencode($company_id));
    if (empty($trip)) {
        echo json_encode(['success' => false, 'error' => 'Trip not found']);
        exit;
    }
    $tripStops = $supabase->get('trip_stops',
        'trip_id=eq.' . urlencode($trip_id) .
        '&company_id=eq.' . urlencode($company_id) .
        '&select=id,outlet_id,stop_order,arrival_time,departure_time,outlets!outlet_id(outlet_name,address,latitude,longitude)' .
        '&order=stop_order.asc'
    );
    $routeNames = [];
    $stops = [];
    foreach ($tripStops as $stop) {
        $outletName = $stop['outlets']['outlet_name'] ?? 'Unknown Outlet';
        $routeNames[] = $outletName;
        $stops[] = [
            'id' => $stop['id'],
            'outlet_id' => $stop['outlet_id'],
            'outlet_name' => $outletName,
            'address' => $stop['outlets']['address'] ?? '',
            'latitude' => $stop['outlets']['latitude'] ?? null,
            'longitude' => $stop['outlets']['longitude'] ?? null,
            'stop_order' => $stop['stop_order'],
            'arrival_time' => $stop['arrival_time'],
            'departure_time' => $stop['departure_time'],
            'status' => $stop['departure_time'] ? 'completed' : ($stop['arrival_time'] ? 'arrived' : 'pending')
        ];
    }
    $routeString = implode(' → ', $routeNames);
    $parcelCount = $supabase->get('parcel_list',
        'trip_id=eq.' . urlencode($trip_id) .
        '&company_id=eq.' . urlencode($company_id) .
        '&select=id'
    );
    echo json_encode([
        'success' => true,
        'trip_id' => $trip_id,
        'trip' => $trip[0],
        'route' => $routeString,
        'stops' => $stops,
        'parcel_count' => count($parcelCount),
        'total_stops' => count($stops),
        'completed_stops' => count(array_filter($stops, function($stop) {
            return $stop['status'] === 'completed';
        }))
    ]);
} catch (Exception $e) {
    error_log('Enhanced route fetch error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
