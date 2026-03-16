<?php
session_start();
require_once __DIR__ . '/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'user_id is required']);
    exit;
}

try {
    // Fetch user profile with suspension details
    $profiles = callSupabaseWithServiceKey(
        'profiles?id=eq.' . urlencode($userId) . '&select=id,suspended_at,suspended_by,suspension_reason',
        'GET'
    );

    if (empty($profiles)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $user = $profiles[0];

    // If suspended, fetch the admin who suspended them
    $suspendedBy = null;
    $suspendedByName = null;
    $suspendedByEmail = null;

    if (!empty($user['suspended_by'])) {
        $suspendedBy = $user['suspended_by'];
        
        // Fetch the admin's profile
        $adminProfiles = callSupabaseWithServiceKey(
            'profiles?id=eq.' . urlencode($suspendedBy) . '&select=id,full_name,email',
            'GET'
        );

        if (!empty($adminProfiles)) {
            $admin = $adminProfiles[0];
            $suspendedByName = $admin['full_name'] ?? null;
            $suspendedByEmail = $admin['email'] ?? null;
        }
    }

    echo json_encode([
        'success' => true,
        'suspension' => [
            'suspended_at' => $user['suspended_at'] ?? null,
            'suspended_by' => $suspendedBy,
            'suspended_by_name' => $suspendedByName,
            'suspended_by_email' => $suspendedByEmail,
            'suspension_reason' => $user['suspension_reason'] ?? null
        ]
    ]);

} catch (Exception $e) {
    error_log('Error fetching suspension details: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch suspension details']);
}
?>