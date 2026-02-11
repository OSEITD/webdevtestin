<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('add_company.php');
ErrorHandler::requireMethod('POST', 'add_company.php');

require_once 'supabase-client.php';

try {
    // Check if we're receiving JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // Step 1: Collect form data
    $companyData = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'subdomain' => trim($_POST['subdomain'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $commission = floatval($_POST['commission'] ?? 0);

    // Step 2: Validate required fields
    $requiredFields = ['company_name', 'subdomain', 'contact_person', 'contact_email', 'contact_phone', 'address', 'password', 'confirm_password'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("$field is required");
        }
    }
    if (!filter_var($companyData['contact_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    if ($password !== $confirmPassword) {
        throw new Exception('Passwords do not match');
    }

    // Step 3: Create Supabase Auth user for company admin
    $userData = [
        'email' => $companyData['contact_email'],
        'password' => $password,
        'user_metadata' => [
            'full_name' => $companyData['contact_person'],
            'role' => 'company_admin',
            'company_name' => $companyData['company_name']
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
        'full_name' => $companyData['contact_person'],
        'email' => $companyData['contact_email'],
        'phone' => $companyData['contact_phone'],
        'role' => 'company_admin'
    ];
    $profileUpdate = callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'PATCH', $profileData);

    // Step 5: Insert into companies table
    $companyRow = [
        'company_name' => $companyData['company_name'],
        'subdomain' => $companyData['subdomain'],
        'contact_person' => $companyData['contact_person'],
        'contact_email' => $companyData['contact_email'],
        'contact_phone' => $companyData['contact_phone'],
        'address' => $companyData['address'],
        'status' => $companyData['status'],
        'created_at' => $companyData['created_at'],
        'manager_id' => $userId,
        'commission' => $commission
    ];
    $companyResult = callSupabaseWithServiceKey('companies', 'POST', $companyRow);

    // Check insert success
    $companyId = null;
    if (is_array($companyResult)) {
        if (isset($companyResult[0]['id'])) {
            $companyId = $companyResult[0]['id'];
        } elseif (isset($companyResult['id'])) {
            $companyId = $companyResult['id'];
        }
    }
    if (!$companyId) {
        // rollback on failure
        callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'DELETE');
        callSupabaseWithServiceKey("auth/v1/admin/users/{$userId}", 'DELETE');
        throw new Exception('Failed to create company record: ' . json_encode($companyResult));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Company created successfully',
        'data' => [
            'company_id' => $companyId,
            'user_id' => $userId
        ]
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'add_company.php', 400);
}
