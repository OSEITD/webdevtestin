<?php
header('Content-Type: application/json');
session_start();

require_once '../../../includes/supabase-helper.php';
require_once '../../../includes/MultiTenantSupabaseHelper.php';
require_once '../../../includes/push_notification_service.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['trip_id'])) {
        throw new Exception('Trip ID is required');
    }
    
    $tripId = $input['trip_id'];
    
    
    $trip = $supabase->get('trips', "id=eq.$tripId", '*');
    
    if (empty($trip)) {
        throw new Exception('Trip not found');
    }
    
    $tripData = $trip[0];
    
    
    $supabase->put("trips?id=eq.$tripId", [
        'trip_status' => 'in_transit',
        'departure_time' => date('Y-m-d H:i:s')
    ]);
    
    
    $bgCompanyId = $_SESSION['company_id'];
    $bgTripId = $tripId;
    $bgTripData = $tripData;
    
    
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'trip_id' => $tripId,
        'status' => 'in_transit',
        'message' => 'Trip started successfully'
    ]);
    
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        ob_end_flush();
        flush();
    }
    
    
    // Background processing - ensure no output
    ob_start(); // Start new output buffer for background processing
    
    try {
        
        require_once '../../../includes/MultiTenantSupabaseHelper.php';
        $bgSupabase = new MultiTenantSupabaseHelper($bgCompanyId);
        
        
        $originOutlet = $bgSupabase->get('outlets', "id=eq.{$bgTripData['origin_outlet_id']}", 'outlet_name');
        $destOutlet = $bgSupabase->get('outlets', "id=eq.{$bgTripData['destination_outlet_id']}", 'outlet_name');
        
        $bgTripData['origin_outlet_name'] = !empty($originOutlet) ? $originOutlet[0]['outlet_name'] : 'Origin';
        $bgTripData['destination_outlet_name'] = !empty($destOutlet) ? $destOutlet[0]['outlet_name'] : 'Destination';
        
        
        $parcelList = $bgSupabase->get('parcel_list', "trip_id=eq.$bgTripId", 'parcel_id');
        $bgTripData['parcel_ids'] = array_column($parcelList, 'parcel_id');
        
        
        $pushService = new PushNotificationService($bgSupabase);
        $pushService->sendTripStartedNotification($bgTripId, $bgTripData);
    } catch (Exception $bgException) {
        error_log("Background notification error: " . $bgException->getMessage());
    }
    
    // Clean up any background output
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    exit;
    
} catch (Exception $e) {
    error_log("Start trip error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
