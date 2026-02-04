<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/supabase-helper.php';

try {
    // Authentication check
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    // Get trip ID from query
    $tripId = $_GET['trip_id'] ?? null;
    
    if (!$tripId) {
        throw new Exception('Trip ID is required');
    }
    
    $companyId = $_SESSION['company_id'] ?? null;
    if (!$companyId) {
        throw new Exception('Company ID not found in session');
    }
    
    $supabase = new SupabaseHelper();
    
    // Verify trip belongs to user's company
    $trip = $supabase->get('trips', "id=eq.$tripId&company_id=eq.$companyId&select=id,driver_id,trip_status");
    
    if (empty($trip)) {
        throw new Exception('Trip not found or access denied');
    }
    
    $tripData = $trip[0];
    
    // Fetch driver locations for this trip (last 50 locations)
    $locationsQuery = "trip_id=eq.$tripId&company_id=eq.$companyId&order=timestamp.desc&limit=50&select=id,latitude,longitude,accuracy,speed,heading,timestamp,created_at,is_manual,source";
    $locations = $supabase->get('driver_locations', $locationsQuery);
    
    if (!is_array($locations)) {
        $locations = [];
    }
    
    // Reverse to get chronological order (oldest to newest)
    $locations = array_reverse($locations);
    
    // Format locations
    $formattedLocations = [];
    foreach ($locations as $loc) {
        $formattedLocations[] = [
            'id' => $loc['id'],
            'latitude' => (float)$loc['latitude'],
            'longitude' => (float)$loc['longitude'],
            'accuracy' => isset($loc['accuracy']) ? (float)$loc['accuracy'] : null,
            'speed' => isset($loc['speed']) ? (float)$loc['speed'] : null,
            'heading' => isset($loc['heading']) ? (float)$loc['heading'] : null,
            'timestamp' => $loc['timestamp'],
            'is_manual' => $loc['is_manual'] ?? false,
            'source' => $loc['source'] ?? 'gps'
        ];
    }
    
    // Get current location (most recent)
    $currentLocation = null;
    if (!empty($formattedLocations)) {
        $currentLocation = end($formattedLocations);
    }
    
    echo json_encode([
        'success' => true,
        'trip_id' => $tripId,
        'trip_status' => $tripData['trip_status'],
        'driver_id' => $tripData['driver_id'],
        'locations' => $formattedLocations,
        'current_location' => $currentLocation,
        'location_count' => count($formattedLocations),
        'last_update' => $currentLocation ? $currentLocation['timestamp'] : null
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'trip_tracking_error'
    ]);
}
?>
