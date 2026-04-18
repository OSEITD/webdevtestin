<?php
require_once __DIR__ . '/../../api/init.php';
require_once __DIR__ . '/../includes/WalletManager.php';
require_once __DIR__ . '/supabase-client.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$companyId = $_SESSION['company_id'] ?? $_SESSION['id'] ?? null;
if (!$companyId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = isset($input['amount']) ? filter_var($input['amount'], FILTER_VALIDATE_FLOAT) : false;
$payout_method = trim($input['payout_method'] ?? 'bank_transfer');
$bankName = $input['bank_name'] ?? '';
$bankAccountNumber = $input['bank_account_number'] ?? '';
$bankAccountName = $input['bank_account_name'] ?? '';
$mobileNumber = $input['mobile_number'] ?? '';

if ($amount === false || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid amount is required.']);
    exit;
}

$allowedMethods = ['bank_transfer', 'mobile_money'];
if (!in_array($payout_method, $allowedMethods)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payout method.']);
    exit;
}

if ($payout_method === 'bank_transfer') {
    if (empty(trim($bankName)) || empty(trim($bankAccountNumber)) || empty(trim($bankAccountName))) {
        echo json_encode(['success' => false, 'message' => 'Bank account details are required for bank transfer.']);
        exit;
    }
} else {
    if (empty(trim($mobileNumber))) {
        echo json_encode(['success' => false, 'message' => 'Mobile money number is required for mobile money payouts.']);
        exit;
    }
}


$companyCurrency = 'ZMW';
try {
    $supabase = new SupabaseClient();
    $companyRes = $supabase->getRecord("companies?id=eq.{$companyId}", true);
    $companyData = is_object($companyRes) ? ($companyRes->data[0] ?? null) : ($companyRes[0] ?? null);
    if (!empty($companyData['currency'])) {
        $companyCurrency = $companyData['currency'];
    }
} catch (Exception $e) {
    
}

$result = CompanyWalletManager::requestPayout(
    $companyId,
    $amount,
    $payout_method,
    [
        'bank_name' => trim($bankName),
        'bank_account_number' => trim($bankAccountNumber),
        'bank_account_name' => trim($bankAccountName),
        'mobile_number' => trim($mobileNumber),
        'currency' => $companyCurrency,
        'requested_by' => $_SESSION['user_id'] ?? null
    ]
);

// sending a notification email to the company contact email if the payout if completed.
if (!empty($result['success']) && $result['success'] === true) {
    try {
        $supabase = new SupabaseClient();
        $companyResp = $supabase->getRecord("companies?id=eq.{$companyId}", true);
        $companyData = is_object($companyResp) ? ($companyResp->data[0] ?? null) : ($companyResp[0] ?? null);

        $toEmail = $companyData['contact_email'] ?? null;
        $toName = $companyData['company_name'] ?? '';
        $currency = $companyData['currency'] ?? 'ZMW';

        if (!empty($toEmail)) {
            require_once __DIR__ . '/../includes/AdminEmailHelper.php';
            $emailHelper = new AdminEmailHelper();

            $subject = "Payout Request Received";
            $body = "Hello {$toName},\n\n" .
                    "We have received your payout request for {$currency} {$amount} via " .
                    strtoupper(str_replace('_', ' ', $payout_method)) . ".\n\n" .
                    "We will process it shortly and notify you once it is completed.\n\n" .
                    "Thank you,\n" .
                    "WDParcel Team";

            $emailHelper->sendEmail($toEmail, $toName, $subject, $body);
        }
    } catch (Exception $e) {
        error_log('Failed to send payout request email: ' . $e->getMessage());
    }
}

echo json_encode($result);
exit;
