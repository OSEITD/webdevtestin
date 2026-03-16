<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('add_company.php');
ErrorHandler::requireMethod('POST', 'add_company.php');

require_once 'supabase-client.php';

try {
    // Check if we're receiving JSON (support both CONTENT_TYPE and HTTP_CONTENT_TYPE for reverse proxies like ngrok)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
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
        'city' => trim($_POST['city'] ?? ''),
        'state' => trim($_POST['state'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'status' => trim($_POST['status'] ?? 'active'),
        'created_at' => date('Y-m-d H:i:s')
    ];
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $commissionRate = floatval($_POST['commission_rate'] ?? 0);

    // Step 2: Validate all fields with detailed error messages
    $validationErrors = [];

    // Company name: required, 2-100 chars
    if (empty($companyData['company_name'])) {
        $validationErrors['company_name'] = 'Company Name is required';
    } elseif (strlen($companyData['company_name']) < 2 || strlen($companyData['company_name']) > 100) {
        $validationErrors['company_name'] = 'Company Name must be 2-100 characters';
    }

    // Subdomain: required, 3-50 chars, lowercase alphanumeric
    if (empty($companyData['subdomain'])) {
        $validationErrors['subdomain'] = 'Subdomain is required';
    } elseif (strlen($companyData['subdomain']) < 3 || strlen($companyData['subdomain']) > 50) {
        $validationErrors['subdomain'] = 'Subdomain must be 3-50 characters';
    } elseif (!preg_match('/^[a-z0-9]+$/', $companyData['subdomain'])) {
        $validationErrors['subdomain'] = 'Subdomain must contain only lowercase letters and numbers';
    }

    // Contact person: required, 2-100 chars
    if (empty($companyData['contact_person'])) {
        $validationErrors['contact_person'] = 'Contact Person is required';
    } elseif (strlen($companyData['contact_person']) < 2 || strlen($companyData['contact_person']) > 100) {
        $validationErrors['contact_person'] = 'Contact Person must be 2-100 characters';
    }

    // Email: required, valid format
    if (empty($companyData['contact_email'])) {
        $validationErrors['contact_email'] = 'Contact Email is required';
    } elseif (!filter_var($companyData['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $validationErrors['contact_email'] = 'Please enter a valid email address';
    }

    // Phone: required, international format +X followed by 7-15 digits
    $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $companyData['contact_phone']);
    if (empty($companyData['contact_phone'])) {
        $validationErrors['contact_phone'] = 'Contact Phone is required';
    } elseif (!preg_match('/^\+\d{7,15}$/', $cleanPhone)) {
        $validationErrors['contact_phone'] = 'Please enter a valid phone number with country code (e.g., +260 XXX XXX XXX)';
    } else {
        // Store the cleaned phone number
        $companyData['contact_phone'] = $cleanPhone;
    }

    // Address: required, 5-500 chars
    if (empty($companyData['address'])) {
        $validationErrors['address'] = 'Address is required';
    } elseif (strlen($companyData['address']) < 5 || strlen($companyData['address']) > 500) {
        $validationErrors['address'] = 'Address must be 5-500 characters';
    }

    // Password: required, 8-16 chars
    if (empty($password)) {
        $validationErrors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $validationErrors['password'] = 'Password must be at least 8 characters';
    } elseif (strlen($password) > 16) {
        $validationErrors['password'] = 'Password must be at most 16 characters';
    }

    // Confirm password: must match
    if (empty($confirmPassword)) {
        $validationErrors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirmPassword) {
        $validationErrors['confirm_password'] = 'Passwords do not match';
    }

    // Commission rate: 0-100
    if ($commissionRate < 0 || $commissionRate > 100) {
        $validationErrors['commission_rate'] = 'Commission rate must be between 0 and 100';
    }

    // If any validation errors, return them all at once
    if (!empty($validationErrors)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'Validation failed',
            'errors' => $validationErrors
        ]);
        exit;
    }

    // Step 3: Check for duplicate subdomain
    $existingSubdomain = callSupabaseWithServiceKey('companies?subdomain=eq.' . urlencode($companyData['subdomain']) . '&select=id', 'GET');
    if (!empty($existingSubdomain)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'This subdomain is already taken',
            'errors' => ['subdomain' => 'This subdomain is already taken. Please choose a different one.']
        ]);
        exit;
    }

    // Step 4: Check for duplicate company name
    $existingName = callSupabaseWithServiceKey('companies?company_name=eq.' . urlencode($companyData['company_name']) . '&select=id', 'GET');
    if (!empty($existingName)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'A company with this name already exists',
            'errors' => ['company_name' => 'A company with this name already exists.']
        ]);
        exit;
    }

    // Step 4b: Check for duplicate contact phone
    $existingPhone = callSupabaseWithServiceKey('companies?contact_phone=eq.' . urlencode($companyData['contact_phone']) . '&select=id', 'GET');
    if (!empty($existingPhone)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'This phone number is already registered to another company',
            'errors' => ['contact_phone' => 'This phone number is already registered to another company.']
        ]);
        exit;
    }

    // Step 5: Check if email already exists in auth system
    try {
        // Query profiles table which mirrors auth users
        $existingEmail = callSupabaseWithServiceKey('profiles?email=eq.' . urlencode($companyData['contact_email']) . '&select=id', 'GET');
        if (!empty($existingEmail)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'A user with this email already exists',
                'errors' => ['contact_email' => 'This email is already registered. Please use a different email.']
            ]);
            exit;
        }
    } catch (Exception $e) {
        // If the check fails, log and continue — the auth create will catch it
        error_log("add_company: Email pre-check failed: " . $e->getMessage());
    }

    // Step 6: Create Supabase Auth user for company admin
    $userId = null;
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
    try {
        $authResult = callSupabaseWithServiceKey('auth/v1/admin/users', 'POST', $userData);
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'email_exists') !== false || strpos($msg, 'already been registered') !== false || strpos($msg, 'already exists') !== false) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'A user with this email already exists',
                'errors' => ['contact_email' => 'This email is already registered. Please use a different email.']
            ]);
            exit;
        }
        throw $e;
    }

    if (!$authResult || !isset($authResult['id'])) {
        throw new Exception('Failed to create auth user: ' . json_encode($authResult));
    }
    $userId = $authResult['id'];
    error_log("add_company: Auth user created with ID: {$userId}");

    // Step 4: Update profile (non-fatal - profile may not exist yet via trigger)
    try {
        $profileData = [
            'full_name' => $companyData['contact_person'],
            'email' => $companyData['contact_email'],
            'phone' => $companyData['contact_phone'],
            'role' => 'company_admin'
        ];
        callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'PATCH', $profileData);
        error_log("add_company: Profile updated for user {$userId}");
    } catch (Exception $e) {
        // Profile update is non-fatal - the profile trigger may not have run yet
        error_log("add_company: Profile update failed (non-fatal): " . $e->getMessage());
    }

    // Step 5: Insert into companies table
    $companyRow = [
        'company_name' => $companyData['company_name'],
        'subdomain' => $companyData['subdomain'],
        'contact_person' => $companyData['contact_person'],
        'contact_email' => $companyData['contact_email'],
        'contact_phone' => $companyData['contact_phone'],
        'address' => $companyData['address'],
        'city' => $companyData['city'],
        'state' => $companyData['state'],
        'postal_code' => $companyData['postal_code'],
        'country' => $companyData['country'],
        'status' => $companyData['status'],
        'created_at' => $companyData['created_at'],
        'manager_id' => $userId,
        'revenue' => floatval($companyData['revenue'] ?? 0),
        'commission_rate' => $commissionRate
    ];
    
    try {
        $companyResult = callSupabaseWithServiceKey('companies', 'POST', $companyRow);
    } catch (Exception $e) {
        error_log("add_company: Company insert failed: " . $e->getMessage());
        // Rollback auth user on company insert failure
        try {
            callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'DELETE');
            callSupabaseWithServiceKey("auth/v1/admin/users/{$userId}", 'DELETE');
            error_log("add_company: Rolled back auth user {$userId}");
        } catch (Exception $rollbackErr) {
            error_log("add_company: Rollback failed: " . $rollbackErr->getMessage());
        }
        throw $e;
    }

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
        // Rollback on failure
        try {
            callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'DELETE');
            callSupabaseWithServiceKey("auth/v1/admin/users/{$userId}", 'DELETE');
            error_log("add_company: Rolled back auth user {$userId} (no company ID)");
        } catch (Exception $rollbackErr) {
            error_log("add_company: Rollback failed: " . $rollbackErr->getMessage());
        }
        throw new Exception('Failed to create company record: ' . json_encode($companyResult));
    }

    error_log("add_company: Company created with ID: {$companyId}");
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
