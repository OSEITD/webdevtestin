<?php
require_once '../../includes/OutletAwareSupabaseHelper.php';
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$action = $_GET['action'] ?? 'current';
$driver_id = $_GET['driver_id'] ?? $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
if ($driver_id === 'current') {
    $driver_id = $_SESSION['user_id'];
}
error_log("Getting driver location: driver_id=$driver_id, action=$action");
$supabase = new OutletAwareSupabaseHelper();
try {
    switch ($action) {
        case 'last_known':
            $url = 'https://xerpchdsykqafrsxbqef.supabase.co/rest/v1/driver_locations?driver_id=eq.' .
                   urlencode($driver_id) . '&company_id=eq.' . urlencode($company_id) .
                   '&select=latitude,longitude,accuracy,speed,heading,timestamp,created_at' .
                   '&order=timestamp.desc&limit=1';
            $serviceKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
            $headers = [
                "apikey: " . $serviceKey,
                "Authorization: Bearer " . $serviceKey,
                "Content-Type: application/json"
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $locationData = json_decode($result, true);
                if (!empty($locationData)) {
                    $location = $locationData[0];
                    $locationTime = new DateTime($location['timestamp']);
                    $currentTime = new DateTime();
                    $interval = $currentTime->diff($locationTime);
                    $ageMinutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
                    $lat = floatval($location['latitude']);
                    $lng = floatval($location['longitude']);
                    $isValidZambian = ($lat >= -18.1 && $lat <= -8.2 && $lng >= 21.9 && $lng <= 33.7);
                    if ($isValidZambian && $ageMinutes <= 180) {
                        echo json_encode([
                            'success' => true,
                            'location' => [
                                'latitude' => $lat,
                                'longitude' => $lng,
                                'accuracy' => $location['accuracy'] ? floatval($location['accuracy']) : null,
                                'speed' => $location['speed'] ? floatval($location['speed']) : null,
                                'heading' => $location['heading'] ? floatval($location['heading']) : null,
                                'timestamp' => $location['timestamp'],
                                'created_at' => $location['created_at']
                            ],
                            'age_minutes' => $ageMinutes,
                            'is_recent' => $ageMinutes <= 60,
                            'is_valid_zambian' => $isValidZambian,
                            'message' => "Last known location found (${ageMinutes} minutes ago)"
                        ]);
                    } else {
                        $reason = !$isValidZambian ? 'outside Zambian bounds' : 'too old';
                        echo json_encode([
                            'success' => false,
                            'error' => "Last known location is ${reason}",
                            'age_minutes' => $ageMinutes,
                            'is_valid_zambian' => $isValidZambian
                        ]);
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'No location history found for driver'
                    ]);
                }
            } else {
                throw new Exception("Database query failed with HTTP code: $httpCode");
            }
            break;
        case 'cache_status':
            $url = 'https://xerpchdsykqafrsxbqef.supabase.co/rest/v1/driver_locations?driver_id=eq.' .
                   urlencode($driver_id) . '&company_id=eq.' . urlencode($company_id) .
                   '&select=latitude,longitude,timestamp&order=timestamp.desc&limit=1';
            $serviceKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9zZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
            $headers = [
                "apikey: " . $serviceKey,
                "Authorization: Bearer " . $serviceKey,
                "Content-Type: application/json"
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $locationData = json_decode($result, true);
                $hasLocation = !empty($locationData);
                $lastLocation = $hasLocation ? $locationData[0] : null;
                $ageMinutes = null;
                if ($lastLocation) {
                    $locationTime = new DateTime($lastLocation['timestamp']);
                    $currentTime = new DateTime();
                    $interval = $currentTime->diff($locationTime);
                    $ageMinutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
                }
                echo json_encode([
                    'success' => true,
                    'has_location' => $hasLocation,
                    'last_update_minutes_ago' => $ageMinutes,
                    'location_summary' => $lastLocation ? [
                        'latitude' => floatval($lastLocation['latitude']),
                        'longitude' => floatval($lastLocation['longitude']),
                        'formatted' => round(floatval($lastLocation['latitude']), 6) . ', ' . round(floatval($lastLocation['longitude']), 6)
                    ] : null,
                    'cache_recommended' => $hasLocation && $ageMinutes <= 60,
                    'message' => $hasLocation ? "Location available (${ageMinutes}m ago)" : 'No location history'
                ]);
            } else {
                throw new Exception("Database query failed with HTTP code: $httpCode");
            }
            break;
        case 'current':
        default:
            $location = $supabase->get('latest_driver_locations',
                'driver_id=eq.' . urlencode($driver_id) . '&limit=1'
            );
            if (empty($location)) {
                echo json_encode(['success' => false, 'error' => 'No location data found']);
                exit;
            }
            $locationData = $location[0];
            if (empty($locationData['latitude']) || empty($locationData['longitude'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid location coordinates']);
                exit;
            }
            echo json_encode([
                'success' => true,
                'location' => [
                    'driver_id' => $locationData['driver_id'],
                    'trip_id' => $locationData['trip_id'],
                    'latitude' => floatval($locationData['latitude']),
                    'longitude' => floatval($locationData['longitude']),
                    'accuracy' => $locationData['accuracy'],
                    'speed' => $locationData['speed'],
                    'heading' => $locationData['heading'],
                    'timestamp' => $locationData['timestamp']
                ]
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Get driver location error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve location: ' . $e->getMessage()
    ]);
}
?>
