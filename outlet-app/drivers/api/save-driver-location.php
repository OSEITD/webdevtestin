<?php
require_once __DIR__ . '/error_handler.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'] ?? null;
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';
$supabase = new OutletAwareSupabaseHelper();
try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['latitude']) || !isset($input['longitude'])) {
        throw new Exception('Latitude and longitude are required');
    }
    $latitude = floatval($input['latitude']);
    $longitude = floatval($input['longitude']);
    $accuracy = isset($input['accuracy']) ? floatval($input['accuracy']) : null;
    $speed = isset($input['speed']) ? floatval($input['speed']) : null;
    $heading = isset($input['heading']) ? floatval($input['heading']) : null;
    $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');
    
    $activeTrips = $supabase->get('trips', 
        "driver_id=eq.{$driverId}&trip_status=in.('accepted','in_transit')&order=created_at.desc&limit=1", 
        'id');
    
    $tripId = null;
    if (!empty($activeTrips)) {
        $tripId = $activeTrips[0]['id'];
    }
    
    
    if (!$tripId) {
        error_log("Driver {$driverId} has no active trip - location tracking skipped");
        echo json_encode([
            'success' => false,
            'error' => 'No active trip',
            'message' => 'Location tracking requires an active trip. Please accept or start a trip first.'
        ]);
        exit();
    }
    
    $locationData = [
        'driver_id' => $driverId,
        'trip_id' => $tripId,
        'company_id' => $companyId,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timestamp' => date('c', strtotime($timestamp)), 
    ];
    
    if ($accuracy !== null) {
        $locationData['accuracy'] = $accuracy;
    }
    if ($speed !== null) {
        $locationData['speed'] = $speed;
    }
    if ($heading !== null) {
        $locationData['heading'] = $heading;
    }
    
    error_log("Attempting to save location data: " . json_encode($locationData));
    
    
    try {
        $url = "https://xerpchdsykqafrsxbqef.supabase.co/rest/v1/driver_locations";
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3MzEzNDE1NjUsImV4cCI6MjA0NjkxNzU2NX0.2c0y3CgjdnT38i4r0qHuwTuHgAOCDIZyqXoRG5UD3W0',
                    'apikey: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3MzEzNDE1NjUsImV4cCI6MjA0NjkxNzU2NX0.2c0y3CgjdnT38i4r0qHuwTuHgAOCDIZyqXoRG5UD3W0',
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ],
                'content' => json_encode($locationData),
                'ignore_errors' => true 
            ]
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        
        $httpResponse = $http_response_header ?? [];
        $statusLine = $httpResponse[0] ?? '';
        
        if ($response === false || strpos($statusLine, '400') !== false || strpos($statusLine, '500') !== false) {
            $errorMsg = $response ?: 'No response from server';
            error_log("Supabase insert error - Status: $statusLine, Response: $errorMsg");
            throw new Exception("Database error: " . $errorMsg);
        }
        
        $result = json_decode($response, true);
        
        echo json_encode([
            'success' => true,
            'message' => 'Location saved successfully',
            'trip_id' => $tripId,
            'location_id' => $result[0]['id'] ?? null
        ]);
    } catch (Exception $insertError) {
        error_log("Insert exception: " . $insertError->getMessage());
        throw new Exception('Failed to save location: ' . $insertError->getMessage());
    }
} catch (Exception $e) {
    error_log("Error saving driver location: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
