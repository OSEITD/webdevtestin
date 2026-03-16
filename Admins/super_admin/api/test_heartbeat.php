<?php
/**
 * Debug script to test presence_heartbeat manually
 * Run this in browser: http://localhost/WDParcelSendReceiverPWA/Admins/super_admin/api/test_heartbeat.php
 */

require_once __DIR__ . '/supabase-client.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check session
echo json_encode([
    'session_check' => [
        'has_user_id' => isset($_SESSION['user_id']),
        'user_id' => $_SESSION['user_id'] ?? null,
        'has_logged_in' => isset($_SESSION['logged_in']),
        'logged_in' => $_SESSION['logged_in'] ?? null,
        'session_data' => $_SESSION
    ],
    'php_version' => phpversion()
], JSON_PRETTY_PRINT);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "\n\nERROR: User not logged in";
    exit;
}

// Test the update directly
echo "\n\nTesting direct profile update...\n";

$userId = $_SESSION['user_id'];
$currentTime = date('c'); // ISO 8601 format

echo "User ID: {$userId}\n";
echo "Current Time: {$currentTime}\n";

// Test update
$testUpdate = callSupabaseWithServiceKey(
    "profiles?id=eq.{$userId}",
    'PATCH',
    ['last_seen_at' => $currentTime]
);

echo "\nUpdate Response:\n";
echo json_encode($testUpdate, JSON_PRETTY_PRINT);

// Verify the update by fetching the record
echo "\n\nFetching profile to verify update...\n";
$profile = callSupabaseWithServiceKey(
    "profiles?id=eq.{$userId}&select=id,email,last_seen_at",
    'GET'
);

echo json_encode($profile, JSON_PRETTY_PRINT);
?>
