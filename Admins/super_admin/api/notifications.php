<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication and role
ErrorHandler::requireAuth('notifications.php');

if ($_SESSION['role'] !== 'super_admin') {
    ErrorHandler::logError("Unauthorized access attempt (wrong role: {$_SESSION['role']})", 'notifications.php');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have permission to access this resource.']);
    exit;
}

require_once 'supabase-client.php';

$action = $_GET['action'] ?? null;
$notificationId = $_GET['id'] ?? null;

try {
    if ($action === 'mark_read' && $notificationId) {
        $data = ['is_read' => true];
        $response = callSupabaseWithServiceKey("notifications?id=eq.{$notificationId}", 'PATCH', $data);

        if (isset($response['error'])) {
            throw new Exception($response['error']['message']);
        }

        echo json_encode(['success' => true]);
    } else {
        ErrorHandler::logError("Invalid action or missing ID: action=$action, id=$notificationId", 'notifications.php');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request. Please check your input and try again.']);
    }
} catch (Exception $e) {
    ErrorHandler::handleException($e, 'notifications.php');
}
