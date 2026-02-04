<?php
header('Content-Type: application/json');
require_once 'supabase-client.php';

try {
    error_log("Received POST data: " . print_r($_POST, true));
    error_log("Raw request body: " . file_get_contents('php://input'));
    
    // Check if we're receiving JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
        error_log("Decoded JSON data: " . print_r($_POST, true));
    }

    // Step 1: Collect form data
    $adminData = [
        'name'      => trim($_POST['name'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'phone'     => trim($_POST['phone'] ?? ''),
        'address'   => trim($_POST['address'] ?? ''),
        'status'    => 'active',
        'created_at'=> date('Y-m-d H:i:s')
    ];
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role           = 'super_admin';

    // Step 2: Validate required fields
    $requiredFields = ['name', 'email', 'phone', 'address', 'password', 'confirm_password'];
    foreach ($requiredFields as $field) {
        error_log("Checking field '$field': " . (isset($_POST[$field]) ? "set" : "not set") . ", value: " . ($_POST[$field] ?? 'null'));
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("$field is required");
        }
    }
    if (!filter_var($adminData['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    if ($password !== $confirmPassword) {
        throw new Exception('Passwords do not match');
    }

    error_log("Creating new super admin: " . $adminData['email']);

    // Step 3: Create Supabase Auth user
    $userData = [
        'email'    => $adminData['email'],
        'password' => $password,
        'user_metadata' => [
            'name'  => $adminData['name'],
            'role'  => $role
        ],
        'email_confirm' => true
    ];
    $authResult = callSupabaseWithServiceKey('auth/v1/admin/users', 'POST', $userData);
    error_log("Auth user creation result: " . json_encode($authResult));

    if (!$authResult || !isset($authResult['id'])) {
        if (isset($authResult['message']) && strpos($authResult['message'], 'already registered') !== false) {
            throw new Exception('A user with this email already exists.');
        }
        throw new Exception('Failed to create auth user: ' . json_encode($authResult));
    }
    $userId = $authResult['id'];

    // Step 4: Update profile (Supabase auto-creates one on signup)
    $profileData = [
        'full_name'      => $adminData['name'],
        'email'     => $adminData['email'],
        'phone'     => $adminData['phone'],
        'role'      => $role,
    ];
    $profileUpdate = callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'PATCH', $profileData);
    error_log("Profile update result: " . print_r($profileUpdate, true));

    // Step 5: Insert into super_admin table
    $superAdminRow = [
        'id'        => $userId,
        'name'      => $adminData['name'],
        'email'     => $adminData['email'],
        'created_at'=> $adminData['created_at']
    ];
    $superAdminResult = callSupabaseWithServiceKey('super_admin', 'POST', $superAdminRow);
    error_log("Super admin insert result: " . print_r($superAdminResult, true));

    // Check insert success
    $superAdminId = null;
    if (is_array($superAdminResult)) {
        if (isset($superAdminResult[0]['id'])) {
            $superAdminId = $superAdminResult[0]['id'];
        } elseif (isset($superAdminResult['id'])) {
            $superAdminId = $superAdminResult['id'];
        }
    }
    if (!$superAdminId) {
        // rollback on failure
        callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'DELETE');
        callSupabaseWithServiceKey("auth/v1/admin/users/{$userId}", 'DELETE');
        throw new Exception('Failed to create super admin record: ' . json_encode($superAdminResult));
    }

    // ✅ Success
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Super admin account created successfully',
        'data' => [
            'profile_id'    => $userId,
            'super_admin_id'=> $superAdminId
        ]
    ]);

} catch (Exception $e) {
    error_log("Error creating super admin: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
