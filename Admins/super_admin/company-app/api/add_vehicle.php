<?php
// Set error handling to catch all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("API endpoint hit: add_vehicle.php");

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error in add_vehicle.php: $errstr");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

// Start session to get company ID
session_start();

// Initialize Supabase client
$supabase = new SupabaseClient();
$supabaseUrl = $supabase->getUrl();
$supabaseKey = $supabase->getKey();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
$required_fields = ['name', 'plate_number', 'status'];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || strlen(trim((string)$data[$field])) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required field: ' . str_replace('_', ' ', $field)]);
        exit;
    }
}

// Normalize and validate status against canonical DB values
$canonicalStatuses = ['available', 'unavailable', 'out_for_delivery'];
$statusRaw = strtolower(trim((string)$data['status']));
$statusMap = [
    'active' => 'available',
    'inactive' => 'unavailable',
    'maintenance' => 'unavailable',
    'assigned' => 'out_for_delivery'
];

if (isset($statusMap[$statusRaw])) {
    $status = $statusMap[$statusRaw];
} else {
    $status = $statusRaw;
}

if (!in_array($status, $canonicalStatuses, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status value. Allowed: ' . implode(', ', $canonicalStatuses)]);
    exit;
}

try {
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("POST data: " . print_r($data, true));

    error_log("Creating vehicle with data: " . print_r($data, true));

    // Insert directly without checking (we'll let Supabase handle unique constraints)
    $apiEndpoint = $supabaseUrl . '/rest/v1/vehicle';  // Using singular form to match existing table
    error_log("Attempting to create vehicle at: " . $apiEndpoint);

    $vehicleData = [
        'name' => $data['name'],
        'plate_number' => $data['plate_number'],
        'status' => $status,
        'company_id' => $_SESSION['id'] // Using the company ID from session
    ];
    
    error_log("Vehicle data to be sent: " . json_encode($vehicleData));
    error_log("Attempting to create vehicle at: " . $apiEndpoint);
    error_log("Vehicle data: " . json_encode($vehicleData));

    $ch = curl_init($apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vehicleData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $result = json_decode($response, true);

    error_log("Supabase Response Code: " . $httpCode);
    error_log("Curl Error (if any): " . $curlError);
    error_log("Raw Response: " . $response);
    error_log("Decoded Response: " . print_r($result, true));
    
    if ($httpCode !== 201 || $curlError) {
        $errorMsg = '';
        if ($curlError) {
            $errorMsg = "Curl error: " . $curlError;
        } else if ($httpCode === 409) {
            $errorMsg = "A vehicle with this plate number already exists";
        } else {
            $errorMsg = "Error creating vehicle. Status code: " . $httpCode;
            if (is_array($result)) {
                if (isset($result['message'])) {
                    $errorMsg .= " - " . $result['message'];
                } else if (isset($result['error'])) {
                    $errorMsg .= " - " . $result['error'];
                }
            }
            error_log("Full response for debugging: " . print_r($result, true));
        }

        error_log("Failed to create vehicle. " . $errorMsg);
        throw new Exception($errorMsg);
    }

    $vehicleId = $result[0]['id']; // Get the new vehicle ID
    curl_close($ch);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Vehicle added successfully',
        'data' => [
            'vehicle' => [
                'id' => $vehicleId,
                'name' => $data['name'],
                'plate_number' => $data['plate_number'],
                'status' => $data['status']
            ]
        ]
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'add_vehicle.php');
}