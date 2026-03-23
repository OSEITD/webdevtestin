<?php
/**
 * Presence Heartbeat API
 * 
 * Handles heartbeat pings from the frontend to keep user presence active.
 * Updates:
 * 1. last_seen_at in profiles table
 * 2. last_active_time in user_sessions table (for active session only)
 */

require_once __DIR__ . '/supabase-client.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // Verify user is authenticated
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized - User not logged in'
        ]);
        exit;
    }

    $userId = $_SESSION['user_id'];
    
    // Use ISO 8601 format with timezone that Supabase expects
    $currentTime = date('c'); // Returns: 2026-03-14T10:30:45+00:00

    error_log("Heartbeat received for user: {$userId} at {$currentTime}");

    // Step 1: Update last_seen_at in profiles table
    $profileUpdateData = ['last_seen_at' => $currentTime];
    
    error_log("Attempting to update profiles: " . json_encode($profileUpdateData));
    
    $profileUpdate = callSupabaseWithServiceKey(
        "profiles?id=eq.{$userId}",
        'PATCH',
        $profileUpdateData
    );

    error_log("Profile update response: " . json_encode($profileUpdate));

    // Check if update was successful (may return empty array on success)
    if ($profileUpdate === false || (is_array($profileUpdate) && isset($profileUpdate['message']) && strpos($profileUpdate['message'], 'error') !== false)) {
        error_log("Warning: profiles.last_seen_at update may have failed for user: {$userId}");
    } else {
        error_log("Updated profiles.last_seen_at for user: {$userId}");
    }

    // Step 2: Find active session (logout_time IS NULL) and update last_active_time
    $activeSessions = callSupabaseWithServiceKey(
        "user_sessions?user_id=eq.{$userId}&logout_time=is.null&order=login_time.desc&limit=1",
        'GET'
    );

    error_log("Active sessions response: " . json_encode($activeSessions));

    if (!empty($activeSessions)) {
        // Get the session ID
        $activeSession = is_array($activeSessions) ? $activeSessions[0] : $activeSessions;
        $sessionId = $activeSession['id'] ?? null;

        error_log("Found active session: {$sessionId}");

        if ($sessionId) {
            $sessionUpdateData = ['last_active_time' => $currentTime];
            
            // Update last_active_time for this session
            $sessionUpdate = callSupabaseWithServiceKey(
                "user_sessions?id=eq.{$sessionId}",
                'PATCH',
                $sessionUpdateData
            );

            error_log("Session update response: " . json_encode($sessionUpdate));

            if ($sessionUpdate) {
                error_log("Updated user_sessions.last_active_time for session: {$sessionId}");
            } else {
                error_log("Warning: Failed to update user_sessions.last_active_time for session: {$sessionId}");
            }
        }
    } else {
        error_log("No active session found for user: {$userId}");
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Heartbeat recorded successfully',
        'timestamp' => $currentTime,
        'user_id' => $userId
    ]);

} catch (Exception $e) {
    error_log('Error in presence_heartbeat.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
