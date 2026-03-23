<?php
/**
 * Fetch Online Users API
 * 
 * Returns a map of user IDs who have been seen in the last 10 minutes.
 * Used by the frontend to dispatch the presence_sync event.
 */

require_once __DIR__ . '/supabase-client.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // Verify user is authenticated
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Calculate timestamp for 10 minutes ago
    // Use ISO 8601 format that Supabase expects
    $tenMinutesAgo = new DateTime();
    $tenMinutesAgo->modify('-10 minutes');
    $timestampData = $tenMinutesAgo->format('c');

    $endpoint = "profiles?select=id,last_seen_at&last_seen_at=gte." . urlencode($timestampData);

    // If super admin, use service key to see ALL users across all companies
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
        $recentUsers = callSupabaseWithServiceKey($endpoint, 'GET');
    } else {
        // If regular admin/manager, they can only see their own company (handled by RLS if we use anon key)
        $recentUsers = callSupabase($endpoint);
    }

    $onlineMap = [];
    if (is_array($recentUsers)) {
        foreach ($recentUsers as $user) {
            if (isset($user['id'])) {
                $onlineMap[$user['id']] = true;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $onlineMap
    ]);

} catch (Exception $e) {
    error_log('Error in fetch_online_users.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
