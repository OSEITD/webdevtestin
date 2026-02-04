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
    
    if (!isset($input['trip_id']) || !isset($input['outlet_id'])) {
        throw new Exception('Trip ID and Outlet ID are required');
    }
    
    $tripId = $input['trip_id'];
    $outletId = $input['outlet_id'];
    
    $trip = $supabase->get('trips', "id=eq.$tripId", '*');
    
    if (empty($trip)) {
        throw new Exception('Trip not found');
    }
    
    $tripData = $trip[0];
    
    $pushService = new PushNotificationService($supabase);
    $notificationResults = $pushService->sendTripArrivedAtOutletNotification($tripId, $outletId, $tripData);
    
    echo json_encode([
        'success' => true,
        'trip_id' => $tripId,
        'outlet_id' => $outletId,
        'notifications' => $notificationResults,
        'message' => 'Arrival notifications sent'
    ]);
    
} catch (Exception $e) {
    error_log("Arrive at outlet error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
