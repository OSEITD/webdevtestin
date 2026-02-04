<?php
require_once 'supabase-client.php';

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
    $requiredFields = ['full_name', 'email', 'password', 'role', 'status'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Hash the password before storing
    $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Add debugging log
    error_log('Attempting to insert user with data: ' . json_encode($data));

    // Determine table and additional fields based on role
    $role = $data['role'];
    $userData = [
        'full_name' => $data['full_name'],
        'email' => $data['email'],
        'password_hash' => $data['password'], // Already hashed above
        'status' => $data['status'],
        'created_at' => date('Y-m-d H:i:s')
    ];

    switch ($role) {
        case 'Admin':
            $table = 'admins';
            $userData['role'] = 'Admin';
            break;
        case 'Company Manager':
            $table = 'company_managers';
            $userData['role'] = 'Company Manager';
            $userData['company_id'] = $data['associated_entity'] ?? null;
            break;
        case 'Outlet Manager':
            $table = 'outlet_managers';
            $userData['role'] = 'Outlet Manager';
            $userData['outlet_id'] = $data['associated_entity'] ?? null;
            break;
        case 'Driver':
            $table = 'drivers';
            $userData['role'] = 'Driver';
            $userData['company_id'] = $data['associated_entity'] ?? null;
            break;
        case 'Customer':
            $table = 'customers';
            $userData['role'] = 'Customer';
            break;
        default:
            throw new Exception('Invalid role selected');
    }

    // Save to the correct table
    $result = callSupabase($table, 'POST', $userData);

    if (!$result) {
        throw new Exception('Failed to add user to database');
    }

    echo json_encode([
        'success' => true,
        'message' => 'User added successfully',
        'user' => $result
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
