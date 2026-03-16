<?php
require_once __DIR__ . '/supabase-client.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('Invalid request data');
    }

    // Required fields validation
    $requiredFields = ['full_name', 'email', 'password', 'role'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid email format',
            'errors' => ['email' => 'Please enter a valid email address.']
        ]);
        exit;
    }

    // Validate passwords match
    if (isset($data['confirm_password']) && $data['password'] !== $data['confirm_password']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Passwords do not match',
            'errors' => ['confirm_password' => 'Passwords do not match.']
        ]);
        exit;
    }

    $role = $data['role'];
    $email = trim($data['email']);
    $fullName = trim($data['full_name']);
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $city = trim($data['city'] ?? '');
    $state = trim($data['state'] ?? '');
    $postalCode = trim($data['postal_code'] ?? '');
    $country = trim($data['country'] ?? '');
    $companyId = $data['company_id'] ?? null;
    $outletId = $data['outlet_id'] ?? null;
    $status = $data['status'] ?? 'active';

    // Step 1: Check for duplicate email in profiles
    $existingEmail = callSupabaseWithServiceKey('profiles?email=eq.' . urlencode($email) . '&select=id', 'GET');
    if (!empty($existingEmail)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'A user with this email already exists',
            'errors' => ['email' => 'This email is already registered. Please use a different email.']
        ]);
        exit;
    }

    // Step 1b: Check for duplicate phone if provided
    if (!empty($phone)) {
        $existingPhone = callSupabaseWithServiceKey('profiles?phone=eq.' . urlencode($phone) . '&select=id', 'GET');
        if (!empty($existingPhone)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'A user with this phone number already exists',
                'errors' => ['phone' => 'This phone number is already registered. Please use a different phone number.']
            ]);
            exit;
        }
    }

    // Step 2: Create Supabase Auth user
    $userData = [
        'email'    => $email,
        'password' => $data['password'],
        'user_metadata' => [
            'name'  => $fullName,
            'role'  => $role
        ],
        'email_confirm' => true
    ];

    $authResult = callSupabaseWithServiceKey('auth/v1/admin/users', 'POST', $userData);
    error_log("Auth user creation result: " . json_encode($authResult));

    if (!$authResult || !isset($authResult['id'])) {
        // Check for email-already-exists error from auth
        if (isset($authResult['message']) && (strpos($authResult['message'], 'already registered') !== false || strpos($authResult['message'], 'email_exists') !== false)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'A user with this email already exists',
                'errors' => ['email' => 'This email is already registered. Please use a different email.']
            ]);
            exit;
        }
        throw new Exception('Failed to create auth user: ' . json_encode($authResult));
    }

    $userId = $authResult['id'];

    // Step 3: Update the auto-created profile
    $profileData = [
        'full_name' => $fullName,
        'email'     => $email,
        'phone'     => $phone,
        'role'      => $role,
        'address'   => $address,
        'city'      => $city,
        'state'     => $state,
        'postal_code' => $postalCode,
        'country'   => $country,
    ];
    if ($companyId) {
        $profileData['company_id'] = $companyId;
    }
    if ($outletId) {
        $profileData['outlet_id'] = $outletId;
    }

    $profileUpdate = callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'PATCH', $profileData);
    error_log("Profile update result: " . print_r($profileUpdate, true));

    // Step 4: Insert into the role-specific table
    $roleTable = null;
    $roleData = [];
    $roleLower = strtolower($role);

    switch ($roleLower) {
        case 'super_admin':
            $roleTable = 'super_admin';
            $roleData = [
                'id'         => $userId,
                'name'       => $fullName,
                'email'      => $email,
                'address'    => $address,
                'city'       => $city,
                'state'      => $state,
                'postal_code' => $postalCode,
                'country'    => $country,
                'created_at' => date('Y-m-d H:i:s')
            ];
            break;

        case 'driver':
            $roleTable = 'drivers';
            $roleData = [
                'id'           => $userId,
                'company_id'   => $companyId,
                'driver_name'  => $fullName,
                'driver_email' => $email,
                'driver_phone' => $phone,
                'address'      => $address,
                'city'         => $city,
                'state'        => $state,
                'postal_code'  => $postalCode,
                'country'      => $country,
                'status'       => 'available',
                'created_at'   => date('Y-m-d H:i:s')
            ];
            // Add license_number if provided
            if (!empty($data['license_number'])) {
                $roleData['license_number'] = trim($data['license_number']);
            }
            break;

        case 'customer':
            $roleTable = 'customers';
            $roleData = [
                'id'         => $userId,
                'full_name'  => $fullName,
                'email'      => $email,
                'phone'      => $phone,
                'status'     => $status,
                'created_at' => date('Y-m-d H:i:s')
            ];
            break;

        case 'outlet_manager':
            // Outlet managers are stored in profiles only (with outlet_id),
            // but if you have an outlet_managers table, insert here:
            // $roleTable = 'outlet_managers';
            // $roleData = [...];
            error_log("Outlet manager user {$userId} created with outlet_id: {$outletId}");
            break;

        default:
            // For any other role, we only create the auth user + profile (no role-specific table)
            error_log("No role-specific table for role: {$role}. User {$userId} created in auth + profiles only.");
            break;
    }

    if ($roleTable) {
        $roleResult = callSupabaseWithServiceKey($roleTable, 'POST', $roleData);
        error_log("Role table ({$roleTable}) insert result: " . print_r($roleResult, true));

        // Verify insertion
        $roleRowId = null;
        if (is_array($roleResult)) {
            if (isset($roleResult[0]['id'])) {
                $roleRowId = $roleResult[0]['id'];
            } elseif (isset($roleResult['id'])) {
                $roleRowId = $roleResult['id'];
            }
        }

        if (!$roleRowId) {
            // Rollback: delete the auth user and profile on failure
            callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'DELETE');
            callSupabaseWithServiceKey("auth/v1/admin/users/{$userId}", 'DELETE');
            throw new Exception("Failed to create {$role} record: " . json_encode($roleResult));
        }
    }

    // Success
    echo json_encode([
        'success' => true,
        'message' => 'User account created successfully',
        'data'    => [
            'profile_id' => $userId,
            'role'       => $role
        ]
    ]);

} catch (Exception $e) {
    error_log('Error in add_user.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
