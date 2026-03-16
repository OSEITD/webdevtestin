<?php
session_start();
require_once 'supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    // Fetch the count of unread notifications
    $response = callSupabaseWithServiceKey("notifications?is_read=eq.false&select=count", 'GET');

    if (isset($response['error'])) {
        throw new Exception($response['error']['message']);
    }

    // The count is returned in the Content-Range header, e.g., "0-9/100". We need to parse it.
    $count = 0;
    if (isset($response['headers']['Content-Range'])) {
        $range = $response['headers']['Content-Range'];
        $parts = explode('/', $range);
        if (count($parts) > 1) {
            $count = (int)$parts[1];
        }
    }

    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
