<?php
require_once __DIR__ . '/supabase-client.php';
session_start();

// Initialize Supabase client
$supabase = new SupabaseClient();
$supabaseUrl = $supabase->getUrl();
$supabaseKey = $supabase->getKey();

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields'
    ]);
    exit;
}

try {
    // Update vehicle status in Supabase
    $apiEndpoint = $supabaseUrl . '/rest/v1/vehicle';
    $apiEndpoint .= '?id=eq.' . $data['id'];
    $apiEndpoint .= '&company_id=eq.' . $_SESSION['id']; // Ensure vehicle belongs to company

    $updateData = [
        'status' => $data['status']
    ];

    $ch = curl_init($apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($curlError) {
        throw new Exception("Curl error: " . $curlError);
    }

    if ($httpCode !== 204) { // Supabase returns 204 for successful PATCH
        throw new Exception("API returned status code: " . $httpCode);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Vehicle status updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}