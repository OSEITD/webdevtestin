<?php

// ───  environment variables ──────────────────────────────────────────────
if (!class_exists('EnvLoader')) {
    require_once __DIR__ . '/../../includes/env.php';
}
EnvLoader::load();


$_lencoEnv = getenv('LENCO_ENV') ?: 'live';
define('LENCO_ENV', $_lencoEnv);

// ─── Live / Production Credentials ───────────────────────────────────────────
define('LENCO_PUBLIC_KEY_LIVE',  getenv('LENCO_PUBLIC_KEY_LIVE')  ?: '');
define('LENCO_SECRET_KEY_LIVE',  getenv('LENCO_SECRET_KEY_LIVE')  ?: '');

// ─── Sandbox / Test Credentials ──────────────────────────────────────────────
define('LENCO_PUBLIC_KEY_SANDBOX', getenv('LENCO_PUBLIC_KEY_SANDBOX') ?: '');
define('LENCO_SECRET_KEY_SANDBOX', getenv('LENCO_SECRET_KEY_SANDBOX') ?: '');

// ─── API Base URLs ───────────────────────────────────────────────────────────
define('LENCO_LIVE_BASE_URL', 'https://api.lenco.co/access/v2');
define('LENCO_SANDBOX_BASE_URL', 'https://sandbox.lenco.co/access/v2');

// ─── Widget Script URLs ─────────────────────────────────────────────────────
define('LENCO_WIDGET_LIVE_URL', 'https://pay.lenco.co/js/v1/inline.js');
define('LENCO_WIDGET_SANDBOX_URL', 'https://pay.sandbox.lenco.co/js/v1/inline.js');

// ─── Currency ────────────────────────────────────────────────────────────────
define('LENCO_CURRENCY', 'ZMW');

// ─── Fee Configuration ──────────────────────────────────────────────────────
define('LENCO_MOBILE_MONEY_FEE_PERCENTAGE', 2.5);
define('LENCO_CARD_FEE_PERCENTAGE', 2.9);

// ─── Payment Channels & Statuses ────────────────────────────────────────────
define('LENCO_PAYMENT_CHANNELS', ['card', 'mobile-money']);
define('LENCO_STATUS_SUCCESSFUL', 'successful');
define('LENCO_STATUS_PENDING', 'pending');
define('LENCO_STATUS_FAILED', 'failed');

// ─── Security: Rate-limit window (seconds) & max requests per window ────────
define('LENCO_RATE_LIMIT_WINDOW', 60);
define('LENCO_RATE_LIMIT_MAX', 10);

function getLencoPublicKey() {
    return LENCO_ENV === 'live' ? LENCO_PUBLIC_KEY_LIVE : LENCO_PUBLIC_KEY_SANDBOX;
}


function getLencoSecretKey() {
    return LENCO_ENV === 'live' ? LENCO_SECRET_KEY_LIVE : LENCO_SECRET_KEY_SANDBOX;
}

/** API base URL */
function getLencoBaseUrl() {
    return LENCO_ENV === 'live' ? LENCO_LIVE_BASE_URL : LENCO_SANDBOX_BASE_URL;
}

/** Widget JS URL */
function getLencoWidgetUrl() {
    return LENCO_ENV === 'live' ? LENCO_WIDGET_LIVE_URL : LENCO_WIDGET_SANDBOX_URL;
}

// Calculating transaction fee
 
function calculateLencoTransactionFee($amount, $method = 'mobile-money') {
    if ($method === 'mobile-money') {
        return ($amount * LENCO_MOBILE_MONEY_FEE_PERCENTAGE) / 100;
    } elseif ($method === 'card') {
        return ($amount * LENCO_CARD_FEE_PERCENTAGE) / 100;
    }
    return 0;
}

//Generatting a unique, unpredictable payment reference
 
function generateLencoReference($prefix = 'WDP') {
    return $prefix . '-' . date('Ymd') . '-' . bin2hex(random_bytes(8));
}

// Zambian phone number for mobile money
function validateLencoPhoneNumber($phoneNumber) {
    $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

    if (strlen($cleaned) === 10 && substr($cleaned, 0, 2) === '09') {
        return true;
    }
    if (strlen($cleaned) === 9 && in_array(substr($cleaned, 0, 1), ['7', '6', '5', '9'])) {
        return true;
    }
    return false;
}


function getLencoMobileOperator($phoneNumber) {
    $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

    if (strlen($cleaned) === 10 && substr($cleaned, 0, 1) === '0') {
        $cleaned = substr($cleaned, 1);
    }

    $prefix = substr($cleaned, 0, 2);

    if (in_array($prefix, ['96', '76'])) return 'mtn';
    if (in_array($prefix, ['97', '77'])) return 'airtel';
    if (in_array($prefix, ['95', '55'])) return 'zamtel';

    return null;
}

 
function lencoRateLimitCheck($action = 'payment') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = md5($ip . $action);
    $file = sys_get_temp_dir() . '/lenco_rl_' . $key . '.json';

    $window = LENCO_RATE_LIMIT_WINDOW;
    $max = LENCO_RATE_LIMIT_MAX;
    $now = time();

    $data = ['requests' => [], 'blocked_until' => 0];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: $data;
    }

   
    if ($data['blocked_until'] > $now) {
        return false;
    }

   
    $data['requests'] = array_values(array_filter($data['requests'], fn($t) => $t > ($now - $window)));

    if (count($data['requests']) >= $max) {
        $data['blocked_until'] = $now + $window;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return false;
    }

    $data['requests'][] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

/**
 * Validatining HMAC signature from Lenco webhook (if header is present).
 * Lenco sends X-Lenco-Signature with HMAC-SHA512 of raw body using secret key.
 */
function validateLencoWebhookSignature($rawBody) {
    $signature = $_SERVER['HTTP_X_LENCO_SIGNATURE'] ?? '';
    if (empty($signature)) {
       
        error_log('Lenco Webhook: No X-Lenco-Signature header present');
        return LENCO_ENV !== 'live'; 
    }

    $expectedSignature = hash_hmac('sha512', $rawBody, getLencoSecretKey());
    return hash_equals($expectedSignature, $signature);
}

?>
