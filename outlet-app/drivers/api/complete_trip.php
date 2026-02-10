<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once '../../includes/OutletAwareSupabaseHelper.php';
require_once '../../includes/push_notification_service.php';

ob_end_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}
$driver_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['trip_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing trip_id parameter']);
    exit;
}
$trip_id = $input['trip_id'];
$supabase = new OutletAwareSupabaseHelper();
try {
   
    $trip_check = $supabase->get('trips', "id=eq.$trip_id&driver_id=eq.$driver_id&company_id=eq.$company_id");
    if (empty($trip_check)) {
        echo json_encode(['success' => false, 'error' => 'Trip not found or not assigned to this driver']);
        exit;
    }
    $trip = $trip_check[0];
    if ($trip['trip_status'] === 'completed') {
        echo json_encode(['success' => false, 'error' => 'Trip is already completed']);
        exit;
    }
    $all_stops = $supabase->get('trip_stops', "trip_id=eq.$trip_id");
    $incomplete_stops = [];
    
    foreach ($all_stops as $stop) {
        if (empty($stop['departure_time'])) {
            $incomplete_stops[] = $stop;
        }
    }
    if (!empty($incomplete_stops)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Cannot complete trip: ' . count($incomplete_stops) . ' stops are not yet completed',
            'incomplete_stops' => count($incomplete_stops)
        ]);
        exit;
    }
    $current_time = date('Y-m-d\TH:i:s.u\Z');
    
    // CRITICAL PATH - Only update trip status, respond immediately
    $result = $supabase->update('trips', 
        [
            'trip_status' => 'completed',
            'arrival_time' => $current_time
        ],
        "id=eq.$trip_id"
    );
    
    if (!$result) {
        throw new Exception('Failed to update trip status');
    }
    
    // IMMEDIATE RESPONSE - User sees instant feedback
    $response = [
        'success' => true,
        'message' => 'Trip completed successfully',
        'trip_id' => $trip_id,
        'completion_time' => $current_time
    ];
    
    // Store for background
    $bgDriverId = $driver_id;
    $bgTripId = $trip_id;
    $bgCompanyId = $company_id;
    $bgCurrentTime = $current_time;
    $bgTrip = $trip;
    
    // Send response NOW
    ob_end_clean();
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
        
        // Background Task 1: Update driver status
        try {
            $bgSupabase->update('drivers', 
                [
                    'status' => 'available',
                    'current_trip_id' => null
                ],
                "id=eq.$bgDriverId"
            );
        } catch (Exception $e) {
            error_log("BG: Failed to update driver: " . $e->getMessage());
        }
        
        // Background Task 2: Update QPS stats
        try {
            $today = date('Y-m-d');
            $parcels_handled = $bgSupabase->get('parcel_list', 
                "trip_id=eq.$bgTripId&status=in.(delivered,failed_delivery)&select=id"
            );
            $parcels_count = is_array($parcels_handled) ? count($parcels_handled) : 0;
            
            $existing_qps = $bgSupabase->get('driver_qps', 
                "driver_id=eq.$bgDriverId&date=eq.$today"
            );
            
            if (!empty($existing_qps)) {
                $current = $existing_qps[0];
                $bgSupabase->update('driver_qps', [
                    'trips_completed' => ($current['trips_completed'] ?? 0) + 1,
                    'parcels_handled' => ($current['parcels_handled'] ?? 0) + $parcels_count
                ], "driver_id=eq.$bgDriverId&date=eq.$today");
            } else {
                $bgSupabase->insert('driver_qps', [
                    'driver_id' => $bgDriverId,
                    'company_id' => $bgCompanyId,
                    'date' => $today,
                    'trips_completed' => 1,
                    'parcels_handled' => $parcels_count
                ]);
            }
        } catch (Exception $e) {
            error_log("BG: Failed to update QPS: " . $e->getMessage());
        }
        
        // Background Task 3: Send notifications
        try {
            if (!empty($bgTrip['outlet_manager_id'])) {
                $shortTripId = substr($bgTripId, 0, 8);
                $notificationData = json_encode([
                    'trip_id' => $bgTripId,
                    'driver_id' => $bgDriverId,
                    'completion_time' => $bgCurrentTime
                ]);
                
                $bgSupabase->insert('notifications', [
                    'company_id' => $bgCompanyId,
                    'recipient_id' => $bgTrip['outlet_manager_id'],
                    'sender_id' => $bgDriverId,
                    'title' => 'Trip Completed',
                    'message' => "Trip {$shortTripId} is completed",
                    'notification_type' => 'trip_completed',
                    'priority' => 'high',
                    'status' => 'unread',
                    'data' => $notificationData
                ]);
                
                // Push notification
                if (class_exists('PushNotificationService')) {
                    $pushService = new PushNotificationService($bgSupabase);
                    $pushService->sendTripCompletedNotification($bgTripId, [
                        'outlet_manager_id' => $bgTrip['outlet_manager_id']
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("BG: Failed to send notifications: " . $e->getMessage());
        }
        
        error_log("Trip Completed (BG): Driver {$bgDriverId} completed trip {$bgTripId}");
        
    } catch (Exception $bgException) {
        error_log("Background complete trip error: " . $bgException->getMessage());
    }
    
    if (ob_get_level() > 0) ob_end_clean();
    exit;
} catch (Exception $e) {
    error_log("Complete trip error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred: ' . $e->getMessage()]);
}
?>
