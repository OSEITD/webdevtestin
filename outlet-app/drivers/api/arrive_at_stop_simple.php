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
    
    // CRITICAL PATH - Only update arrival time, respond immediately
    $update_result = $supabase->update('trip_stops', 
        ['arrival_time' => $current_time],
        'id=eq.' . urlencode($stop_id)
    );
    
    if (!$update_result) {
        throw new Exception('Failed to update trip stop arrival time');
    }
    
    // IMMEDIATE RESPONSE - Don't wait for parcel updates
    $response = [
        'success' => true,
        'message' => 'Successfully arrived at stop',
        'data' => [
            'stop_id' => $stop_id,
            'arrival_time' => $current_time,
            'outlet_id' => $outlet_id
        ]
    ];
    
    // Store for background processing
    $bgStopId = $stop_id;
    $bgTripId = $trip_id;
    $bgOutletId = $outlet_id;
    $bgDriverId = $driver_id;
    $bgCompanyId = $_SESSION['company_id'];
    $bgCurrentTime = $current_time;
    $bgLatitude = $input['latitude'] ?? null;
    $bgLongitude = $input['longitude'] ?? null;
    $bgAccuracy = $input['accuracy'] ?? null;
    
    // Send response NOW
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode($response);
    
    // Flush to client
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) ob_end_flush();
        flush();
    }
    
    // ==========================================
    // BACKGROUND PROCESSING - After response sent
    // ==========================================
    ob_start();
    
    try {
        $bgSupabase = new OutletAwareSupabaseHelper();
        
        // Background Task 1: Update parcels at this stop
        try {
            $parcels = $bgSupabase->get('parcel_list', 'trip_stop_id=eq.' . urlencode($bgStopId));
            if (!empty($parcels)) {
                foreach ($parcels as $parcel) {
                    $bgSupabase->update('parcel_list',
                        ['status' => 'at_outlet', 'updated_at' => $bgCurrentTime],
                        'id=eq.' . urlencode($parcel['id'])
                    );
                }
            }
        } catch (Exception $e) {
            error_log("BG: Failed to update parcels: " . $e->getMessage());
        }
        
        // Background Task 2: Record driver location
        if ($bgLatitude !== null && $bgLongitude !== null) {
            try {
                $bgSupabase->insert('driver_locations', [
                    'driver_id' => $bgDriverId,
                    'trip_id' => $bgTripId,
                    'company_id' => $bgCompanyId,
                    'latitude' => (float) $bgLatitude,
                    'longitude' => (float) $bgLongitude,
                    'accuracy' => $bgAccuracy ? (float) $bgAccuracy : null,
                    'speed' => null,
                    'heading' => null,
                    'timestamp' => $bgCurrentTime,
                    'created_at' => $bgCurrentTime,
                    'is_manual' => true,
                    'source' => 'manual',
                    'synced_at' => $bgCurrentTime,
                    'device_timestamp' => $bgCurrentTime
                ]);
            } catch (Exception $e) {
                error_log("BG: Failed to insert location: " . $e->getMessage());
            }
        }
        
    } catch (Exception $bgException) {
        error_log("Background arrive processing error: " . $bgException->getMessage());
    }
    
    if (ob_get_level() > 0) ob_end_clean();
    exit;
    
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
