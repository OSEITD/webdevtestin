<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/supabase.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'outlet_manager') {
        throw new Exception('Access denied');
    }

    $driverId = $_GET['driver_id'] ?? '';
    $tripId = $_GET['trip_id'] ?? '';
    $companyId = $_SESSION['company_id'];

    if (empty($driverId)) {
        throw new Exception('Driver ID is required');
    }

    $supabase = new SupabaseHelper();

    if ($tripId) {
        $trip = $supabase->get('trips', "id=eq.$tripId&company_id=eq.$companyId");
        if (empty($trip)) {
            throw new Exception('Trip not found or access denied');
        }
    }

    $locationQuery = "driver_id=eq.$driverId";
    if ($tripId) {
        $locationQuery .= "&trip_id=eq.$tripId";
    }
    $locationQuery .= "&order=timestamp.desc&limit=1";

    $locations = $supabase->get('driver_locations', $locationQuery);

    if (empty($locations)) {
        echo json_encode([
            'success' => false,
            'message' => 'No location data found for this driver'
        ]);
        exit;
    }

    $location = $locations[0];

    $locationTime = strtotime($location['timestamp']);
    $currentTime = time();
    $timeDiff = $currentTime - $locationTime;
    $isRecent = $timeDiff <= 1800;

    echo json_encode([
        'success' => true,
        'location' => [
            'latitude' => floatval($location['latitude']),
            'longitude' => floatval($location['longitude']),
            'timestamp' => $location['timestamp'],
            'speed' => $location['speed'] ?? 0,
            'heading' => $location['heading'] ?? 0,
            'accuracy' => $location['accuracy'] ?? 0,
            'is_recent' => $isRecent,
            'time_diff_minutes' => round($timeDiff / 60, 1)
        ],
        'driver_id' => $driverId,
        'trip_id' => $tripId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>