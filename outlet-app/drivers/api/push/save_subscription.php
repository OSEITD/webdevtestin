<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/supabase-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['subscription']) || !isset($input['endpoint']) || !isset($input['keys'])) {
        throw new Exception('Missing required fields');
    }

    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? 'driver';
    $companyId = $_SESSION['company_id'] ?? null;
    $subscription = $input['subscription'];
    $endpoint = $input['endpoint'];
    $keys = $input['keys'];

    $supabase = new SupabaseHelper();


    $deactivateData = array('is_active' => false, 'updated_at' => date('c'));
    $supabase->patch('push_subscriptions', $deactivateData, "user_id=eq.$userId&user_role=eq.$userRole");


    $existingQuery = "user_id=eq.$userId&user_role=eq.$userRole";
    $existing = $supabase->get('push_subscriptions', $existingQuery);

    if (!empty($existing)) {
        $subscriptionId = $existing[0]['id'];

        $updateData = array(
            'p256dh_key' => $keys['p256dh'],
            'auth_key' => $keys['auth'],
            'subscription_json' => is_string($subscription) ? $subscription : json_encode($subscription),
            'is_active' => true,
            'company_id' => $companyId,
            'updated_at' => date('c')
        );

        $updated = $supabase->patch('push_subscriptions', $updateData, "id=eq.$subscriptionId");

        echo json_encode(array(
            'success' => true,
            'message' => 'Subscription updated',
            'subscription_id' => $subscriptionId
        ));
    } else {
        $newSubscription = array(
            'user_id' => $userId,
            'user_role' => $userRole,
            'company_id' => $companyId,
            'endpoint' => $endpoint,
            'p256dh_key' => $keys['p256dh'],
            'auth_key' => $keys['auth'],
            'subscription_json' => is_string($subscription) ? $subscription : json_encode($subscription),
            'is_active' => true,
            'created_at' => date('c'),
            'updated_at' => date('c')
        );

        $created = $supabase->post('push_subscriptions', $newSubscription);

        error_log('Subscription created: ' . print_r($created, true));

        if ($created) {
            echo json_encode(array(
                'success' => true,
                'message' => 'Subscription created',
                'subscription_id' => isset($created['id']) ? $created['id'] : null,
                'data' => $created
            ));
        } else {
            throw new Exception('Failed to create subscription');
        }
    }

} catch (Exception $e) {
    error_log('Save subscription error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
