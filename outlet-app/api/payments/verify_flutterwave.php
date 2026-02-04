<?php

require_once '../../includes/session_manager.php';
require_once '../../includes/PaymentTransactionDB.php';
require_once 'flutterwave_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['transaction_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Transaction ID is required'
    ]);
    exit();
}

try {
    $transactionId = $input['transaction_id'];
    
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, FLUTTERWAVE_API_BASE_URL . "/transactions/$transactionId/verify");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . getFlutterwaveSecretKey()
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to verify payment',
            'details' => $response
        ]);
        exit();
    }
    
    $result = json_decode($response, true);
    
    if ($result['status'] === 'success') {
        $data = $result['data'];
        
        
        if ($data['status'] === 'successful' && $data['currency'] === FLUTTERWAVE_CURRENCY) {
            
            $paymentDB = new PaymentTransactionDB();
            $dbResult = $paymentDB->verifyTransaction($data['tx_ref'], [
                'transaction_id' => $data['id'],
                'tx_ref' => $data['tx_ref'],
                'status' => $data['status'],
                'processor_response' => $data['processor_response'] ?? null,
                'auth_model' => $data['auth_model'] ?? null,
                'payment_type' => $data['payment_type'] ?? null,
                'card' => $data['card'] ?? null
            ]);
            
            if (!$dbResult['success']) {
                error_log('Failed to update payment transaction: ' . $dbResult['error']);
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'transaction_id' => $data['id'],
                    'tx_ref' => $data['tx_ref'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'payment_type' => $data['payment_type'],
                    'status' => $data['status'],
                    'charged_amount' => $data['charged_amount']
                ],
                'message' => 'Payment verified successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Payment verification failed',
                'status' => $data['status']
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['message'] ?? 'Verification failed'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
