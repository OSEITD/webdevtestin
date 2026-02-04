<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/supabase-helper.php';

$input = json_decode(file_get_contents('php://input'), true);
$driver_id = isset($input['driver_id']) ? $input['driver_id'] : null;
$trip_id = isset($input['trip_id']) ? $input['trip_id'] : null;

if (!$driver_id) {
    echo json_encode(['success' => false, 'error' => 'Missing driver_id']);
    exit;
}

$supabase = new SupabaseHelper();

if (!$trip_id) {
    $filter = 'id=eq.' . $driver_id;
    $drivers = $supabase->get('drivers', $filter);
    if ($drivers && isset($drivers[0]['current_trip_id'])) {
        $trip_id = $drivers[0]['current_trip_id'];
    }
}

if (!$trip_id) {
    echo json_encode(['success' => false, 'error' => 'No trip_id found for driver']);
    exit;
}

$filter = 'driver_id=eq.' . $driver_id . '&trip_id=eq.' . $trip_id . '&order=timestamp.desc&limit=1';
$locations = $supabase->get('driver_locations', $filter);

if ($locations && isset($locations[0])) {
    echo json_encode(['success' => true, 'location' => $locations[0]]);
} else {
    echo json_encode(['success' => false, 'error' => 'No location found for driver/trip']);
}
