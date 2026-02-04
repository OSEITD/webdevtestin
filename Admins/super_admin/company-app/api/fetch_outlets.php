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

    // Build Supabase request
    $url = $supabase->getUrl() . '/rest/v1/outlets';
    $url .= '?select=id,company_id,outlet_name,address,contact_person,contact_email,contact_phone,status';
    $url .= '&company_id=eq.' . $companyId; // FIX: filter by company_id
    $url .= '&order=created_at.desc';

    $headers = [
        'apikey: ' . $supabase->getKey(),
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    // Debug request details
    error_log("Fetching outlets for company_id={$companyId}");
    error_log("Request URL: " . $url);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // Gate SSL verification by APP_ENV: enforce in production, allow bypass in development
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (getenv('APP_ENV') ?: 'production') === 'production');
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, (getenv('APP_ENV') ?: 'production') === 'production' ? 2 : 0);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        throw new Exception('Failed to fetch outlets: ' . curl_error($ch));
    }

    curl_close($ch);

    // Debug response
    error_log("Supabase Response Code: " . $httpCode);
    error_log("Supabase Response: " . $response);

    if ($httpCode === 401) {
        if (strpos($response, 'JWT expired') !== false) {
            session_destroy();
            throw new Exception('Session expired. Please log in again.', 401);
        }
    }

    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        $errorMessage = isset($error['message']) ? $error['message'] : 'Unknown error';
        throw new Exception('Failed to fetch outlets: ' . $errorMessage, $httpCode);
    }

    $outlets = json_decode($response, true);
    if (!is_array($outlets)) {
        error_log("Invalid response format: " . $response);
        throw new Exception('Invalid response format from Supabase');
    }

    if (empty($outlets)) {
        error_log("No outlets found for company_id={$companyId}");
    } else {
        error_log("Fetched outlets: " . print_r($outlets, true));
    }

    $result = [
        'success' => true,
        'data' => array_map(function ($outlet) {
            return [
                'id' => $outlet['id'],
                'company_id' => $outlet['company_id'],
                'outlet_name' => $outlet['outlet_name'],
                'address' => $outlet['address'],
                'contact_person' => $outlet['contact_person'],
                'contact_email' => $outlet['contact_email'],
                'contact_phone' => $outlet['contact_phone'],
                'status' => $outlet['status'] ?? 'inactive'
            ];
        }, $outlets)
    ];

    echo json_encode($result);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to encode response: ' . json_last_error_msg());
    }
} catch (Exception $e) {
    ErrorHandler::handleException($e, 'fetch_outlets.php');
}
