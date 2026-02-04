<?php
require_once __DIR__ . '/../includes/OutletAwareSupabaseHelper.php';
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight quickly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Quick auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

// Quick validation
if (!$latitude || !$longitude || !is_numeric($latitude) || !is_numeric($longitude)) {
    echo json_encode(['success' => false, 'error' => 'Invalid coordinates']);
    exit;
}

if (abs($latitude) > 90 || abs($longitude) > 180 || ($latitude == 0 && $longitude == 0)) {
    echo json_encode(['success' => false, 'error' => 'Coordinates out of range']);
    exit;
}

$driver_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$trip_id = $input['trip_id'] ?? null;
$accuracy = $input['accuracy'] ?? null;
$speed = $input['speed'] ?? null;
$heading = $input['heading'] ?? null;

try {
    $supabase = new OutletAwareSupabaseHelper();
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    
    $locationData = [
        'driver_id' => $driver_id,
        'trip_id' => $trip_id,
        'company_id' => $company_id,
        'latitude' => floatval($latitude),
        'longitude' => floatval($longitude),
        'accuracy' => $accuracy ? floatval($accuracy) : null,
        'speed' => $speed ? floatval($speed) : null,
        'heading' => $heading ? floatval($heading) : null,
        'timestamp' => $timestamp,
        'is_manual' => false,
        'source' => 'gps',
        'synced_at' => $timestamp,
        'device_timestamp' => $timestamp
    ];
    
    // Use UPSERT via Supabase - insert with conflict resolution on driver_id+trip_id
    $serviceKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
    
    // Fast upsert using Supabase's on_conflict
    $url = 'https://xerpchdsykqafrsxbqef.supabase.co/rest/v1/driver_locations';
    $headers = [
        "apikey: " . $serviceKey,
        "Authorization: Bearer " . $serviceKey,
        "Content-Type: application/json",
        "Prefer: resolution=merge-duplicates,return=minimal"
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($locationData),
        CURLOPT_TIMEOUT => 5 // 5 second timeout for fast response
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode([
            'success' => true,
            'message' => 'Location updated'
        ]);
    } else {
        // Fallback to helper method if upsert fails
        $result = $supabase->insert('driver_locations', $locationData);
        echo json_encode([
            'success' => (bool)$result,
            'message' => $result ? 'Location saved' : 'Failed to save'
        ]);
    }
} catch (Exception $e) {
    error_log("Update driver location error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to update location']);
}
?>
