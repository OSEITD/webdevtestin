<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../supabase-client.php';

$userId = $_SESSION['user_id'];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['subscription'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing subscription data']);
        exit;
    }
    
    $subscription = $data['subscription'];
    $endpoint = $subscription['endpoint'] ?? '';
    $p256dh = $subscription['keys']['p256dh'] ?? '';
    $auth = $subscription['keys']['auth'] ?? '';
    
    if (!$endpoint || !$p256dh || !$auth) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Incomplete subscription data']);
        exit;
    }
    
    // Check if subscription already exists
    $existing = callSupabaseWithServiceKey('push_subscriptions', 'GET', array(
        'select' => 'id',
        'filters' => array('endpoint' => $endpoint)
    ));
    
    if (!empty($existing)) {
        // Update existing subscription - use SupabaseClient directly for proper PATCH
        global $supabaseUrl, $supabaseServiceKey;
        $client = new SupabaseClient($supabaseUrl, $supabaseServiceKey);
        $result = $client->update('push_subscriptions', array(
            'is_active' => true,
            'p256dh_key' => $p256dh,
            'auth_key' => $auth,
            'subscription_json' => json_encode($subscription),
            'updated_at' => date('c')
        ), 'endpoint=eq.' . urlencode($endpoint));
        
        error_log("[Admin Push] Updated existing subscription for user: $userId");
    } else {
        // Create new subscription
        $result = callSupabaseWithServiceKey('push_subscriptions', 'POST', array(
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'p256dh_key' => $p256dh,
            'auth_key' => $auth,
            'subscription_json' => json_encode($subscription),
            'is_active' => true,
            'created_at' => date('c'),
            'updated_at' => date('c')
        ));
        
        error_log("[Admin Push] Created new subscription for user: $userId");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Subscription saved successfully',
        'endpoint' => substr($endpoint, 0, 50) . '...'
    ]);
    
} catch (Exception $e) {
    error_log("Error saving push subscription: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
