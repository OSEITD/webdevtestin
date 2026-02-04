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
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    if (!isset($input['endpoint'])) {
        throw new Exception('Missing endpoint');
    }

    $endpoint = $input['endpoint'];
    $userId = $_SESSION['user_id'];

    
    $config = require __DIR__ . '/../config.php';
    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key']; 
    
    if (!$supabaseUrl || !$supabaseKey) {
        throw new Exception('Supabase configuration missing');
    }

    
    $updateData = json_encode([
        'is_active' => false,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?user_id=eq.$userId&endpoint=eq." . urlencode($endpoint));
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
        throw new Exception('Failed to remove subscription');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Push notifications disabled successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    
    error_log('Remove Push Subscription Error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
