<?php

require_once __DIR__ . '/../includes/security_headers.php';
SecurityHeaders::apply();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }


    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }


    $input = json_decode(file_get_contents('php://input'), true);

    error_log('===== OUTLET MANAGER SUBSCRIPTION REQUEST =====');
    error_log('✅ CORRECT API FILE: /api/save_push_subscription.php (OUTLET MANAGER API)');
    error_log('Input data: ' . json_encode($input));
    error_log('Session user_id: ' . ($_SESSION['user_id'] ?? 'NULL'));
    error_log('Session company_id: ' . ($_SESSION['company_id'] ?? 'NULL'));
    error_log('Session role: ' . ($_SESSION['role'] ?? 'NULL'));
    error_log('Session outlet_id: ' . ($_SESSION['outlet_id'] ?? 'NULL'));
    error_log('Session email: ' . ($_SESSION['email'] ?? 'NULL'));

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    if (!isset($input['subscription'])) {
        throw new Exception('Missing subscription data');
    }

    $subscription = $input['subscription'];
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    $userRole = $input['user_role'] ?? $_SESSION['role'] ?? 'outlet_manager';

    error_log('User role: ' . $userRole);
    error_log('Subscription endpoint: ' . ($subscription['endpoint'] ?? 'NULL'));
    error_log('Has p256dh key: ' . (isset($subscription['keys']['p256dh']) ? 'YES' : 'NO'));
    error_log('Has auth key: ' . (isset($subscription['keys']['auth']) ? 'YES' : 'NO'));


    $config = require __DIR__ . '/../config.php';
    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key'];

    if (!$supabaseUrl || !$supabaseKey) {
        throw new Exception('Supabase configuration missing');
    }

    // First check if subscription with same endpoint already exists
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?user_id=eq.$userId&user_role=eq.$userRole&endpoint=eq." . urlencode($subscription['endpoint']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to check existing subscription');
    }

    $existingData = json_decode($response, true);

    error_log('Check existing result (HTTP ' . $httpCode . '): ' . (empty($existingData) ? 'No existing subscription' : 'Found ' . count($existingData) . ' subscription(s)'));

    if (!empty($existingData)) {
        // Update existing subscription
        $subscriptionId = $existingData[0]['id'];

        error_log('Updating existing subscription: ' . $subscriptionId);

        $updateData = json_encode([
            'p256dh_key' => $subscription['keys']['p256dh'],
            'auth_key' => $subscription['keys']['auth'],
            'subscription_json' => json_encode($subscription),
            'user_role' => $userRole,
            'updated_at' => date('c'),
            'is_active' => true
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?id=eq.$subscriptionId");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 204) {
            error_log('Update failed (HTTP ' . $httpCode . '): ' . $response);
            throw new Exception('Failed to update subscription');
        }

        error_log('✅ Subscription updated successfully: ' . $subscriptionId);

        echo json_encode([
            'success' => true,
            'message' => 'Push notifications enabled successfully',
            'subscription_id' => $subscriptionId
        ]);
    } else {
        // Deactivate old subscriptions before creating new one
        $deactivateData = json_encode([
            'is_active' => false,
            'updated_at' => date('c')
        ]);

        error_log('Deactivating old subscriptions for user: ' . $userId . ', role: ' . $userRole);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?user_id=eq.$userId&user_role=eq.$userRole");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $deactivateData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]);
        $deactivateResponse = curl_exec($ch);
        $deactivateHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log('Deactivate response (HTTP ' . $deactivateHttpCode . '): ' . $deactivateResponse);

        // Create new subscription
        error_log('Creating new subscription for user: ' . $userId);

        $insertData = json_encode([
            'user_id' => $userId,
            'company_id' => $companyId,
            'user_role' => $userRole,
            'endpoint' => $subscription['endpoint'],
            'p256dh_key' => $subscription['keys']['p256dh'],
            'auth_key' => $subscription['keys']['auth'],
            'subscription_json' => json_encode($subscription),
            'is_active' => true,
            'created_at' => date('c'),
            'updated_at' => date('c')
        ]);

        error_log('Insert payload: ' . $insertData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $insertData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            error_log('Insert failed (HTTP ' . $httpCode . '): ' . $response);
            throw new Exception('Failed to save subscription: ' . $response);
        }

        $resultData = json_decode($response, true);

        error_log('✅ Subscription created successfully: ' . ($resultData[0]['id'] ?? 'unknown'));
        error_log('Insert response: ' . $response);

        echo json_encode([
            'success' => true,
            'message' => 'Push notifications enabled successfully',
            'subscription_id' => $resultData[0]['id'] ?? null
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);

    error_log('Manager Push Subscription Error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
