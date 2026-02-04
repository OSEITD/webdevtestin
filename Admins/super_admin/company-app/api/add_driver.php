<?php
// Set error handling to catch all errors
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

// Start session to get company ID
session_start();

// Ensure company id present in session
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated: company session missing']);
    exit;
}

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
$required_fields = ['driverName', 'driver_phone', 'driver_email', 'license_number', 'status'];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Validate status value
$allowed_statuses = ['available', 'busy', 'offline'];
if (!in_array($data['status'], $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => "Invalid status value. Must be one of: " . implode(', ', $allowed_statuses)]);
    exit;
}

try {
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("POST data: " . print_r($data, true));

    // First check if a driver with this email already exists
    $checkUrl = $supabaseUrl . '/rest/v1/drivers?driver_email=eq.' . urlencode($data['driver_email']) . 
                '&company_id=eq.' . $_SESSION['id'];
    
    error_log("Check URL: " . $checkUrl);
    $ch = curl_init($checkUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    
    $checkResponse = curl_exec($ch);
    $existingDrivers = json_decode($checkResponse, true);
    curl_close($ch);
    
    if (!empty($existingDrivers)) {
        throw new Exception('A driver with this email already exists in your company');
    }

    // First create the auth user account
    $authUrl = $supabaseUrl . '/auth/v1/signup';
    // If client supplied a password use it, otherwise generate a temp one
    $tempPassword = isset($data['password']) && !empty($data['password']) ? $data['password'] : bin2hex(random_bytes(8));
    
    $authPayload = [
        'email' => $data['driver_email'],
        'password' => $tempPassword,
        'data' => [
            'full_name' => $data['driverName'],
            'role' => 'driver',
            'phone' => $data['driver_phone']
        ]
    ];

    $ch = curl_init($authUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($authPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $authData = json_decode($response, true);
    
    // Debug information
    error_log("Auth Response: " . $response);
    error_log("HTTP Code: " . $httpCode);
    error_log("Auth Data: " . print_r($authData, true));
    
    // Accept any 2xx code as success from the auth service
    if ($httpCode < 200 || $httpCode >= 300) {
        $error = "Failed to create driver account";
        if (isset($authData['msg'])) {
            $error .= ": " . $authData['msg'];
        } else if (isset($authData['message'])) {
            $error .= ": " . $authData['message'];
        } else if (isset($authData['error_description'])) {
            $error .= ": " . $authData['error_description'];
        } else {
            $error .= ": HTTP {$httpCode} - " . substr($response, 0, 300);
        }
        throw new Exception($error);
    }
    
    // Try to extract user id from known locations
    $userId = null;
    if (isset($authData['user']) && isset($authData['user']['id'])) {
        $userId = $authData['user']['id'];
    } else if (isset($authData['id'])) {
        $userId = $authData['id'];
    }
    if (!$userId) {
        // Some Supabase responses return different shapes; attempt to find an id in nested arrays
        $flat = json_decode($response, true);
        if (is_array($flat)) {
            array_walk_recursive($flat, function($v, $k) use (&$userId) { if (!$userId && $k === 'id') $userId = $v; });
        }
    }
    if (!$userId) throw new Exception('Invalid authentication response structure: missing user id');

    // Now create the driver record with the user ID
    $driverData = [
        'id' => $userId, // Use the auth user ID as the driver ID
        'driver_name' => $data['driverName'],
        'driver_phone' => $data['driver_phone'],
        'driver_email' => $data['driver_email'],
        'license_number' => $data['license_number'],
        'status' => $data['status'],
        'company_id' => $_SESSION['id'] // Using the company ID from session
    ];

    $apiEndpoint = $supabaseUrl . '/rest/v1/drivers';
    error_log("Attempting to create driver at: " . $apiEndpoint);
    error_log("Driver data: " . json_encode($driverData));

    $ch = curl_init($apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($driverData));
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
    
    if ($httpCode !== 201 || !$result || !is_array($result) || empty($result[0]['id'])) {
        $errorMsg = '';
        if ($curlError) {
            $errorMsg = "Curl error: " . $curlError;
        } else if ($httpCode !== 201) {
            $errorMsg = "API returned status code " . $httpCode;
        } else if (!$result || !is_array($result)) {
            $errorMsg = "Invalid response format";
        } else if (empty($result[0]['id'])) {
            $errorMsg = "No driver ID in response";
        }

        if (is_array($result)) {
            if (isset($result['message'])) {
                $errorMsg .= " - " . $result['message'];
            } else if (isset($result['error'])) {
                $errorMsg .= " - " . $result['error'];
            }
        }

        error_log("Failed to create driver. " . $errorMsg);
        throw new Exception($errorMsg);
    }

    $driverId = $result[0]['id']; // Get the new driver ID
    curl_close($ch);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Driver added successfully! Please provide them with their login credentials.',
        'data' => [
            'driver' => [
                'id' => $userId,
                'name' => $data['driverName'],
                'email' => $data['driver_email'],
                'temp_password' => $tempPassword // Include the temporary password in the response
            ]
        ]
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'add_driver.php');
}