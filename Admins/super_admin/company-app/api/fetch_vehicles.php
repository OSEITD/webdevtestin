<?php
require_once __DIR__ . '/supabase-client.php';
session_start();

// Initialize Supabase client
$supabase = new SupabaseClient();
$supabaseUrl = $supabase->getUrl();
$supabaseKey = $supabase->getKey();

try {
    // Query Supabase for vehicles belonging to the company
    $apiEndpoint = $supabaseUrl . '/rest/v1/vehicle';
    $apiEndpoint .= '?select=*'; // Select all fields
    $apiEndpoint .= '&company_id=eq.' . $_SESSION['id']; // Filter by company_id
    $apiEndpoint .= '&order=created_at.desc'; // Order by creation date, newest first

    $ch = curl_init($apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    if ($curlError) {
        throw new Exception("Curl error: " . $curlError);
    }

    if ($httpCode !== 200) {
        throw new Exception("API returned status code: " . $httpCode);
    }

    $vehicles = json_decode($response, true);

    if ($vehicles === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse JSON response");
    }

    echo json_encode([
        'success' => true,
        'vehicles' => $vehicles
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}