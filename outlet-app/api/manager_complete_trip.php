<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/supabase.php';
require_once '../includes/push_notification_service.php';

try {
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'outlet_manager') {
        throw new Exception('Access denied');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }

    $tripId = $input['trip_id'] ?? '';
    $managerId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'];
    $notes = $input['notes'] ?? '';

    if (empty($tripId)) {
        throw new Exception('Trip ID is required');
    }

    $supabase = new SupabaseHelper();

    
    $trip = $supabase->get('trips', "id=eq.$tripId&company_id=eq.$companyId", '*');
    
    if (empty($trip)) {
        throw new Exception('Trip not found or access denied');
    }

    $tripData = $trip[0];

    
    if (!in_array($tripData['trip_status'], ['accepted', 'in_transit', 'at_outlet'])) {
        throw new Exception('Trip cannot be completed from current status: ' . $tripData['trip_status']);
    }

    
    $updateData = json_encode([
        'trip_status' => 'completed',
        'arrival_time' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    $updateResult = $supabase->update('trips', "id=eq.$tripId", $updateData);

    if (!$updateResult) {
        throw new Exception('Failed to complete trip');
    }

    
    $parcelUpdate = json_encode(['status' => 'delivered']);
    $supabase->update('parcel_list', "trip_id=eq.$tripId", $parcelUpdate);
    
    
    $outletIds = array_unique(array_filter([$tripData['origin_outlet_id'], $tripData['destination_outlet_id']]));
    $outletIdsStr = implode(',', $outletIds);
    $outlets = $supabase->get('outlets', "id=in.($outletIdsStr)", 'id,outlet_name');
    
    $outletMap = [];
    foreach ($outlets as $outlet) {
        $outletMap[$outlet['id']] = $outlet['outlet_name'];
    }
    
    $originOutletName = $outletMap[$tripData['origin_outlet_id']] ?? 'Unknown';
    $destinationOutletName = $outletMap[$tripData['destination_outlet_id']] ?? 'Unknown';
    
    
    $driverData = null;
    if ($tripData['driver_id']) {
        $driver = $supabase->get('drivers', "id=eq.{$tripData['driver_id']}", 'id,driver_name');
        if (!empty($driver)) {
            $driverData = $driver[0];
        }
    }

    
    error_log("Trip $tripId completed by manager $managerId");
    
    
    $responseJson = json_encode([
        'success' => true,
        'message' => 'Trip completed successfully',
        'trip_id' => $tripId,
        'completed_at' => date('Y-m-d H:i:s')
    ]);
    
    http_response_code(200);
    header('Connection: close');
    header('Content-Length: ' . strlen($responseJson));
    echo $responseJson;
    
    if (ob_get_level() > 0) ob_end_flush();
    flush();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    
    ignore_user_abort(true);
    set_time_limit(30);
    
    
    if ($driverData) {
        try {
            
            $notificationId = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            $notificationData = json_encode([
                'id' => $notificationId,
                'company_id' => $companyId,
                'outlet_id' => $tripData['destination_outlet_id'],
                'recipient_id' => $tripData['driver_id'],
                'sender_id' => $managerId,
                'title' => '✅ Trip Completed',
                'message' => sprintf(
                    'Your trip from %s to %s has been completed',
                    $originOutletName,
                    $destinationOutletName
                ),
                'notification_type' => 'delivery_completed',
                'status' => 'unread',
                'priority' => 'high',
                'data' => json_encode([
                    'trip_id' => $tripId,
                    'action' => 'trip_completed',
                    'url' => '/drivers/dashboard.php'
                ]),
                'created_at' => date('c')
            ]);
            
            $supabase->post('notifications', $notificationData);
            
            
            $pushService = new PushNotificationService($supabase);
            
            // Notify driver
            $title = '✅ Trip Completed';
            $body = sprintf(
                'Trip from %s to %s has been completed',
                $originOutletName,
                $destinationOutletName
            );
            
            $pushData = [
                'type' => 'trip_completed',
                'trip_id' => $tripId,
                'url' => 'http://acme.localhost/drivers/dashboard.php',
                'timestamp' => time(),
                'actions' => [
                    ['action' => 'view_dashboard', 'title' => '📊 View Dashboard'],
                    ['action' => 'dismiss', 'title' => '❌ Dismiss']
                ]
            ];
            
            $pushService->sendToDriver($tripData['driver_id'], $title, $body, $pushData);
            
            // Notify ALL outlets in the route (origin, destination, and stops)
            error_log("Sending trip completed notifications to all outlets in route for trip: $tripId");
            $pushService->sendToAllOutletsInRoute($tripId, '✅ Trip Completed', sprintf(
                'Trip from %s to %s has been completed',
                $originOutletName,
                $destinationOutletName
            ), [
                'trip_id' => $tripId,
                'action' => 'trip_completed',
                'url' => '/outlet-app/pages/manager_trips.php',
                'type' => 'delivery_completed'
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
        }
    }
    
    exit(0);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>