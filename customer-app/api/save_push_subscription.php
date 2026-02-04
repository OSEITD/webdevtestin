<?php

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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    if (!isset($input['subscription']) || !isset($input['tracking_number'])) {
        throw new Exception('Missing required fields: subscription and tracking_number');
    }

    $subscription = $input['subscription'];
    $trackingNumber = $input['tracking_number'];
    $userRole = $input['user_role'] ?? 'customer';

    $config = require __DIR__ . '/../../outlet-app/config.php';
    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key'];

    if (!$supabaseUrl || !$supabaseKey) {
        throw new Exception('Supabase configuration missing');
    }

    session_start();
    $userId = null;
    $parcelData = null;
    $customerRole = null;

    error_log("Session data: " . json_encode($_SESSION));

    if (isset($_SESSION['verified_tracking_data']) && !empty($_SESSION['verified_tracking_data']['parcel'])) {
        $parcel = $_SESSION['verified_tracking_data']['parcel'];
        $customerRole = $_SESSION['verified_tracking_data']['customer_role'] ?? null;
        error_log("Customer role: " . $customerRole);
        error_log("Parcel global_sender_id: " . ($parcel['global_sender_id'] ?? 'null'));
        error_log("Parcel global_receiver_id: " . ($parcel['global_receiver_id'] ?? 'null'));

        if ($customerRole === 'sender' && !empty($parcel['global_sender_id'])) {
            $userId = $parcel['global_sender_id'];
            error_log("Using sender user_id from session: " . $userId);
        } elseif ($customerRole === 'receiver' && !empty($parcel['global_receiver_id'])) {
            $userId = $parcel['global_receiver_id'];
            error_log("Using receiver user_id from session: " . $userId);
        }

        if (!$userId && !empty($_SESSION['verified_tracking_data']['track_number'])) {
            $sessionTrackingNumber = $_SESSION['verified_tracking_data']['track_number'];
            error_log("Querying database for user_id using tracking_number from session: " . $sessionTrackingNumber);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/parcels?track_number=eq.$sessionTrackingNumber&select=global_sender_id,global_receiver_id");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]);

            $parcelResponse = curl_exec($ch);
            curl_close($ch);

            $parcelData = json_decode($parcelResponse, true);
            if (!empty($parcelData)) {
                if ($customerRole === 'sender' && !empty($parcelData[0]['global_sender_id'])) {
                    $userId = $parcelData[0]['global_sender_id'];
                    error_log("Got sender user_id from database: " . $userId);
                } elseif ($customerRole === 'receiver' && !empty($parcelData[0]['global_receiver_id'])) {
                    $userId = $parcelData[0]['global_receiver_id'];
                    error_log("Got receiver user_id from database: " . $userId);
                }
            }
        }

        if (!$userId && !empty($trackingNumber) && $trackingNumber !== 'general') {
            error_log("Querying database for user_id using tracking_number from request body: " . $trackingNumber);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/parcels?track_number=eq.$trackingNumber&select=global_sender_id,global_receiver_id");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]);
            $parcelResponse = curl_exec($ch);
            curl_close($ch);
            $parcelData = json_decode($parcelResponse, true);
            error_log("Parcel query result: " . json_encode($parcelData));
            if (!empty($parcelData)) {
                if ($customerRole === 'sender' && !empty($parcelData[0]['global_sender_id'])) {
                    $userId = $parcelData[0]['global_sender_id'];
                    error_log("Got sender user_id from request tracking_number: " . $userId);
                } elseif ($customerRole === 'receiver' && !empty($parcelData[0]['global_receiver_id'])) {
                    $userId = $parcelData[0]['global_receiver_id'];
                    error_log("Got receiver user_id from request tracking_number: " . $userId);
                } else {
                    $userId = $parcelData[0]['global_sender_id'] ?? $parcelData[0]['global_receiver_id'] ?? null;
                    error_log("Got user_id from request tracking_number (no role match): " . ($userId ?? 'null'));
                }
            }
        }
    } else {
        error_log("No session data found");
    }

    error_log("Final user_id: " . ($userId ?? 'null'));
    error_log("Final tracking_number: " . $trackingNumber);

    // First check if subscription with same endpoint already exists
    $ch = curl_init();
    if ($userId) {
        curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?user_id=eq.$userId&user_role=eq.$userRole&endpoint=eq." . urlencode($subscription['endpoint']));
    } else {
        curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?tracking_number=eq." . urlencode($trackingNumber) . "&user_role=eq.$userRole&endpoint=eq." . urlencode($subscription['endpoint']));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    error_log("Check existing subscription response (HTTP $httpCode): " . $response);

    $existingData = json_decode($response, true);
    error_log("Existing subscription data: " . json_encode($existingData));

    if (!empty($existingData) && isset($existingData[0]['id'])) {
        // Update existing subscription
        $subscriptionId = $existingData[0]['id'];
        $updateData = json_encode([
            'p256dh_key' => $subscription['keys']['p256dh'],
            'auth_key' => $subscription['keys']['auth'],
            'subscription_json' => json_encode($subscription),
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
        curl_exec($ch);
        curl_close($ch);
        echo json_encode([
            'success' => true,
            'message' => 'Subscription updated successfully',
            'subscription_id' => $subscriptionId
        ]);
    } else {
        // Deactivate old subscriptions before creating new one
        $deactivateData = json_encode(['is_active' => false]);
        $ch = curl_init();
        if ($userId) {
            curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?user_id=eq.$userId&user_role=eq.$userRole");
        } else {
            curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?tracking_number=eq." . urlencode($trackingNumber) . "&user_role=eq.$userRole");
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $deactivateData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]);
        curl_exec($ch);
        curl_close($ch);

        // Create new subscription
        $insertPayload = [
            'user_role' => $userRole,
            'endpoint' => $subscription['endpoint'],
            'p256dh_key' => $subscription['keys']['p256dh'],
            'auth_key' => $subscription['keys']['auth'],
            'subscription_json' => json_encode($subscription),
            'created_at' => date('c'),
            'is_active' => true
        ];
        if ($userId) {
            $insertPayload['user_id'] = $userId;
        }
        $insertData = json_encode($insertPayload);
        error_log("INSERT payload: " . $insertData);
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
        error_log("INSERT response (HTTP $httpCode): " . $response);
        $resultData = json_decode($response, true);
        echo json_encode([
            'success' => true,
            'message' => 'Subscription saved successfully',
            'subscription_id' => $resultData[0]['id'] ?? null,
            'user_id' => $userId,
            'tracking_number' => $trackingNumber
        ]);
    }

} catch (Exception $e) {
    error_log('Push Subscription Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
