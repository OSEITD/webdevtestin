<?php
session_start();
require_once '../../includes/OutletAwareSupabaseHelper.php';
header('Content-Type: application/json');
try {
    $supabase = new OutletAwareSupabaseHelper();
    
    echo json_encode([
        'current_session' => [
            'user_id' => $_SESSION['user_id'] ?? 'NOT_SET',
            'role' => $_SESSION['role'] ?? 'NOT_SET',
            'company_id' => $_SESSION['company_id'] ?? 'NOT_SET',
            'outlet_id' => $_SESSION['outlet_id'] ?? 'NOT_SET'
        ],
        'expected_driver' => [
            'user_id' => '4b94ab87-a51b-4369-9cbb-5763d4f44fa3',
            'role' => 'driver'
        ],
        'need_to_do' => 'Log out and log in as the correct driver',
        'logout_url' => 'http://acme.localhost/drivers/logout.php',
        'login_url' => 'http://acme.localhost/drivers/login.php'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
