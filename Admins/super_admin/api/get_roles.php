<?php
require_once __DIR__ . '/supabase-client.php';

session_start();

header('Content-Type: application/json');

try {
    // Fetch all roles from the roles table
    $res = callSupabaseWithServiceKey('roles?select=id,name,description&order=name.asc', 'GET');
    
    if (!is_array($res)) {
        $res = [];
    }
    
    // Format response: extract only name and description
    $roles = [];
    foreach ($res as $role) {
        $roles[] = [
            'name' => $role['name'] ?? '',
            'description' => $role['description'] ?? ''
        ];
    }
    
    echo json_encode($roles);
} catch (Exception $e) {
    error_log('Error fetching roles: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
