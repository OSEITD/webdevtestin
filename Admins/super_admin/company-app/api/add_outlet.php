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
$required_fields = ['outletName', 'address', 'contactPerson', 'contact_email', 'contact_phone', 
                   'password', 'status'];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Validate password length
if (strlen($data['password']) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters long']);
    exit;
}

try {
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("POST data: " . print_r($data, true));

    // First check if an outlet with this name already exists for this company
    $checkUrl = $supabaseUrl . '/rest/v1/outlets?outlet_name=eq.' . urlencode($data['outletName']) . 
                '&company_id=eq.' . $_SESSION['id'];
    
    error_log("Check URL: " . $checkUrl);
    $ch = curl_init($checkUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    
    $checkResponse = curl_exec($ch);
    $existingOutlets = json_decode($checkResponse, true);
    curl_close($ch);
    
    if (!empty($existingOutlets)) {
        throw new Exception('An outlet with this name already exists in your company');
    }

    // If we get here, the outlet name is unique for this company
    $outletData = [
        'outlet_name' => $data['outletName'],
        'address' => $data['address'],
        'contact_person' => $data['contactPerson'],
        'contact_email' => $data['contact_email'],
        'contact_phone' => $data['contact_phone'],
        'status' => $data['status'],
        'company_id' => $_SESSION['id'] // Using the company ID from session
    ];

    $apiEndpoint = $supabaseUrl . '/rest/v1/outlets';
    error_log("Attempting to create outlet at: " . $apiEndpoint);
    error_log("Outlet data: " . json_encode($outletData));

    $ch = curl_init($apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($outletData));
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
            $errorMsg = "No outlet ID in response";
        }

        if (is_array($result)) {
            if (isset($result['message'])) {
                $errorMsg .= " - " . $result['message'];
            } else if (isset($result['error'])) {
                $errorMsg .= " - " . $result['error'];
            }
        }

        error_log("Failed to create outlet. " . $errorMsg);
        throw new Exception($errorMsg);
    }

    $outletId = $result[0]['id']; // Get the new outlet ID
    curl_close($ch);

    // Now create the auth user
    $authUrl = $supabaseUrl . '/auth/v1/signup';
    $authPayload = [
        'email' => $data['contact_email'],
        'password' => $data['password'],
        'data' => [
            'full_name' => $data['contactPerson'],
            'role' => 'outlet_manager',
            'outlet_id' => $outletId // Link the user to the outlet
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
    error_log("Auth Response: " . print_r($response, true));
    error_log("HTTP Code: " . $httpCode);
    error_log("Auth Data: " . print_r($authData, true));
    
    if ($httpCode !== 200) {
        if (isset($authData['message'])) {
            throw new Exception('Auth error: ' . $authData['message']);
        } else if (isset($authData['error_description'])) {
            throw new Exception('Auth error: ' . $authData['error_description']);
        } else if (isset($authData['error'])) {
            throw new Exception('Auth error: ' . $authData['error']);
        } else {
            throw new Exception('Failed to create user account. Status code: ' . $httpCode);
        }
    }
    
    if (!isset($authData['user']) || !isset($authData['user']['id'])) {
        error_log("Invalid auth response structure: " . print_r($authData, true));
        throw new Exception('Invalid authentication response structure');
    }
    
    $userId = $authData['user']['id'];

    // Now create the outlet record
    $outletData = [
        'name' => $data['outletName'],
        'address' => $data['address'],
        'contact_person' => $data['contactPerson'],
        'contact_email' => $data['contact_email'],
        'contact_phone' => $data['contact_phone'],
        'status' => $data['status'],
        'company_id' => $_SESSION['id'], // Use the company ID from session
        'manager_id' => $userId
    ];

    // Add optional opening/closing times if provided
    if (!empty($data['openingTime'])) {
        $outletData['opening_time'] = $data['openingTime'];
    }

    if (!empty($data['closingTime'])) {
        $outletData['closing_time'] = $data['closingTime'];
    }

    // Return success response with both outlet and user data
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Outlet and manager account created successfully',
        'data' => [
            'outlet' => [
                'id' => $outletId,
                'name' => $data['outletName']
            ],
            'user' => [
                'id' => $userId,
                'email' => $data['contact_email']
            ]
        ]
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'add_outlet.php');
}