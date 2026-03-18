<?php
/**
 * Payout preview endpoint for Super Admin.
 *
 * Returns the exact payload that will be sent to Lenco when the payout is executed.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/WalletManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$payoutId = $input['payout_id'] ?? null;

if (empty($payoutId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payout ID is required']);
    exit;
}

// Minimal auth check (requires logged-in super_admin)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Fetch payout record
    $payoutResponse = callSupabaseWithServiceKey("company_payouts?id=eq.{$payoutId}&limit=1", 'GET');
    if (empty($payoutResponse) || !isset($payoutResponse[0])) {
        throw new Exception('Payout record not found');
    }
    $payout = $payoutResponse[0];

    // Fetch company record
    $companyResponse = callSupabaseWithServiceKey("companies?id=eq.{$payout['company_id']}&limit=1", 'GET');
    $company = is_array($companyResponse) && isset($companyResponse[0]) ? $companyResponse[0] : [];

    // Build payload using the same logic as the payout service
    $payload = LencoPayoutService::buildPayload($payout, $company, $_SESSION['user_id'] ?? '');

    echo json_encode(['success' => true, 'payload' => $payload, 'endpoint' => rtrim(getenv('LENCO_API_BASE') ?: '', '/') . '/payouts']);
} catch (Exception $e) {
    http_response_code(500);
    $message = $e->getMessage();
    if (empty($message)) {
        $message = 'Unknown error building payout preview.';
    }
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error' => get_class($e),
    ]);
}
