<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';
$supabase = new SupabaseClient();

try {
    // Detect environment and set cookie params accordingly
    $isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    // Only set secure flag and domain in production
    if (!$isLocalhost) {
        $cookieParams['secure'] = true;
        $cookieParams['domain'] = '.' . $_SERVER['HTTP_HOST'];
    }

    session_set_cookie_params($cookieParams);
    session_start();

    // Debug session
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new Exception('Session is not active', 500);
    }

    // Validate session values
    if (!isset($_SESSION['id'])) {
        throw new Exception('Company ID not found in session', 401);
    }
    if (!isset($_SESSION['access_token'])) {
        throw new Exception('Access token not found in session', 401);
    }

    $companyId = $_SESSION['id']; // company id from companies table
    $accessToken = $_SESSION['access_token'];
    $refreshToken = $_SESSION['refresh_token'] ?? null;

    if (!$companyId || !$accessToken) {
        throw new Exception('Not authenticated', 401);
    }

    // Initialize Supabase client
    $supabase = new SupabaseClient();
    
    // Dump diagnostic info directly to our own log
    $debugInfo = "Supabase URL: " . ($supabase->getUrl() ?: 'EMPTY') . "\n";
    $debugInfo .= "EnvLoader exists: " . (class_exists('EnvLoader') ? 'YES' : 'NO') . "\n";
    $debugInfo .= "EnvLoader loaded flag: " . (property_exists('EnvLoader', 'loaded') ? 'Unknown' : 'Unknown') . "\n"; 
    $debugInfo .= "EnvLoader SUPABASE_URL: " . (class_exists('EnvLoader') ? (EnvLoader::get('SUPABASE_URL') ?: 'EMPTY') : 'N/A') . "\n";
    
    // Check if $_ENV or getenv has it natively without EnvLoader
    $debugInfo .= "getenv('SUPABASE_URL'): " . (getenv('SUPABASE_URL') ?: 'EMPTY') . "\n";
    $debugInfo .= '$_ENV["SUPABASE_URL"]: ' . ($_ENV['SUPABASE_URL'] ?? 'EMPTY') . "\n";
    
    file_put_contents(__DIR__ . '/debug_outlets.log', $debugInfo);

    $filters = "company_id=eq.{$companyId}&deleted_at=is.null&order=created_at.desc&select=id,company_id,outlet_name,address,contact_person,contact_email,contact_phone,status";
    $urlPath = "outlets?{$filters}";

    error_log("Fetching outlets via SupabaseClient for URL path: {$urlPath}");
    $response = $supabase->getWithToken($urlPath, $accessToken);

    if (is_array($response) && isset($response['error'])) {
        throw new Exception('Failed to fetch outlets: ' . $response['error']);
    }

    $outlets = (is_array($response) && !isset($response['error']) && !isset($response['message'])) ? $response : [];
    
    // Some endpoints wrap data in a 'data' array
    if (isset($outlets['data']) && is_array($outlets['data'])) {
        $outlets = $outlets['data'];
    }

    $result = [
        'success' => true,
        'data' => array_map(function ($outlet) {
            return [
                'id' => $outlet['id'] ?? null,
                'company_id' => $outlet['company_id'] ?? null,
                'outlet_name' => $outlet['outlet_name'] ?? null,
                'address' => $outlet['address'] ?? null,
                'contact_person' => $outlet['contact_person'] ?? null,
                'contact_email' => $outlet['contact_email'] ?? null,
                'contact_phone' => $outlet['contact_phone'] ?? null,
                'status' => $outlet['status'] ?? 'inactive'
            ];
        }, $outlets)
    ];

    echo json_encode($result);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to encode response: ' . json_last_error_msg());
    }
} catch (Throwable $e) {
    file_put_contents(__DIR__ . '/debug_outlets.log', "\n--- EXCEPTION ---\n" . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    ErrorHandler::handleException($e, 'fetch_outlets.php');
}
