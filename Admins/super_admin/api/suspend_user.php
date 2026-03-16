<?php
/**
 * Suspend User API
 * 
 * Suspends a user account immediately:
 * 1. Sets status to 'Suspended'
 * 2. Records suspended_at and suspended_by
 * 3. Kills all active sessions
 * 4. Logs audit trail
 * 5. Sends suspension email
 */

require_once __DIR__ . '/supabase-client.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // Verify admin is authenticated
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized - Super Admin access required'
        ]);
        exit;
    }

    $requestMethod = $_SERVER['REQUEST_METHOD'];
    if ($requestMethod !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid request data');
    }

    $userId = $data['user_id'] ?? null;
    $suspensionReason = trim($data['suspension_reason'] ?? '');
    $adminId = $_SESSION['user_id'];

    if (empty($userId)) {
        throw new Exception('Missing user_id parameter');
    }

    if (empty($suspensionReason)) {
        throw new Exception('Suspension reason is required');
    }

    // Prevent self-suspension
    if ($userId === $adminId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'You cannot suspend your own account'
        ]);
        exit;
    }

    // Step 1: Get user details
    $userProfile = callSupabaseWithServiceKey(
        "profiles?id=eq.{$userId}&select=id,email,full_name,status",
        'GET'
    );

    if (empty($userProfile)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'User not found'
        ]);
        exit;
    }

    $user = is_array($userProfile) ? $userProfile[0] : $userProfile;

    // Check if already suspended
    if ($user['status'] === 'Suspended') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'User is already suspended'
        ]);
        exit;
    }

    $currentTime = date('c');

    // Step 2: Update user status to Suspended
    $suspensionUpdate = callSupabaseWithServiceKey(
        "profiles?id=eq.{$userId}",
        'PATCH',
        [
            'status' => 'Suspended',
            'suspended_at' => $currentTime,
            'suspended_by' => $adminId,
            'suspension_reason' => $suspensionReason,
            'updated_at' => $currentTime
        ]
    );

    error_log("Suspended user {$userId} by admin {$adminId}");

    // Step 3: Kill all active sessions for this user
    $activeSessions = callSupabaseWithServiceKey(
        "user_sessions?user_id=eq.{$userId}&logout_time=is.null",
        'GET'
    );

    if (!empty($activeSessions)) {
        $sessions = is_array($activeSessions) ? $activeSessions : [$activeSessions];
        
        foreach ($sessions as $session) {
            $sessionId = $session['id'] ?? null;
            if ($sessionId) {
                callSupabaseWithServiceKey(
                    "user_sessions?id=eq.{$sessionId}",
                    'PATCH',
                    ['logout_time' => $currentTime]
                );
                error_log("Killed session {$sessionId} for suspended user {$userId}");
            }
        }
    }

    // Step 4: Log to audit trail
    $auditLog = callSupabaseWithServiceKey(
        'suspension_audit_log',
        'POST',
        [
            'user_id' => $userId,
            'suspended_by_id' => $adminId,
            'action' => 'suspended',
            'reason' => "Account suspended by admin",
            'suspension_reason' => $suspensionReason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ]
    );

    error_log("Audit log created for user suspension: {$userId}");

    // Step 5: Send suspension email
    try {
        $adminProfile = callSupabaseWithServiceKey(
            "profiles?id=eq.{$adminId}&select=full_name,email",
            'GET'
        );
        $admin = is_array($adminProfile) ? $adminProfile[0] : $adminProfile;
        $adminName = $admin['full_name'] ?? 'Administrator';

        $userEmail = $user['email'];
        $userName = $user['full_name'];

        $emailSubject = 'Your Account Has Been Suspended';
        $emailBody = "
Hello {$userName},

Your account has been suspended effective immediately.

Reason: {$suspensionReason}

Suspended by: {$adminName}
Date: " . date('Y-m-d H:i:s') . "

If you believe this is a mistake, please contact support.

Regards,
Support Team
        ";

        $headers = [
            'From: noreply@parcelsendreceiver.com',
            'Content-Type: text/plain; charset=UTF-8'
        ];

        @mail($userEmail, $emailSubject, $emailBody, implode("\r\n", $headers));
        error_log("Suspension email sent to {$userEmail}");
    } catch (Exception $e) {
        error_log("Warning: Failed to send suspension email: " . $e->getMessage());
        // Don't fail the suspension if email fails
    }

    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'User account suspended successfully',
        'data' => [
            'user_id' => $userId,
            'email' => $user['email'],
            'suspended_at' => $currentTime,
            'suspended_by' => $adminId,
            'sessions_killed' => count($sessions ?? [])
        ]
    ]);

} catch (Exception $e) {
    error_log('Error in suspend_user.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
