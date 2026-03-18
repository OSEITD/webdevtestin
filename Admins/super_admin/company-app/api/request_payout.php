<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/WalletManager.php';

header('Content-Type: application/json');

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
$amount = $input['amount'] ?? 0;
$payout_method = $input['payout_method'] ?? 'bank_transfer';
$bankName = $input['bank_name'] ?? '';
$bankAccountNumber = $input['bank_account_number'] ?? '';
$bankAccountName = $input['bank_account_name'] ?? '';
$mobileNumber = $input['mobile_number'] ?? '';

if (empty($amount) || $amount <= 0) {
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

$result = CompanyWalletManager::requestPayout(
    $companyId,
    $amount,
    $payout_method,
    [
        'bank_name' => trim($bankName),
        'bank_account_number' => trim($bankAccountNumber),
        'bank_account_name' => trim($bankAccountName),
        'mobile_number' => trim($mobileNumber)
    ]
);

echo json_encode($result);
exit;
