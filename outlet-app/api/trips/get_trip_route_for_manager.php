<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $trip_id = $_GET['trip_id'] ?? null;
    if (!$trip_id) {
        echo json_encode(['success' => false, 'error' => 'Missing trip_id']);
        exit;
    }

    $supabase = new OutletAwareSupabaseHelper();
    $company_id = $_SESSION['company_id'] ?? null;

    
    $stops = $supabase->get('trip_stops', 'trip_id=eq.' . urlencode($trip_id) . '&order=stop_order.asc&select=id,trip_id,outlet_id,stop_order,arrival_time,departure_time');
    if (empty($stops)) {
        echo json_encode(['success' => true, 'stops' => []]);
        exit;
    }

    
    $outletIds = array_values(array_unique(array_map(function($s){ return $s['outlet_id'] ?? null; }, $stops)));
    $stopIds = array_values(array_map(function($s){ return $s['id']; }, $stops));

    $outletMap = [];
    if (!empty($outletIds)) {
        $quoted = array_map(function($id){ return '"' . $id . '"'; }, $outletIds);
        $outlets = $supabase->get('outlets', 'id=in.(' . implode(',', $quoted) . ')&company_id=eq.' . urlencode($company_id), 'id,outlet_name,address,latitude,longitude');
        if (!empty($outlets)) {
            foreach ($outlets as $o) {
                $outletMap[$o['id']] = $o;
            }
        }
    }

    
    $parcelCounts = [];
    if (!empty($stopIds)) {
        $quotedStops = implode(',', array_map('urlencode', $stopIds));
        $parcelList = $supabase->get('parcel_list', 'trip_stop_id=in.(' . $quotedStops . ')&select=id,trip_stop_id');
        foreach ($parcelList as $p) {
            $sid = $p['trip_stop_id'] ?? null;
            if ($sid) $parcelCounts[$sid] = ($parcelCounts[$sid] ?? 0) + 1;
        }
    }

    $formatted = [];
    foreach ($stops as $s) {
        $out = $outletMap[$s['outlet_id']] ?? null;
        $formatted[] = [
            'id' => $s['id'],
            'stop_id' => $s['id'],
            'trip_id' => $s['trip_id'],
            'outlet_id' => $s['outlet_id'],
            'outlet_name' => $out['outlet_name'] ?? 'Unknown Outlet',
            'address' => $out['address'] ?? '',
            'latitude' => $out['latitude'] ?? null,
            'longitude' => $out['longitude'] ?? null,
            'stop_order' => $s['stop_order'],
            'arrival_time' => $s['arrival_time'],
            'departure_time' => $s['departure_time'],
            'parcel_count' => $parcelCounts[$s['id']] ?? 0
        ];
    }

    echo json_encode(['success' => true, 'stops' => $formatted, 'total_stops' => count($formatted)]);

} catch (Exception $e) {
    error_log('get_trip_route_for_manager error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
