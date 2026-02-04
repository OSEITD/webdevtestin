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

if (!$input) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request data'
    ]);
    exit();
}

$requiredFields = ['amount', 'payment_method', 'customer_email', 'customer_phone', 'customer_name'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: $field"
        ]);
        exit();
    }
}

try {
    $amount = floatval($input['amount']);
    $paymentMethod = $input['payment_method']; 
    $customerEmail = filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL);
    $customerPhone = $input['customer_phone'];
    $customerName = $input['customer_name'];
    $trackingNumber = $input['tracking_number'] ?? 'TBD';
    
    
    if (!$customerEmail) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid email address'
        ]);
        exit();
    }
    
    
    if (!validateZambianMobileNumber($customerPhone)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid Zambian phone number format'
        ]);
        exit();
    }
    $formattedPhone = formatZambianPhoneNumber($customerPhone);
    
    
    $txRef = 'PARCEL-' . time() . '-' . uniqid();
    
    
    $transactionFee = calculateTransactionFee($amount, $paymentMethod);
    $totalAmount = $amount + $transactionFee;
    
    
    $paymentData = [
        'tx_ref' => $txRef,
        'amount' => $totalAmount,
        'currency' => FLUTTERWAVE_CURRENCY,
        'redirect_url' => PAYMENT_REDIRECT_URL,
        'payment_options' => $paymentMethod === 'mobile_money' ? 'mobilemoneyzambia' : 'card',
        'customer' => [
            'email' => $customerEmail,
            'phonenumber' => $formattedPhone,
            'name' => $customerName
        ],
        'customizations' => [
            'title' => 'Parcel Delivery Payment',
            'description' => "Payment for parcel delivery - Track #$trackingNumber",
            'logo' => 'https://yourdomain.com/logo.png' 
        ],
        'meta' => [
            'tracking_number' => $trackingNumber,
            'user_id' => $_SESSION['user_id'],
            'outlet_id' => $_SESSION['outlet_id'] ?? null,
            'company_id' => $_SESSION['company_id'] ?? null,
            'delivery_fee' => $amount,
            'transaction_fee' => $transactionFee
        ]
    ];
    
    
    if ($paymentMethod === 'mobile_money' && isset($input['mobile_provider'])) {
        $paymentData['meta']['mobile_network'] = strtoupper($input['mobile_provider']);
    }
    
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, FLUTTERWAVE_API_BASE_URL . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
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
            'error' => 'Failed to initialize payment with Flutterwave',
            'details' => $response
        ]);
        exit();
    }
    
    $result = json_decode($response, true);
    
    if ($result['status'] === 'success') {
        
        $paymentDB = new PaymentTransactionDB();
        $dbResult = $paymentDB->createTransaction([
            'tx_ref' => $txRef,
            'company_id' => $_SESSION['company_id'],
            'outlet_id' => $_SESSION['outlet_id'] ?? null,
            'user_id' => $_SESSION['user_id'],
            'parcel_id' => $input['parcel_id'] ?? null,
            'amount' => $amount,
            'transaction_fee' => $transactionFee,
            'total_amount' => $totalAmount,
            'currency' => FLUTTERWAVE_CURRENCY,
            'payment_method' => $paymentMethod,
            'mobile_network' => isset($input['mobile_provider']) ? strtoupper($input['mobile_provider']) : null,
            'mobile_number' => $paymentMethod === 'mobile_money' ? $customerPhone : null,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $formattedPhone,
            'payment_link' => $result['data']['link'],
            'redirect_url' => PAYMENT_REDIRECT_URL,
            'metadata' => [
                'tracking_number' => $trackingNumber,
                'payment_data' => $paymentData['meta']
            ]
        ]);
        
        if (!$dbResult['success']) {
            error_log('Failed to store payment transaction: ' . $dbResult['error']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'payment_link' => $result['data']['link'],
                'tx_ref' => $txRef,
                'amount' => $totalAmount,
                'transaction_fee' => $transactionFee,
                'transaction_id' => $dbResult['transaction_id'] ?? null
            ],
            'message' => 'Payment initialized successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['message'] ?? 'Payment initialization failed'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
