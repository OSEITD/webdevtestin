<?php
/**
 * Lenco Payment Verification API (Secured)
 * 
 * Verifies payment status with Lenco API server-side.
 * - Requires active session (authenticated user).
 * - Rate-limited per IP.
 * - Reference is sanitised before use.
 * 
 * Documentation: https://lenco-api.readme.io/v2.0/reference/accept-payments
 */

// ─── Security Headers ────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Allow same-origin only (adjust if your frontend is on a different domain)
$allowedOrigin = ($_SERVER['HTTP_ORIGIN'] ?? '');
if (!empty($allowedOrigin) && (
    str_contains($allowedOrigin, $_SERVER['HTTP_HOST'] ?? '') ||
    str_contains($allowedOrigin, 'localhost')
)) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
} else {
    header('Access-Control-Allow-Origin: ' . ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once 'lenco_config.php';
require_once __DIR__ . '/../../config.php';

// ─── Session Authentication ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized — please log in']);
    exit();
}

// ─── Rate Limiting ───────────────────────────────────────────────────────────
if (!lencoRateLimitCheck('verify')) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait and try again.']);
    exit();
}

// ─── Get & Sanitise Reference ────────────────────────────────────────────────
$reference = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $reference = $_GET['reference'] ?? '';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $reference = $input['reference'] ?? '';
}

// Strip anything that isn't alphanumeric, dash, or underscore
$reference = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($reference));

if (empty($reference) || strlen($reference) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payment reference']);
    exit();
}

try {
    // ─── Verify with Lenco API ───────────────────────────────────────────────
    $apiUrl = getLencoBaseUrl() . '/collections/status/' . urlencode($reference);
    $secretKey = getLencoSecretKey();
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Network error while verifying payment');
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode !== 200 || !$responseData) {
        error_log("Lenco Verify Error — HTTP {$httpCode}, Ref: {$reference}");
        throw new Exception('Payment verification failed. Please try again.');
    }
    
    // ─── Process Response ────────────────────────────────────────────────────
    if (isset($responseData['status']) && $responseData['status'] === true && isset($responseData['data'])) {
        $paymentData = $responseData['data'];
        $paymentStatus = $paymentData['status'] ?? 'unknown';
        
        error_log("Lenco Verify OK — Ref: {$reference}, Status: {$paymentStatus}, User: {$_SESSION['user_id']}");
        
        echo json_encode([
            'success' => true,
            'verified' => ($paymentStatus === 'successful'),
            'data' => [
                'id'                  => $paymentData['id'] ?? null,
                'reference'           => $paymentData['reference'] ?? $reference,
                'lenco_reference'     => $paymentData['lencoReference'] ?? null,
                'amount'              => $paymentData['amount'] ?? 0,
                'fee'                 => $paymentData['fee'] ?? 0,
                'currency'            => $paymentData['currency'] ?? 'ZMW',
                'status'              => $paymentStatus,
                'type'                => $paymentData['type'] ?? null,
                'completed_at'        => $paymentData['completedAt'] ?? null,
                'bearer'              => $paymentData['bearer'] ?? 'merchant',
                'settlement_status'   => $paymentData['settlementStatus'] ?? null,
                'mobile_money_details'=> $paymentData['mobileMoneyDetails'] ?? null,
                'card_details'        => $paymentData['cardDetails'] ?? null
            ],
            'message' => $paymentStatus === 'successful' 
                ? 'Payment verified successfully' 
                : 'Payment status: ' . $paymentStatus
        ]);
    } else {
        echo json_encode([
            'success'  => false,
            'verified' => false,
            'error'    => $responseData['message'] ?? 'Payment verification failed'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Lenco Verify Exception — " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success'  => false,
        'verified' => false,
        'error'    => $e->getMessage()
    ]);
}
?>
