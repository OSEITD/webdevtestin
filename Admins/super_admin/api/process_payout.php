<?php
/**
 * API Endpoint: Process Payout Request
 * Handles updates from Super Admin to approve, complete, or reject payouts.
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/WalletManager.php';

// Endpoint handles only POST via JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// init.php handles the CSRF validation and JSON decoding into $_POST, 
// so we directly use $_POST array.
$payoutId = $_POST['payout_id'] ?? null;
$companyId = $_POST['company_id'] ?? null;
$status = $_POST['status'] ?? null;
$notes = $_POST['notes'] ?? '';
$reference = $_POST['reference'] ?? '';

if (!$payoutId || !$companyId || !$status) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// User doing the processing
$adminUserId = $_SESSION['user_id'] ?? null;

// Validate status transition
$validStatuses = ['approved', 'processing', 'completed', 'failed', 'cancelled'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit;
}

// Execute through WalletManager explicitly
$result = WalletManager::resolvePayout($payoutId, $companyId, $status, $adminUserId, $notes, $reference);

if ($result) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Payout successfully updated.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to resolve the payout. It may already be completed or there was a system error.'
    ]);
}
exit();