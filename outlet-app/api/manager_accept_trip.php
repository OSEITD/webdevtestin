<?php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'outlet_manager') {
        throw new Exception('Only outlet managers can accept trips');
    }
    
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['trip_id'])) {
        throw new Exception('Trip ID is required');
    }
    
    $tripId = $input['trip_id'];
    $managerId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    
    if (!$companyId) {
        throw new Exception('Company ID not found in session');
    }
    
    
    $config = require __DIR__ . '/../config.php';
    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key'];
    
    if (!$supabaseUrl || !$supabaseKey) {
        throw new Exception('Supabase configuration missing');
    }
    
    
    $getTripUrl = "$supabaseUrl/rest/v1/trips?id=eq.$tripId&company_id=eq.$companyId&select=*";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $getTripUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to fetch trip: HTTP $httpCode");
    }
    
    $trips = json_decode($response, true);
    if (!is_array($trips) || empty($trips)) {
        throw new Exception('Trip not found');
    }
    
    $trip = $trips[0];
    
    
    if ($trip['trip_status'] !== 'scheduled') {
        throw new Exception('Trip cannot be accepted. Current status: ' . $trip['trip_status']);
    }
    
    
    $updateData = json_encode([
        'trip_status' => 'accepted',
        'outlet_manager_id' => $managerId,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    $updateUrl = "$supabaseUrl/rest/v1/trips?id=eq.$tripId";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $updateUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 204 && $httpCode !== 200) {
        throw new Exception("Failed to update trip: HTTP $httpCode - $response");
    }
    
    
    error_log("Manager $managerId accepted trip $tripId");
    
    
    if ($trip['driver_id']) {
        try {
            require_once __DIR__ . '/../includes/push_notification_service.php';
            require_once __DIR__ . '/../includes/supabase-helper.php';
            
            $supabaseHelper = new SupabaseHelper();
            $pushService = new PushNotificationService($supabaseHelper);
            
            // Notify driver
            $pushService->sendToDriver(
                $trip['driver_id'],
                '✅ Trip Accepted by Manager',
                'Your assigned trip has been accepted and is ready to start.',
                [
                    'trip_id' => $tripId,
                    'action' => 'trip_accepted'
                ]
            );
            
            // Notify all outlets in the route
            error_log("Sending trip accepted notifications to all outlets in route for trip: $tripId");
            $pushService->sendToAllOutletsInRoute($tripId, '✅ Trip Accepted', 'Trip has been accepted by outlet manager and is ready to start.', [
                'trip_id' => $tripId,
                'action' => 'trip_accepted',
                'url' => '/outlet-app/pages/manager_trips.php',
                'type' => 'parcel_status_change'
            ]);
            
        } catch (Exception $e) {
            
            error_log("Failed to send notification: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Trip accepted successfully',
        'trip_id' => $tripId,
        'new_status' => 'accepted'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'trip_acceptance_error'
    ]);
}
?>