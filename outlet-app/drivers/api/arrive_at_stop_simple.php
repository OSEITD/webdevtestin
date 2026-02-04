<?php
error_reporting(E_ALL);
ob_start();
require_once '../../includes/OutletAwareSupabaseHelper.php';
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id'])) {
    
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No user session found. Please log in.',
        'debug' => [
            'session_exists' => session_status() === PHP_SESSION_ACTIVE,
            'user_id_set' => isset($_SESSION['user_id'])
        ]
    ]);
    exit;
}
if ($_SESSION['role'] !== 'driver') {
    
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Access denied. Driver role required.',
        'debug' => [
            'current_role' => $_SESSION['role'] ?? 'NOT_SET',
            'required_role' => 'driver',
            'user_id' => $_SESSION['user_id'] ?? 'NOT_SET'
        ]
    ]);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON input'
    ]);
    exit;
}
$required_fields = ['stop_id', 'trip_id', 'outlet_id'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: {$field}"
        ]);
        exit;
    }
}
$stop_id = $input['stop_id'];
$trip_id = $input['trip_id'];
$outlet_id = $input['outlet_id'];
$driver_id = $_SESSION['user_id'];
try {
    $supabase = new OutletAwareSupabaseHelper();
    $current_time = date('c'); 
    $update_result = $supabase->update('trip_stops', 
        ['arrival_time' => $current_time],
        'id=eq.' . urlencode($stop_id)
    );
    
    if (!$update_result) {
        throw new Exception('Failed to update trip stop arrival time');
    }
    
   
    $parcels = $supabase->get('parcel_list', 'trip_stop_id=eq.' . urlencode($stop_id));
    $parcel_count = count($parcels);
    
    foreach ($parcels as $parcel) {
        $supabase->update('parcel_list',
            ['status' => 'at_outlet', 'updated_at' => $current_time],
            'id=eq.' . urlencode($parcel['id'])
        );
    }
  
    if (isset($input['latitude']) && isset($input['longitude'])) {
        $location_data = [
            'driver_id' => $driver_id,
            'trip_id' => $trip_id,
            'company_id' => $_SESSION['company_id'],
            'latitude' => (float) $input['latitude'],
            'longitude' => (float) $input['longitude'],
            'accuracy' => isset($input['accuracy']) ? (float) $input['accuracy'] : null,
            'speed' => null,
            'heading' => null,
            'timestamp' => $current_time,
            'created_at' => $current_time,
            'is_manual' => true,
            'source' => 'manual',
            'synced_at' => $current_time,
            'device_timestamp' => $current_time
        ];
        
        $supabase->insert('driver_locations', $location_data);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully arrived at stop',
        'data' => [
            'stop_id' => $stop_id,
            'arrival_time' => $current_time,
            'parcels_updated' => $parcel_count,
            'outlet_id' => $outlet_id
        ]
    ]);
  
    if (ob_get_length()) {
        ob_end_flush();
    }
    
} catch (Exception $e) {
   
    if (ob_get_length()) {
        ob_clean();
    }
    
    error_log("Arrive at stop error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'stop_id' => $stop_id ?? 'undefined',
            'trip_id' => $trip_id ?? 'undefined',
            'outlet_id' => $outlet_id ?? 'undefined',
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
}
if (ob_get_length()) {
    ob_end_clean();
}
echo "\n";
?>
