<?php


// Environment: 'sandbox' for testing, 'live' for production
define('LENCO_ENV', 'sandbox');

// Sandbox/Test Credentials
define('LENCO_PUBLIC_KEY_SANDBOX', 'pub-88dd921c0ecd73590459a1dd5a9343c77db0f3c344f222b9');
define('LENCO_SECRET_KEY_SANDBOX', '993bed87f9d592566a6cce2cefd79363d1b7e95af3e1e6642b294ce5fc8c59f6');

// Production Credentials (Replace with actual keys when going live)
define('LENCO_PUBLIC_KEY_LIVE', 'YOUR_LIVE_PUBLIC_KEY');
define('LENCO_SECRET_KEY_LIVE', 'YOUR_LIVE_SECRET_KEY');

// API Base URLs
define('LENCO_SANDBOX_BASE_URL', 'https://sandbox.lenco.co/access/v2');
define('LENCO_LIVE_BASE_URL', 'https://api.lenco.co/access/v2');

// Widget Script URLs
define('LENCO_WIDGET_SANDBOX_URL', 'https://pay.sandbox.lenco.co/js/v1/inline.js');
define('LENCO_WIDGET_LIVE_URL', 'https://pay.lenco.co/js/v1/inline.js');

// Default Currency
define('LENCO_CURRENCY', 'ZMW');

// Fee Configuration (adjust based on your Lenco dashboard settings)
define('LENCO_MOBILE_MONEY_FEE_PERCENTAGE', 2.5);
define('LENCO_CARD_FEE_PERCENTAGE', 2.9);

// Payment Channels
define('LENCO_PAYMENT_CHANNELS', ['card', 'mobile-money']);

// Payment Statuses
define('LENCO_STATUS_SUCCESSFUL', 'successful');
define('LENCO_STATUS_PENDING', 'pending');
define('LENCO_STATUS_FAILED', 'failed');

/**
 * Get Lenco public key based on environment
 */
function getLencoPublicKey() {
    return LENCO_ENV === 'live' ? LENCO_PUBLIC_KEY_LIVE : LENCO_PUBLIC_KEY_SANDBOX;
}

/**
 * Get Lenco secret key based on environment
 */
function getLencoSecretKey() {
    return LENCO_ENV === 'live' ? LENCO_SECRET_KEY_LIVE : LENCO_SECRET_KEY_SANDBOX;
}

/**
 * Get Lenco API base URL based on environment
 */
function getLencoBaseUrl() {
    return LENCO_ENV === 'live' ? LENCO_LIVE_BASE_URL : LENCO_SANDBOX_BASE_URL;
}

/**
 * Get Lenco widget script URL based on environment
 */
function getLencoWidgetUrl() {
    return LENCO_ENV === 'live' ? LENCO_WIDGET_LIVE_URL : LENCO_WIDGET_SANDBOX_URL;
}

/**
 * Calculate transaction fee
 * 
 * @param float $amount The transaction amount
 * @param string $method Payment method ('card' or 'mobile-money')
 * @return float The calculated fee
 */
function calculateLencoTransactionFee($amount, $method = 'mobile-money') {
    if ($method === 'mobile-money') {
        return ($amount * LENCO_MOBILE_MONEY_FEE_PERCENTAGE) / 100;
    } else if ($method === 'card') {
        return ($amount * LENCO_CARD_FEE_PERCENTAGE) / 100;
    }
    return 0;
}

/**
 * Generate a unique payment reference
 * 
 * @param string $prefix 
 * @return string 
 */
function generateLencoReference($prefix = 'WDP') {
    return $prefix . '-' . date('Ymd') . '-' . uniqid();
}

/**
 *  Zambian phone number for mobile money
 * 
 * @param string $phoneNumber 
 * @return bool 
 */
function validateLencoPhoneNumber($phoneNumber) {

    $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // Check for valid Zambian format (10 digits starting with 09)
    if (strlen($cleaned) === 10 && substr($cleaned, 0, 2) === '09') {
        return true;
    }
    
    // Check for format without leading 0
    if (strlen($cleaned) === 9 && in_array(substr($cleaned, 0, 1), ['7', '6', '5', '9'])) {
        return true;
    }
    
    return false;
}

/**
 * Get mobile operator from phone number
 * 
 * @param string $phoneNumber Phone number
 * @return string|null Operator name or null
 */
function getLencoMobileOperator($phoneNumber) {
    $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // Normalize to 9 digits
    if (strlen($cleaned) === 10 && substr($cleaned, 0, 1) === '0') {
        $cleaned = substr($cleaned, 1);
    }
    
    // First two digits after country code
    $prefix = substr($cleaned, 0, 2);
    
    // MTN Zambia prefixes
    if (in_array($prefix, ['96', '76'])) {
        return 'mtn';
    }
    
    // Airtel Zambia prefixes
    if (in_array($prefix, ['97', '77'])) {
        return 'airtel';
    }
    
    // Zamtel prefixes
    if (in_array($prefix, ['95', '55'])) {
        return 'zamtel';
    }
    
    return null;
}

/**
 * Mobile Money Test Accounts for Sandbox
 * 
 * MTN:
 * - 0961111111 - Successful
 * - 0962222222 - Failed (Not enough funds)
 * - 0963333333 - Failed (Limit exceeded)
 * - 0964444444 - Failed (Unauthorized)
 * - 0966666666 - Failed (Timeout)
 * 
 * Airtel (ZM):
 * - 0971111111 - Successful
 * - 0972222222 - Failed (Incorrect Pin)
 * - 0975555555 - Failed (Not enough funds)
 * - 0977777777 - Failed (Timeout)
 * 
 * Test Cards:
 * - Visa: 4622 9431 2701 3705, CVV: 838, Expiry: Any future date
 * - Visa: 4622 9431 2701 3747, CVV: 370, Expiry: Any future date
 * - Mastercard: 5555 5555 5555 4444, CVV: Any 3 digits, Expiry: Any future date
 */
function getLencoTestAccounts() {
    return [
        'mobile_money' => [
            'mtn' => [
                ['phone' => '0961111111', 'response' => 'Successful'],
                ['phone' => '0962222222', 'response' => 'Failed - Not enough funds'],
                ['phone' => '0963333333', 'response' => 'Failed - Limit exceeded'],
            ],
            'airtel' => [
                ['phone' => '0971111111', 'response' => 'Successful'],
                ['phone' => '0972222222', 'response' => 'Failed - Incorrect Pin'],
                ['phone' => '0975555555', 'response' => 'Failed - Not enough funds'],
            ]
        ],
        'card' => [
            ['type' => 'Visa', 'number' => '4622 9431 2701 3705', 'cvv' => '838', 'expiry' => 'Any future date'],
            ['type' => 'Visa', 'number' => '4622 9431 2701 3747', 'cvv' => '370', 'expiry' => 'Any future date'],
            ['type' => 'Mastercard', 'number' => '5555 5555 5555 4444', 'cvv' => 'Any 3 digits', 'expiry' => 'Any future date'],
        ]
    ];
}

?>
