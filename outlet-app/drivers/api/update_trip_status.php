<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Type: application/json');
    exit(0);
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/supabase_config.php';
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';
session_start();

ob_end_clean();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $required_fields = ['trip_id', 'status'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    $trip_id = $input['trip_id'];
    $new_status = $input['status'];
    $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');
    
    $valid_statuses = ['scheduled', 'in_transit', 'completed', 'at_outlet', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception('Invalid status. Must be one of: ' . implode(', ', $valid_statuses));
    }
    
    $driver_id = $_SESSION['driver_id'] ?? null;
    if (!$driver_id) {
        throw new Exception('Driver not authenticated');
    }
    
    $supabase = new OutletAwareSupabaseHelper();
    
    $trip_check = $supabase->get('trips', 
        'id=eq.' . urlencode($trip_id) . 
        '&driver_id=eq.' . urlencode($driver_id) . 
        '&select=*'
    );
    
    if (empty($trip_check)) {
        throw new Exception('Trip not found or access denied');
    }
    $current_trip = $trip_check[0];
    
    $update_data = [
        'status' => $new_status,
        'updated_at' => $timestamp
    ];
    
    switch ($new_status) {
        case 'in_transit':
            $update_data['actual_start_time'] = $timestamp;
            break;
        case 'completed':
            $update_data['actual_end_time'] = $timestamp;
            break;
        case 'at_outlet':
            $update_data['arrived_at_outlet_time'] = $timestamp;
            break;
    }
    
    $result = $supabase->update('trips', $update_data, 
        'id=eq.' . urlencode($trip_id)
    );
    if (!$result) {
        throw new Exception('Failed to update trip status in database');
    }
    
    $log_data = [
        'trip_id' => $trip_id,
        'driver_id' => $driver_id,
        'status_from' => $current_trip['status'] ?? 'unknown',
        'status_to' => $new_status,
        'changed_at' => $timestamp,
        'additional_data' => json_encode($input)
    ];
    
    try {
        $supabase->insert('trip_status_logs', $log_data);
    } catch (Exception $e) {
        error_log('Failed to log trip status change: ' . $e->getMessage());
    }
    echo json_encode([
        'success' => true,
        'message' => "Trip status updated to $new_status",
        'data' => [
            'trip_id' => $trip_id,
            'old_status' => $current_trip['status'] ?? 'unknown',
            'new_status' => $new_status,
            'timestamp' => $timestamp
        ]
    ]);
} catch (Exception $e) {
    error_log('Update trip status error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
