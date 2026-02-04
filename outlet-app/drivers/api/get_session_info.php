<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
try {
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        echo json_encode([
            'success' => true,
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'],
            'company_id' => $_SESSION['company_id'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'email' => $_SESSION['email'] ?? null
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No active session'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get session info'
    ]);
}
