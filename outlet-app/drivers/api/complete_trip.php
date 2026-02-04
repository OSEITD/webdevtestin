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
    
    $result = $supabase->update('trips', 
        [
            'trip_status' => 'completed',
            'arrival_time' => $current_time
        ],
        "id=eq.$trip_id"
    );
    if ($result) {
     
        $supabase->update('drivers', 
            [
                'status' => 'available',
                'current_trip_id' => null
            ],
            "id=eq.$driver_id"
        );
        error_log("Trip Completed: Driver {$driver_id} completed trip {$trip_id} at {$current_time}");
        
        try {
            $today = date('Y-m-d');
         
            $parcels_handled = $supabase->get('parcel_list', [
                'trip_id' => "eq.$trip_id",
                'status' => 'in.(delivered,failed_delivery)',
                'select' => 'id'
            ]);
            $parcels_count = $parcels_handled ? count($parcels_handled) : 0;
           
            $existing_qps = $supabase->get('driver_qps', [
                'driver_id' => "eq.$driver_id",
                'date' => "eq.$today"
            ]);
            
            if ($existing_qps && count($existing_qps) > 0) {
                $current = $existing_qps[0];
                $supabase->update('driver_qps', [
                    'trips_completed' => ($current['trips_completed'] ?? 0) + 1,
                    'parcels_handled' => ($current['parcels_handled'] ?? 0) + $parcels_count
                ], "driver_id=eq.$driver_id&date=eq.$today");
            } else {

                $supabase->insert('driver_qps', [
                    'driver_id' => $driver_id,
                    'company_id' => $company_id,
                    'date' => $today,
                    'trips_completed' => 1,
                    'parcels_handled' => $parcels_count
                ]);
            }

            error_log("Updated driver_qps for driver {$driver_id}: +1 trip, +{$parcels_count} parcels");
        } catch (Exception $e) {
            error_log("Error updating driver_qps: " . $e->getMessage());

        }

        $notificationsSent = 0;
        try {
            if (!empty($trip['outlet_manager_id'])) {
                $shortTripId = substr($trip_id, 0, 8);

                $notificationData = json_encode([
                    'trip_id' => $trip_id,
                    'driver_id' => $driver_id,
                    'completion_time' => $current_time
                ]);


                $supabase->insert('notifications', [
                    'company_id' => $company_id,
                    'recipient_id' => $trip['outlet_manager_id'],
                    'sender_id' => $driver_id,
                    'title' => ' Trip Completed',
                    'message' => "Trip {$shortTripId} is completed",
                    'notification_type' => 'trip_completed',
                    'priority' => 'high',
                    'status' => 'unread',
                    'data' => $notificationData
                ]);
                $notificationsSent++;


                $pushService = new PushNotificationService($supabase);
                $pushService->sendTripCompletedNotification($trip_id, [
                    'outlet_manager_id' => $trip['outlet_manager_id']
                ]);
            }
        } catch (Exception $e) {
            error_log("Error sending trip completion notification: " . $e->getMessage());
        }
        echo json_encode([
            'success' => true,
            'message' => 'Trip completed successfully',
            'trip_id' => $trip_id,
            'completion_time' => $current_time,
            'notifications_sent' => $notificationsSent
        ]);

    } else {
        throw new Exception('Failed to update trip status');
    }
} catch (Exception $e) {
    error_log("Complete trip error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred: ' . $e->getMessage()]);
}
?>
