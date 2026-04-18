<?php
require_once __DIR__ . '/../../api/init.php';
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/../includes/WalletManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$payoutId = $input['payout_id'] ?? null;
$companyId = $input['company_id'] ?? null;
$status = $input['status'] ?? null;
$reference = trim($input['reference'] ?? '');
$notes = trim($input['notes'] ?? '');
$failureReason = trim($input['failure_reason'] ?? '');

if (!$payoutId || !$companyId || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
    exit;
}

$allowedStatuses = ['approved', 'processing', 'completed', 'failed', 'cancelled'];
if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payout status.']);
    exit;
}

$transitionRules = [
    'pending' => ['approved', 'processing', 'failed', 'cancelled'],
    'approved' => ['processing', 'completed', 'failed', 'cancelled'],
    'processing' => ['completed', 'failed', 'cancelled'],
];

try {
    // Fetching the existing payout to know the amount and company
    $supabase = new SupabaseClient();
    $payoutResp = $supabase->getRecord("company_payouts?id=eq.{$payoutId}", true);
    $payoutData = is_object($payoutResp) ? ($payoutResp->data[0] ?? null) : ($payoutResp[0] ?? null);

    if (empty($payoutData)) {
        throw new Exception('Payout request not found.');
    }

    // enforcing server-trust company id to avoid client override
    $companyId = $payoutData['company_id'] ?? null;
    if (empty($companyId)) {
        throw new Exception('Payout request missing company association.');
    }

    $currentStatus = $payoutData['status'] ?? 'pending';
    if (in_array($currentStatus, ['completed', 'failed', 'cancelled'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Payout request already finalized.']);
        exit;
    }

    if (isset($transitionRules[$currentStatus]) && !in_array($status, $transitionRules[$currentStatus], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid payout state transition from ' . $currentStatus . ' to ' . $status]);
        exit;
    }

    $amount = floatval($payoutData['amount'] ?? 0);

    $now = gmdate('Y-m-d\TH:i:s\Z');
    $adminId = $_SESSION['user_id'] ?? null;

    $updateData = [
        'status' => $status,
        'updated_at' => $now,
        'notes' => $notes ?: null,
        'external_reference' => $reference ?: null
    ];

    if (in_array($status, ['approved', 'processing'], true)) {
        $updateData['approved_at'] = $now;
        $updateData['approved_by'] = $adminId;
    }
    if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
        $updateData['completed_at'] = $now;
        $updateData['completed_by'] = $adminId;
    }

    if ($status === 'failed') {
        $updateData['failure_reason'] = $failureReason ?: $notes ?: 'Payout failed';
    }

    callSupabaseWithServiceKey("company_payouts?id=eq.{$payoutId}", 'PATCH', $updateData);


    CompanyWalletManager::applyPayoutStatusUpdate($companyId, $amount, $status);

    // Updatin the wallet balances for completed / failed/cancelled payouts ensuring that the wallet remains consistent even if the payout request itself did not adjust balances.
    $wallet = CompanyWalletManager::getWallet($companyId);
    if ($wallet && $amount > 0) {
        $currentAvailable = (float)$wallet['available_balance'];
        $currentPending = (float)$wallet['pending_balance'];
        $walletUpdate = [];

     
        if ($status === 'completed') {
            if ($currentPending >= $amount) {
                $walletUpdate['pending_balance'] = $currentPending - $amount;
            } else {
                
                $walletUpdate['available_balance'] = max(0, $currentAvailable - $amount);
            }
            $walletUpdate['total_paid_out'] = (float)$wallet['total_paid_out'] + $amount;
            $walletUpdate['last_payout_at'] = $now;
        }

        if (in_array($status, ['failed', 'cancelled'], true)) {
            if ($currentPending >= $amount) {
                $walletUpdate['pending_balance'] = $currentPending - $amount;
            }
            $walletUpdate['available_balance'] = $currentAvailable + $amount;
        }

        if (!empty($walletUpdate)) {
      
            callSupabaseWithServiceKey("company_wallets?id=eq.{$companyId}", 'PATCH', $walletUpdate);
        }
    }

    echo json_encode(['status' => 'success']);
    exit;
} catch (Exception $e) {
    error_log('Error processing payout: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to process payout.']);
    exit;
}
