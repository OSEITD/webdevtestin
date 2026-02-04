<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
require_once '../../../vendor/autoload.php';
require_once '../../../config/supabase_config.php';
require_once '../includes/OutletAwareSupabaseHelper.php';
session_start();
try {
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $new_status = $input['status'] ?? null;
    $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');
    if (!$new_status) {
        throw new Exception("Missing required field: status");
    }
    
    $valid_statuses = ['available', 'unavailable'];
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception('Invalid status. Must be one of: ' . implode(', ', $valid_statuses));
    }
    
    $driver_id = $_SESSION['driver_id'] ?? $_SESSION['user_id'] ?? null;
    if (!$driver_id) {
        throw new Exception('Driver not authenticated');
    }
    
    $supabase = new OutletAwareSupabaseHelper();
    
    $driver_check = $supabase->get('drivers', 
        'user_id=eq.' . urlencode($driver_id) . 
        '&select=*'
    );
    if (empty($driver_check)) {
        throw new Exception('Driver record not found');
    }
    $current_driver = $driver_check[0];
    
    $update_data = [
        'status' => $new_status,
        'updated_at' => $timestamp
    ];
    
    if ($new_status === 'available') {
        $update_data['last_available_time'] = $timestamp;
    } else {
        $update_data['last_unavailable_time'] = $timestamp;
    }
    
    $result = $supabase->update('drivers', $update_data, 
        'user_id=eq.' . urlencode($driver_id)
    );
    if (!$result) {
        throw new Exception('Failed to update driver status in database');
    }
    
    $log_data = [
        'driver_id' => $driver_id,
        'status_from' => $current_driver['status'] ?? 'unknown',
        'status_to' => $new_status,
        'changed_at' => $timestamp,
        'additional_data' => json_encode($input)
    ];
    
    try {
        $supabase->insert('driver_status_logs', $log_data);
    } catch (Exception $e) {
        error_log('Failed to log driver status change: ' . $e->getMessage());
    }
    echo json_encode([
        'success' => true,
        'message' => "Driver status updated to $new_status",
        'data' => [
            'driver_id' => $driver_id,
            'old_status' => $current_driver['status'] ?? 'unknown',
            'new_status' => $new_status,
            'timestamp' => $timestamp
        ]
    ]);
} catch (Exception $e) {
    error_log('Update driver status error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
