<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/supabase-helper.php';
session_start();

$driverId = $_SESSION['driver_id'] ?? ($_GET['driver_id'] ?? null);
$companyId = $_SESSION['company_id'] ?? ($_GET['company_id'] ?? null);

if (!$driverId || !$companyId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Missing driver or company ID']);
    exit;
}

$supabase = new SupabaseHelper();

$driver = $supabase->get('drivers', 'id=eq.' . urlencode($driverId));
$profile = $supabase->get('profiles', 'id=eq.' . urlencode($driverId));

$now = date('Y-m-d H:i:s');

$allTrips = $supabase->get('trips', 'driver_id=eq.' . urlencode($driverId) . '&company_id=eq.' . urlencode($companyId) . '&order=departure_time.asc');

function getOutletName($id, $supabase) {
    if (!$id) return null;
    $outlet = $supabase->get('outlets', 'id=eq.' . urlencode($id));
    return $outlet[0]['outlet_name'] ?? null;
}

function getParcelCount($tripId, $supabase) {
    if (!$tripId) return 0;
    $parcels = $supabase->get('parcel_list', 'trip_id=eq.' . urlencode($tripId));
    return is_array($parcels) ? count($parcels) : 0;
}

function enhanceTrips($trips, $supabase) {
    $result = [];
    foreach ($trips as $trip) {
        $stops = $supabase->get('trip_stops', 'trip_id=eq.' . urlencode($trip['id']) . '&order=stop_order.asc');
        $routeNames = [];
        foreach ($stops as $stop) {
            $outletId = $stop['outlet_id'] ?? null;
            $outletName = $outletId ? getOutletName($outletId, $supabase) : null;
            if ($outletName) $routeNames[] = $outletName;
        }
        $trip['route'] = count($routeNames) ? implode(' → ', $routeNames) : '-';
        $trip['parcels_count'] = getParcelCount($trip['id'], $supabase);
        $result[] = $trip;
    }
    return $result;
}

$active_trips = enhanceTrips(array_filter($allTrips, function($trip) {
    return $trip['trip_status'] === 'in_transit';
}), $supabase);
$upcoming_trips = enhanceTrips(array_filter($allTrips, function($trip) use ($now) {
    return $trip['trip_status'] === 'scheduled' && $trip['departure_time'] >= $now;
}), $supabase);
$completed_trips = enhanceTrips(array_filter($allTrips, function($trip) {
    return $trip['trip_status'] === 'completed';
}), $supabase);

$today = date('Y-m-d');
$qpsToday = $supabase->get('driver_qps', 'driver_id=eq.' . urlencode($driverId) . '&company_id=eq.' . urlencode($companyId) . '&date=eq.' . urlencode($today));
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$qpsWeek = $supabase->get('driver_qps', 'driver_id=eq.' . urlencode($driverId) . '&company_id=eq.' . urlencode($companyId) . '&date=gte.' . urlencode($weekStart) . '&date=lte.' . urlencode($weekEnd));

$trips_today = $qpsToday[0]['trips_completed'] ?? 0;
$trips_week = array_sum(array_map(function($row){ return $row['trips_completed'] ?? 0; }, $qpsWeek));
$parcels_delivered = $qpsToday[0]['parcels_handled'] ?? 0;
$parcels_returned = $qpsToday[0]['parcels_returned'] ?? 0;

$performance = [
    'trips_today' => $trips_today,
    'trips_week' => $trips_week,
    'parcels_delivered' => $parcels_delivered,
    'parcels_returned' => $parcels_returned
];

$response = [
    'success' => true,
    'driver' => $driver[0] ?? [],
    'profile' => $profile[0] ?? [],
    'active_trips' => array_values($active_trips),
    'upcoming_trips' => array_values($upcoming_trips),
    'completed_trips' => array_values($completed_trips),
    'performance' => $performance
];
echo json_encode($response);
