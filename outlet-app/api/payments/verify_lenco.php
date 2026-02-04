<?php
/**
 * Lenco Payment Verification API
 * 
 * This endpoint verifies payment status with Lenco API
 * Called after a payment is completed to confirm the transaction
 * 
 * Documentation: https://lenco-api.readme.io/v2.0/reference/accept-payments
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'lenco_config.php';
require_once __DIR__ . '/../../config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get reference from request
$reference = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $reference = $_GET['reference'] ?? '';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $reference = $input['reference'] ?? '';
}

if (empty($reference)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Payment reference is required'
    ]);
    exit();
}

try {
    // Verify payment with Lenco API
    $apiUrl = getLencoBaseUrl() . '/collections/status/' . urlencode($reference);
    $secretKey = getLencoSecretKey();
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Network error: ' . $curlError);
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode !== 200 || !$responseData) {
        throw new Exception('Failed to verify payment with Lenco API. HTTP Code: ' . $httpCode);
    }
    
    // Check if payment was successful
    if (isset($responseData['status']) && $responseData['status'] === true && isset($responseData['data'])) {
        $paymentData = $responseData['data'];
        $paymentStatus = $paymentData['status'] ?? 'unknown';
        
        // Log the verification
        error_log("Lenco Payment Verification - Reference: {$reference}, Status: {$paymentStatus}");
        
        // Return structured response
        echo json_encode([
            'success' => true,
            'verified' => ($paymentStatus === 'successful'),
            'data' => [
                'id' => $paymentData['id'] ?? null,
                'reference' => $paymentData['reference'] ?? $reference,
                'lenco_reference' => $paymentData['lencoReference'] ?? null,
                'amount' => $paymentData['amount'] ?? 0,
                'fee' => $paymentData['fee'] ?? 0,
                'currency' => $paymentData['currency'] ?? 'ZMW',
                'status' => $paymentStatus,
                'type' => $paymentData['type'] ?? null,
                'completed_at' => $paymentData['completedAt'] ?? null,
                'bearer' => $paymentData['bearer'] ?? 'merchant',
                'settlement_status' => $paymentData['settlementStatus'] ?? null,
                'mobile_money_details' => $paymentData['mobileMoneyDetails'] ?? null,
                'card_details' => $paymentData['cardDetails'] ?? null
            ],
            'message' => $paymentStatus === 'successful' 
                ? 'Payment verified successfully' 
                : 'Payment status: ' . $paymentStatus
        ]);
    } else {
        // Payment verification returned an error or unexpected response
        echo json_encode([
            'success' => false,
            'verified' => false,
            'error' => $responseData['message'] ?? 'Payment verification failed',
            'data' => $responseData
        ]);
    }
    
} catch (Exception $e) {
    error_log("Lenco Payment Verification Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'verified' => false,
        'error' => $e->getMessage()
    ]);
}

?>
