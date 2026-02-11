<?php
session_start();
require_once 'supabase-client.php';

// Set headers
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    error_log("Unauthorized access attempt to fetch_users.php. Session role: " . ($_SESSION['role'] ?? 'none'));
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

try {
    error_log("Fetching users data from Supabase...");
    
    // Get users with their active status
    $users = callSupabaseWithServiceKey('all_users', 'GET', [
        'select' => 'id,status'
    ]);
    
    if (!is_array($users)) {
        error_log("Invalid response from Supabase: " . print_r($users, true));
        throw new Exception('Failed to fetch users data');
    }
    
    $activeUsers = array_filter($users, function($user) {
        return isset($user['status']) && $user['status'] === 'active';
    });
    
    echo json_encode([
        'status' => 'success',
        'total_users' => count($users),
        'active_users' => count($activeUsers)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
