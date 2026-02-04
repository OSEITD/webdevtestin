<?php
header('Content-Type: application/json');
require_once 'supabase-client.php';

try {
    // Get POST data for customer
    $customerData = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'notification_preference' => $_POST['notification_preference'] ?? 'email',
        'language_preference' => $_POST['language_preference'] ?? 'en',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Get address information
    $addressData = [
        'street' => $_POST['street_address'] ?? '',
        'city' => $_POST['city'] ?? '',
        'state' => $_POST['state'] ?? '',
        'postal_code' => $_POST['postal_code'] ?? '',
        'country' => $_POST['country'] ?? '',
        'is_primary' => true,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'email', 'phone'];
    foreach ($requiredFields as $field) {
        if (empty($customerData[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Validate email format
    if (!filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Create user account in Supabase auth
    $userData = [
        'email' => $customerData['email'],
        'password' => $_POST['password'],
        'user_metadata' => [
            'full_name' => $customerData['first_name'] . ' ' . $customerData['last_name'],
            'role' => 'customer'
        ]
    ];

    $userResult = callSupabaseWithServiceKey('auth/v1/admin/users', 'POST', $userData);

    if (!$userResult || !isset($userResult['id'])) {
        throw new Exception('Failed to create user account');
    }

    $userId = $userResult['id'];

    // Create a profile for the user
    $profileData = [
        'id' => $userId,
        'full_name' => $customerData['first_name'] . ' ' . $customerData['last_name'],
        'email' => $customerData['email']
    ];
    callSupabaseWithServiceKey('profiles', 'POST', $profileData);

    // Add user_id to customer data
    $customerData['user_id'] = $userId;

    // Insert customer information
    $customerResult = callSupabaseWithServiceKey('customers', 'POST', $customerData);

    if (!$customerResult) {
        throw new Exception('Failed to create customer record');
    }

    $customerId = $customerResult['id'];

    // Add customer_id to address data
    $addressData['customer_id'] = $customerId;

    // Insert address information
    $addressResult = callSupabaseWithServiceKey('customer_addresses', 'POST', $addressData);

    if ($userResult && $customerResult && $addressResult) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customer account created successfully',
            'data' => [
                'user_id' => $userId,
                'customer_id' => $customerId
            ]
        ]);
    } else {
        throw new Exception('Failed to create customer records');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>