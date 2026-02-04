<?php
header('Content-Type: application/json');
require_once 'supabase-client.php';

try {
    error_log("Received POST data: " . print_r($_POST, true));

    // Step 1: Prepare driver data (no id yet)
    $driverData = [
        'company_id' => $_POST['company_id'] ?? '',
        'driver_name' => $_POST['driver_name'] ?? '',
        'driver_email' => $_POST['driver_email'] ?? '',
        'driver_phone' => $_POST['driver_phone'] ?? '',
        'status' => 'available',
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Validate required fields
    $requiredFields = ['company_id', 'driver_name', 'driver_email', 'driver_phone', 'password'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Required fields missing: ' . implode(', ', $missingFields)
        ]);
        exit;
    }

    if (!filter_var($driverData['driver_email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit;
    }

    // Handle license image upload
    if (isset($_FILES['license_image']) && $_FILES['license_image']['error'] === UPLOAD_ERR_OK) {
        $license = $_FILES['license_image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($license['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
        }
        $extension = pathinfo($license['name'], PATHINFO_EXTENSION);
        $filename = uniqid('license_', true) . '.' . $extension;
        $uploadPath = '../assets/uploads/driver_licenses/' . $filename;
        if (!file_exists(dirname($uploadPath))) {
            mkdir(dirname($uploadPath), 0777, true);
        }
        if (move_uploaded_file($license['tmp_name'], $uploadPath)) {
            $driverData['license_image'] = $filename;
        } else {
            throw new Exception('Failed to upload license image');
        }
    }

    // Step 2: Create Supabase Auth user
    $userData = [
        'email' => $driverData['driver_email'],
        'password' => $_POST['password'],
        'user_metadata' => [
            'name' => $driverData['driver_name'],
            'role' => 'driver',
            'company_id' => $driverData['company_id']
        ],
        'email_confirm' => true
    ];
    $profileResult = callSupabaseWithServiceKey('auth/v1/admin/users', 'POST', $userData);
    error_log("User creation result: " . json_encode($profileResult));

    if (!$profileResult || !isset($profileResult['id'])) {
        if (isset($profileResult['message']) && strpos($profileResult['message'], 'already registered') !== false) {
            throw new Exception('A user with this email address already exists.');
        }
        throw new Exception('Failed to create user profile: ' . json_encode($profileResult));
    }
    $userId = $profileResult['id'];

    // Step 3: UPDATE the existing profile that was auto-created by the Supabase trigger.
    $profileData = [
        'username' => $userData['email'],
        'full_name' => $userData['user_metadata']['name'],
    ];
    $profileUpdate = callSupabaseWithServiceKey("profiles?id=eq.{$userId}", 'PATCH', $profileData);
    error_log("Profile update result: " . print_r($profileUpdate, true));

    // Step 4: Insert driver row
    $driverData['id'] = $userId;
    $driverResult = callSupabaseWithServiceKey('drivers', 'POST', $driverData);
    error_log("Driver insert result: " . print_r($driverResult, true));

    // *** CORRECTED LOGIC BELOW ***
    // More robust check for the returned driver ID
    $driverId = null;
    if (is_array($driverResult)) {
        if (isset($driverResult[0]['id'])) {
            // Handle response format: [{ "id": "..." }]
            $driverId = $driverResult[0]['id'];
        } elseif (isset($driverResult['id'])) {
            // Handle response format: { "id": "..." }
            $driverId = $driverResult['id'];
        }
    }
    // *** END OF CORRECTED LOGIC ***
    
    if (!$driverId) {
        callSupabaseWithServiceKey("auth/v1/admin/users/{$userId}", 'DELETE');
        throw new Exception('Failed to create driver record: ' . json_encode($driverResult));
    }

    // Success
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Driver account created successfully',
        'data' => [
            'profile_id' => $userId,
            'driver_id'  => $driverId
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>