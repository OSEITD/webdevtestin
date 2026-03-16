<?php
/**
 * Unsuspend User API
 * 
 * Reactivates a suspended user account:
 * 1. Sets status to 'Active'
 * 2. Clears suspension fields
 * 3. Logs audit trail
 * 4. Sends reactivation email
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
    $adminId = $_SESSION['user_id'];
    $unsuspensionReason = trim($data['unsuspension_reason'] ?? 'Account reactivated by administrator');

    if (empty($userId)) {
        throw new Exception('Missing user_id parameter');
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

    // Check if actually suspended
    if ($user['status'] !== 'Suspended') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'User is not suspended'
        ]);
        exit;
    }

    $currentTime = date('c');

    // Step 2: Update user status back to Active
    $unsuspensionUpdate = callSupabaseWithServiceKey(
        "profiles?id=eq.{$userId}",
        'PATCH',
        [
            'status' => 'Active',
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
            'updated_at' => $currentTime
        ]
    );

    error_log("Unsuspended user {$userId} by admin {$adminId}");

    // Step 3: Log to audit trail
    $auditLog = callSupabaseWithServiceKey(
        'suspension_audit_log',
        'POST',
        [
            'user_id' => $userId,
            'suspended_by_id' => $adminId,
            'action' => 'unsuspended',
            'reason' => $unsuspensionReason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ]
    );

    error_log("Audit log created for user unsuspension: {$userId}");

    // Step 4: Send reactivation email
    try {
        $adminProfile = callSupabaseWithServiceKey(
            "profiles?id=eq.{$adminId}&select=full_name,email",
            'GET'
        );
        $admin = is_array($adminProfile) ? $adminProfile[0] : $adminProfile;
        $adminName = $admin['full_name'] ?? 'Administrator';

        $userEmail = $user['email'];
        $userName = $user['full_name'];

        $emailSubject = 'Your Account Has Been Reactivated';
        $emailBody = "
Hello {$userName},

We're pleased to inform you that your account has been reactivated and is now active.

Date: " . date('Y-m-d H:i:s') . "
Reactivated by: {$adminName}

You can now log in and access your account.

Regards,
Support Team
        ";

        $headers = [
            'From: noreply@parcelsendreceiver.com',
            'Content-Type: text/plain; charset=UTF-8'
        ];

        @mail($userEmail, $emailSubject, $emailBody, implode("\r\n", $headers));
        error_log("Reactivation email sent to {$userEmail}");
    } catch (Exception $e) {
        error_log("Warning: Failed to send reactivation email: " . $e->getMessage());
        // Don't fail the unsuspension if email fails
    }

    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'User account reactivated successfully',
        'data' => [
            'user_id' => $userId,
            'email' => $user['email'],
            'status' => 'Active',
            'reactivated_at' => $currentTime,
            'reactivated_by' => $adminId
        ]
    ]);

} catch (Exception $e) {
    error_log('Error in unsuspend_user.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
