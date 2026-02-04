<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access attempt - no user_id in session");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get company_id from session
$companyId = $_SESSION['company_id'] ?? null;

if (!$companyId) {
    error_log("No company_id in session for user: " . ($_SESSION['user_id'] ?? 'unknown'));
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Company ID is required',
        'debug' => [
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'session_keys' => array_keys($_SESSION)
        ]
    ]);
    exit();
}

error_log("Fetching vehicles for company: $companyId");

// Supabase credentials
$SUPABASE_URL = "https://xerpchdsykqafrsxbqef.supabase.co";
$SUPABASE_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

try {
    // Fetch vehicles for the specific company only, and only available ones
    $ch = curl_init("$SUPABASE_URL/rest/v1/vehicle?company_id=eq.$companyId&status=in.(available,out_for_delivery)&select=id,name,plate_number,status&order=name");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "apikey: $SUPABASE_API_KEY",
            "Authorization: Bearer $SUPABASE_API_KEY"
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Supabase API Error: HTTP $httpCode - Response: $response");
        throw new Exception("Failed to fetch vehicles. HTTP Code: $httpCode");
    }

    $vehicles = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg() . " - Response: " . $response);
        throw new Exception("Invalid JSON response from Supabase");
    }

    // Ensure we have an array
    if (!is_array($vehicles)) {
        error_log("Expected array response, got: " . gettype($vehicles) . " - Response: " . print_r($vehicles, true));
        $vehicles = [];
    }

    error_log("Fetched " . count($vehicles) . " vehicles for company: $companyId");

    echo json_encode([
        "success" => true,
        "vehicles" => $vehicles,
        "count" => count($vehicles),
        "message" => count($vehicles) . " vehicles found"
    ]);

} catch (Exception $e) {
    error_log("Error fetching vehicles: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch vehicles: ' . $e->getMessage(),
        'vehicles' => []
    ]);
}
?>