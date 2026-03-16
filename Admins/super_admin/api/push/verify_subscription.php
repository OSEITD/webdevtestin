<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'active' => false]);
    exit;
}

require_once __DIR__ . '/../supabase-client.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['endpoint'])) {
        echo json_encode(['success' => false, 'error' => 'Missing endpoint', 'active' => false]);
        exit;
    }
    
    $endpoint = $data['endpoint'];
    
    // Check if subscription exists and is active
    $subscriptions = callSupabaseWithServiceKey('push_subscriptions', 'GET', array(
        'select' => 'id,is_active',
        'filters' => array('endpoint' => $endpoint)
    ));
    
    if (!empty($subscriptions) && $subscriptions[0]['is_active']) {
        echo json_encode(['success' => true, 'active' => true]);
    } else {
        echo json_encode(['success' => true, 'active' => false]);
    }
    
} catch (Exception $e) {
    error_log("Error verifying subscription: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'active' => false]);
}
