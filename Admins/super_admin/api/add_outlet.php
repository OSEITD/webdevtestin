<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('add_outlet.php');
ErrorHandler::requireMethod('POST', 'add_outlet.php');

require_once 'supabase-client.php';

try {
    // Check if we're receiving JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // Step 1: Collect form data
    $outletData = [
        'outlet_name' => trim($_POST['outlet_name'] ?? ''),
        'company_id' => trim($_POST['company_id'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Step 2: Validate required fields
    $requiredFields = ['outlet_name', 'company_id', 'address', 'contact_person', 'contact_phone', 'contact_email', 'password', 'confirm_password'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("$field is required");
        }
    }
    if (!filter_var($outletData['contact_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    if ($password !== $confirmPassword) {
        throw new Exception('Passwords do not match');
    }
    
    // Validate company_id format
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $outletData['company_id'])) {
        throw new Exception('Invalid company ID format');
    }

    // Step 3: Create Supabase Auth user for outlet admin
    $userData = [
        'email' => $outletData['contact_email'],
        'password' => $password,
        'user_metadata' => [
            'full_name' => $outletData['contact_person'],
            'role' => 'outlet_manager',
            'outlet_name' => $outletData['outlet_name'],
            'company_id' => $outletData['company_id']
        ],
        'email_confirm' => true
    ];
    
    $authResult = callSupabaseWithServiceKey('auth/v1/admin/users', 'POST', $userData);

    if (!$authResult || !isset($authResult['id'])) {
        if (isset($authResult['message']) && strpos($authResult['message'], 'already registered') !== false) {
            throw new Exception('A user with this email already exists.');
        }
        throw new Exception('Failed to create auth user: ' . json_encode($authResult));
    }
    $userId = $authResult['id'];

    // Step 4: Update profile
    $profileData = [
        'full_name' => $outletData['contact_person'],
        'email' => $outletData['contact_email'],
        'phone' => $outletData['contact_phone'],
        'role' => 'outlet_manager'
    ];
    $profileUpdate = callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'PATCH', $profileData);

    // Step 5: Insert into outlets table
    $outletRow = [
        'outlet_name' => $outletData['outlet_name'],
        'company_id' => $outletData['company_id'],
        'address' => $outletData['address'],
        'contact_person' => $outletData['contact_person'],
        'contact_phone' => $outletData['contact_phone'],
        'contact_email' => $outletData['contact_email'],
        'status' => $outletData['status'],
        'created_at' => $outletData['created_at'],
        'manager_id' => $userId,
    ];
    $outletResult = callSupabaseWithServiceKey('outlets', 'POST', $outletRow);

    // Check insert success
    $outletId = null;
    if (is_array($outletResult)) {
        if (isset($outletResult[0]['id'])) {
            $outletId = $outletResult[0]['id'];
        } elseif (isset($outletResult['id'])) {
            $outletId = $outletResult['id'];
        }
    }
    if (!$outletId) {
        // rollback on failure
        callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'DELETE');
        callSupabaseWithServiceKey("auth/v1/admin/users/{$userId}", 'DELETE');
        throw new Exception('Failed to create outlet record: ' . json_encode($outletResult));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Outlet created successfully',
        'outlet' => [
            [
                'id' => $outletId,
                'user_id' => $userId
            ]
        ]
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'add_outlet.php', 400);
}
