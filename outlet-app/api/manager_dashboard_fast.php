<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ERROR | E_PARSE);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$supabaseCandidates = [
    __DIR__ . '/../includes/supabase-helper.php',
    __DIR__ . '/../includes/OutletAwareSupabaseHelper.php',
    __DIR__ . '/../includes/supabase-client.php',
    __DIR__ . '/../includes/supabase-helper-bypass.php',
    __DIR__ . '/../../customer-app/includes/supabase.php'
];
$loaded = false;
foreach ($supabaseCandidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Supabase helper not found']);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'outlet_manager') {
        throw new Exception('Only outlet managers can access this endpoint');
    }

    $managerId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    if (!$companyId) throw new Exception('Company ID not found in session');

    
    $CACHE_TTL = 15; 
    $cacheDir = sys_get_temp_dir();
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'manager_dashboard_fast_' . $companyId . '.json';

    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) <= $CACHE_TTL)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            header('X-Cache: HIT');
            echo $cached;
            exit;
        }
    }

    $supabase = new SupabaseHelper();

    
    $tripQuery = "company_id=eq.$companyId&trip_status=in.(scheduled,accepted,in_transit,at_outlet)";
    $trips = $supabase->get('trips', $tripQuery);
    if (!is_array($trips)) $trips = [];

    $counts = [
        'total' => 0,
        'scheduled' => 0,
        'accepted' => 0,
        'in_transit' => 0,
        'at_outlet' => 0
    ];

    foreach ($trips as $t) {
        $counts['total']++;
        $status = $t['trip_status'] ?? '';
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }

    
    $sample = [];
    $sampleTrips = array_slice($trips, 0, 5);
    
    $driverIds = array_filter(array_map(function($t){ return $t['driver_id'] ?? null; }, $sampleTrips));
    $vehicleIds = array_filter(array_map(function($t){ return $t['vehicle_id'] ?? null; }, $sampleTrips));
    $outletIds = array_filter(array_merge(
        array_map(function($t){ return $t['origin_outlet_id'] ?? null; }, $sampleTrips),
        array_map(function($t){ return $t['destination_outlet_id'] ?? null; }, $sampleTrips)
    ));

    $driversMap = [];
    $vehiclesMap = [];
    $outletsMap = [];

    if (count($driverIds) > 0) {
        $in = implode(',', array_unique($driverIds));
        $drivers = $supabase->get('drivers', "id=in.($in)");
        if (is_array($drivers)) foreach ($drivers as $d) $driversMap[$d['id']] = $d;
    }

    if (count($vehicleIds) > 0) {
        $in = implode(',', array_unique($vehicleIds));
        $vehicles = $supabase->get('vehicle', "id=in.($in)");
        if (is_array($vehicles)) foreach ($vehicles as $v) $vehiclesMap[$v['id']] = $v;
    }

    if (count($outletIds) > 0) {
        $in = implode(',', array_unique($outletIds));
        $outs = $supabase->get('outlets', "id=in.($in)");
        if (is_array($outs)) foreach ($outs as $o) $outletsMap[$o['id']] = $o;
    }

    foreach ($sampleTrips as $t) {
        $sample[] = [
            'id' => $t['id'],
            'trip_status' => $t['trip_status'] ?? 'unknown',
            'trip_date' => $t['trip_date'] ?? null,
            'origin_name' => isset($t['origin_outlet_id']) && isset($outletsMap[$t['origin_outlet_id']]) ? $outletsMap[$t['origin_outlet_id']]['outlet_name'] : ($t['origin_name'] ?? null),
            'destination_name' => isset($t['destination_outlet_id']) && isset($outletsMap[$t['destination_outlet_id']]) ? $outletsMap[$t['destination_outlet_id']]['outlet_name'] : ($t['destination_name'] ?? null),
            'driver_name' => isset($t['driver_id']) && isset($driversMap[$t['driver_id']]) ? ($driversMap[$t['driver_id']]['driver_name'] ?? null) : null,
            'vehicle_name' => isset($t['vehicle_id']) && isset($vehiclesMap[$t['vehicle_id']]) ? ($vehiclesMap[$t['vehicle_id']]['name'] ?? null) : null
        ];
    }

    $out = json_encode([
        'success' => true,
        'counts' => $counts,
        'sample' => $sample
    ]);

    
    @file_put_contents($cacheFile, $out);

    header('X-Cache: MISS');
    echo $out;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
