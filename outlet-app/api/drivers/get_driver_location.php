<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php'; 

$input = json_decode(file_get_contents('php://input'), true);
$driver_id = isset($input['driver_id']) ? $input['driver_id'] : null;
$trip_id = isset($input['trip_id']) ? $input['trip_id'] : null;

if (!$driver_id) {
    echo json_encode(['success' => false, 'error' => 'Missing driver_id']);
    exit;
}

if (!$trip_id) {
    $stmt = $pdo->prepare('SELECT current_trip_id FROM drivers WHERE id = ?');
    $stmt->execute([$driver_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['current_trip_id']) {
        $trip_id = $row['current_trip_id'];
    }
}

if (!$trip_id) {
    echo json_encode(['success' => false, 'error' => 'No trip_id found for driver']);
    exit;
}

$stmt = $pdo->prepare('SELECT latitude, longitude, accuracy, speed, heading, timestamp FROM driver_locations WHERE driver_id = ? AND trip_id = ? ORDER BY timestamp DESC LIMIT 1');
$stmt->execute([$driver_id, $trip_id]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);

if ($location) {
    echo json_encode(['success' => true, 'location' => $location]);
} else {
    echo json_encode(['success' => false, 'error' => 'No location found for driver/trip']);
}
