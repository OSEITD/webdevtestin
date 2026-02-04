<?php
require_once '../../includes/OutletAwareSupabaseHelper.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$trip_id = $_GET['trip_id'] ?? null;
if (!$trip_id) {
    echo json_encode(['success' => false, 'error' => 'Trip ID required']);
    exit;
}
$company_id = $_SESSION['company_id'];
$supabase = new OutletAwareSupabaseHelper();
try {
    $trip = $supabase->get('trips',
        'id=eq.' . urlencode($trip_id) . '&limit=1',
        'id,trip_status,vehicle_id,origin_outlet_id,destination_outlet_id,departure_time,arrival_time,driver_id,company_id'
    );
    if (empty($trip)) {
        echo json_encode(['success' => false, 'error' => 'Trip not found']);
        exit;
    }
    $stops = $supabase->get('trip_stops',
        'trip_id=eq.' . urlencode($trip_id),
        'id,trip_id,outlet_id,stop_order,arrival_time,departure_time'
    );
    if (empty($stops)) {
        echo json_encode([
            'success' => true,
            'trip' => $trip[0],
            'stops' => [],
            'total_stops' => 0
        ]);
        exit;
    }
    $outletIds = array_unique(array_filter(array_column($stops, 'outlet_id')));
    $outletMap = [];
    if (!empty($outletIds)) {
        $quotedIds = array_map(function($id) { return '"' . $id . '"'; }, $outletIds);
        $outlets = $supabase->get('outlets', 'id=in.(' . implode(',', $quotedIds) . ')', 'id,outlet_name,address,latitude,longitude');
        foreach ($outlets as $outlet) {
            $outletMap[$outlet['id']] = $outlet;
        }
    }
    $parcelCounts = [];
    $parcelList = $supabase->get('parcel_list', 'trip_id=eq.' . urlencode($trip_id), 'id,trip_stop_id');
    foreach ($parcelList as $parcel) {
        $stopId = $parcel['trip_stop_id'] ?? null;
        if ($stopId) {
            $parcelCounts[$stopId] = ($parcelCounts[$stopId] ?? 0) + 1;
        }
    }
    $formattedStops = array_map(function($stop) use ($outletMap, $parcelCounts) {
        $outlet = $outletMap[$stop['outlet_id']] ?? null;
        $parcelCount = $parcelCounts[$stop['id']] ?? 0;
        return [
            'id' => $stop['id'],
            'stop_id' => $stop['id'],
            'outlet_id' => $stop['outlet_id'],
            'stop_order' => $stop['stop_order'],
            'outlet_name' => $outlet['outlet_name'] ?? 'Unknown Location',
            'address' => $outlet['address'] ?? 'Address not available',
            'latitude' => $outlet['latitude'] ?? null,
            'longitude' => $outlet['longitude'] ?? null,
            'arrival_time' => $stop['arrival_time'],
            'departure_time' => $stop['departure_time'],
            'parcel_count' => $parcelCount,
            'status' => $stop['departure_time'] ? 'completed' : ($stop['arrival_time'] ? 'current' : 'pending')
        ];
    }, $stops);
    usort($formattedStops, function($a, $b) {
        return $a['stop_order'] - $b['stop_order'];
    });
    echo json_encode([
        'success' => true,
        'trip' => $trip[0],
        'stops' => $formattedStops,
        'total_stops' => count($formattedStops)
    ]);
} catch (Exception $e) {
    error_log("Get trip route error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch route data']);
}
?>
