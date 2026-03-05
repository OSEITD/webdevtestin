<?php


header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/session_manager.php';
initSession();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit;
}

require_once __DIR__ . '/../../includes/supabase-helper.php';

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
    $userId = $_SESSION['user_id'];
    
    $supabase = new SupabaseHelper();
    
    $subscriptions = $supabase->get(
        'push_subscriptions',
        "endpoint=eq." . urlencode($endpoint) . "&user_id=eq.$userId&select=id,is_active,created_at,updated_at"
    );
    
    if (empty($subscriptions)) {
        echo json_encode([
            'success' => true,
            'active' => false,
            'reason' => 'Subscription not found in database'
        ]);
        exit;
    }
    
    $subscription = $subscriptions[0];
    
    if (!$subscription['is_active']) {
        echo json_encode([
            'success' => true,
            'active' => false,
            'reason' => 'Subscription marked as inactive',
            'subscription_id' => $subscription['id']
        ]);
        exit;
    }
    
    $createdAt = strtotime($subscription['created_at']);
    $daysSinceCreated = (time() - $createdAt) / (60 * 60 * 24);
    
    echo json_encode([
        'success' => true,
        'active' => true,
        'subscription_id' => $subscription['id'],
        'days_old' => round($daysSinceCreated),
        'should_renew' => $daysSinceCreated > 90
    ]);
    
} catch (Exception $e) {
    error_log("Subscription verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to verify subscription',
        'message' => $e->getMessage()
    ]);
}
