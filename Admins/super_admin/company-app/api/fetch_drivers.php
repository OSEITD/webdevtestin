<?php
// Disable error display
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

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

    // Debug session data
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }

    // Validate session status
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new Exception('Session is not active', 500);
    }

    // Get company ID from session
    if (!isset($_SESSION['id'])) {
        throw new Exception('Company ID not found in session', 401);
    }
    if (!isset($_SESSION['access_token'])) {
        throw new Exception('Access token not found in session', 401);
    }

    $companyId = $_SESSION['id'];
    $accessToken = $_SESSION['access_token'];
    $refreshToken = $_SESSION['refresh_token'] ?? null;
    
    if (!$companyId || !$accessToken) {
        throw new Exception('Not authenticated', 401);
    }

    // Initialize Supabase client
    $supabase = new SupabaseClient();
    
    // Function to refresh token
    function refreshToken($supabase, $refreshToken) {
        $refreshUrl = $supabase->getUrl() . '/auth/v1/token?grant_type=refresh_token';
        $headers = [
            'apikey: ' . $supabase->getKey(),
            'Content-Type: application/json'
        ];
        
        $ch = curl_init($refreshUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['refresh_token' => $refreshToken]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['access_token'])) {
                $_SESSION['access_token'] = $result['access_token'];
                return $result['access_token'];
            }
        }
        return null;
    }
    
    // Try to refresh token if refresh_token exists
    if ($refreshToken) {
        error_log("Attempting to refresh token");
        $newAccessToken = refreshToken($supabase, $refreshToken);
        if ($newAccessToken) {
            error_log("Token refreshed successfully");
            $accessToken = $newAccessToken;
        }
    }

    // Create request URL
    $url = $supabase->getUrl() . '/rest/v1/drivers';
    $url .= '?select=id,driver_name,driver_email,driver_phone,license_number,status';
    $url .= '&company_id=eq.' . $companyId;
    $url .= '&order=created_at.desc';

    // Debug request details
    error_log("Request URL: " . $url);
    error_log("Request headers: " . print_r($headers, true));

    // Make the request
    $headers = [
        'apikey: ' . $supabase->getKey(),
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // Gate SSL verification by APP_ENV: enforce in production, allow bypass in development
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (getenv('APP_ENV') ?: 'production') === 'production');
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, (getenv('APP_ENV') ?: 'production') === 'production' ? 2 : 0);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        throw new Exception('Failed to fetch drivers: ' . curl_error($ch));
    }
    
    curl_close($ch);

    // Debug response
    error_log("Supabase Response Code: " . $httpCode);
    error_log("Supabase Response: " . $response);

    if ($httpCode === 401) {
        // Token might be expired even after refresh attempt
        if (strpos($response, 'JWT expired') !== false) {
            // Clear session and force re-login
            session_destroy();
            throw new Exception('Session expired. Please log in again.', 401);
        }
    }
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        $errorMessage = isset($error['message']) ? $error['message'] : 'Unknown error';
        throw new Exception('Failed to fetch drivers: ' . $errorMessage, $httpCode);
    }

    $drivers = json_decode($response, true);
    if (!is_array($drivers)) {
        throw new Exception('Invalid response format from Supabase');
    }

    $result = [
        'success' => true,
        'data' => $drivers
    ];
    
    echo json_encode($result);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to encode response');
    }

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'fetch_drivers.php');
}
?>