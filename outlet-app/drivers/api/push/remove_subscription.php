<?php


session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/supabase-helper.php';

try {
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['endpoint'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing endpoint parameter'
        ]);
        exit;
    }
    
    $endpoint = $input['endpoint'];
    
    $supabase = new SupabaseHelper();
    

    
    $result = $supabase->patch(
        'push_subscriptions',
        ['is_active' => false],
        "endpoint=eq." . urlencode($endpoint)
    );
    
    error_log("✅ Removed push subscription for endpoint: " . substr($endpoint, 0, 50) . "...");
    
    echo json_encode([
        'success' => true,
        'message' => 'Subscription removed successfully'
    ]);
    
} catch (Exception $e) {
    error_log("❌ Remove subscription error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to remove subscription',
        'message' => $e->getMessage()
    ]);
}
