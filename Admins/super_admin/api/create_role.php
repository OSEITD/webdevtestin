<?php
require_once __DIR__ . '/supabase-client.php';

session_start();

header('Content-Type: application/json');

// Only super_admin can create roles
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || empty(trim($data['name'] ?? ''))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Role name is required',
            'errors' => ['name' => 'Please enter a role name.']
        ]);
        exit;
    }

    $roleName = trim($data['name']);
    $roleDescription = trim($data['description'] ?? '');

    // Check for duplicate role name (case-insensitive)
    $existing = callSupabaseWithServiceKey('roles?name=ilike.' . urlencode($roleName) . '&select=id', 'GET');
    if (!empty($existing)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'A role with this name already exists',
            'errors' => ['name' => 'This role name is already taken. Please choose a different name.']
        ]);
        exit;
    }

    // Insert the new role
    $roleData = [
        'name' => $roleName,
        'description' => $roleDescription,
        'created_at' => date('c')
    ];

    $result = callSupabaseWithServiceKey('roles', 'POST', $roleData);

    if (!$result) {
        throw new Exception('Failed to create role');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Role created successfully',
        'role' => $result
    ]);

} catch (Exception $e) {
    error_log('Error in create_role.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
