<?php
header('Content-Type: application/json');
$tripId = $_GET['trip_id'] ?? '';
$companyId = $_GET['company_id'] ?? '';
if (!$tripId || !$companyId) {
    echo json_encode(['success' => false, 'error' => 'Missing trip_id or company_id']);
    exit();
}
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';
$supabase = new OutletAwareSupabaseHelper();
try {
    
    $stops = $supabase->get('trip_stops', "trip_id=eq.$tripId&company_id=eq.$companyId&order=stop_order.asc", 'outlet_id,stop_order');
    if (!is_array($stops) || empty($stops)) {
        echo json_encode(['success' => true, 'route' => '-', 'route_stops' => []]);
        exit();
    }
    
    $outletIds = array_values(array_unique(array_map(function($s){ return $s['outlet_id'] ?? null; }, $stops)));
    $outletIds = array_filter($outletIds);
    if (empty($outletIds)) {
        echo json_encode(['success' => true, 'route' => '-', 'route_stops' => []]);
        exit();
    }
    
    $quoted = array_map(function($id){ return '"' . $id . '"'; }, $outletIds);
    $inList = implode(',', $quoted);
    $outlets = $supabase->get('outlets', "id=in.($inList)&company_id=eq.$companyId", 'id,outlet_name');
    $outletMap = [];
    if (is_array($outlets)) {
        foreach ($outlets as $o) {
            if (isset($o['id'])) $outletMap[$o['id']] = $o['outlet_name'] ?? null;
        }
    }
    $outletNames = [];
    foreach ($stops as $s) {
        $oid = $s['outlet_id'] ?? null;
        if ($oid && isset($outletMap[$oid])) {
            $outletNames[] = $outletMap[$oid];
        }
    }
    echo json_encode([
        'success' => true,
        'route' => count($outletNames) ? implode(' → ', $outletNames) : '-',
        'route_stops' => $outletNames
    ]);
} catch (Exception $e) {
    error_log('fetch_trip_route exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error fetching route']);
}
?>
