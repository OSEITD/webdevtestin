<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/OutletAwareSupabaseHelper.php';
require_once __DIR__ . '/../includes/push_notification_service.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'outlet_manager') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    
    $input = json_decode(file_get_contents('php://input'), true);
    $tripId = $input['trip_id'] ?? $_GET['trip_id'] ?? $_POST['trip_id'] ?? null;
    if (empty($tripId)) {
        throw new Exception('trip_id is required');
    }
    $managerId = $_SESSION['user_id'];

    $supabase = new OutletAwareSupabaseHelper();

    
    $trip = $supabase->get('trips', 'id=eq.' . urlencode($tripId), '*');
    if (empty($trip)) {
        throw new Exception('Trip not found');
    }
    $tripData = $trip[0];

    
    
    
    
    $managerOutlet = $_SESSION['outlet_id'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    $authorized = false;

    
    $profile = $supabase->get('profiles', 'id=eq.' . urlencode($managerId) . '&select=id,role,outlet_id');
    if (!empty($profile) && isset($profile[0]['role']) && $profile[0]['role'] === 'outlet_manager' && isset($profile[0]['outlet_id']) && $profile[0]['outlet_id'] == $tripData['origin_outlet_id']) {
        $authorized = true;
    }

    
    if (!$authorized) {
        $originOutlet = $supabase->get('outlets', 'id=eq.' . urlencode($tripData['origin_outlet_id']) . '&select=id,manager_id');
        if (!empty($originOutlet) && isset($originOutlet[0]['manager_id']) && $originOutlet[0]['manager_id'] == $managerId) {
            $authorized = true;
        }
    }

    
    if (!$authorized && isset($tripData['outlet_manager_id']) && $tripData['outlet_manager_id'] == $managerId) {
        $authorized = true;
    }

    
    if (!$authorized && $userRole === 'super_admin') {
        $authorized = true;
    }

    if (!$authorized) {
        throw new Exception('You are not authorized to start this trip');
    }

    
    if (!in_array($tripData['trip_status'], ['scheduled','accepted'])) {
        throw new Exception('Trip cannot be started from current status: ' . $tripData['trip_status']);
    }

    $now = date('Y-m-d H:i:s');
    $update = [
        'trip_status' => 'in_transit',
        'departure_time' => $now,
        'updated_at' => $now
    ];

    $ok = $supabase->update('trips', $update, 'id=eq.' . urlencode($tripId));
    if (!$ok) {
        throw new Exception('Failed to update trip status');
    }

    
    try {
        $parcelList = $supabase->get('parcel_list', 'trip_id=eq.' . urlencode($tripId) . '&select=parcel_id');
        $parcelIds = array_values(array_filter(array_map(function($p){ return $p['parcel_id'] ?? null; }, $parcelList)));
        
        if (!empty($parcelList)) {
            $supabase->update('parcel_list', ['status' => 'in_transit', 'updated_at' => $now], 'trip_id=eq.' . urlencode($tripId));
        }
        
        if (!empty($parcelIds)) {
            $idsStr = implode(',', array_map('urlencode', $parcelIds));
            $supabase->update('parcels', ['status' => 'in_transit', 'updated_at' => $now], 'id=in.(' . $idsStr . ')');
        }
    } catch (Exception $e) {
        error_log('Manager start trip parcel update failed: ' . $e->getMessage());
    }

    
    try {
        $push = new PushNotificationService($supabase);
        
        // Send notification to driver
        if (!empty($tripData['driver_id'])) {
            error_log("Sending trip started notification to driver: {$tripData['driver_id']}");
            $push->sendToDriver($tripData['driver_id'], '🚗 Trip Started', 'The trip has been started by the outlet manager.', ['trip_id' => $tripId, 'action' => 'trip_started']);
        }
        
        // Send notification to ALL outlets in the route (origin, destination, and stops)
        error_log("Sending trip started notifications to all outlets in route for trip: $tripId");
        $push->sendToAllOutletsInRoute($tripId, '🚗 Trip Started', "Trip has started and is now in transit", [
            'trip_id' => $tripId,
            'action' => 'trip_started',
            'url' => '/outlet-app/pages/manager_trips.php',
            'type' => 'parcel_status_change'
        ]);
        
    } catch (Exception $e) {
        error_log('Push notification failed: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'trip_id' => $tripId, 'status' => 'in_transit', 'departure_time' => $now]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
