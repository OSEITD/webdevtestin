<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/session_manager.php';

try {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated',
            'redirect' => 'login.php'
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'company_name' => $_SESSION['company_name'] ?? '',
            'outlet_id' => $_SESSION['outlet_id'] ?? null
        ],
        'session_id' => session_id()
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
