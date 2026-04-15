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
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalhost = in_array($host, ['localhost', '127.0.0.1']);
    $cookieParams = [
        'lifetime' => 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !$isLocalhost
    ];

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

    $companyId = $_SESSION['id'] ?? $_SESSION['company_id'] ?? null;
    $accessToken = $_SESSION['access_token'] ?? null;

    if (!$companyId) {
        throw new Exception('Company ID not found in session', 401);
    }

    $supabase = new SupabaseClient();

    $filters = "company_id=eq.{$companyId}&deleted_at=is.null&order=created_at.desc&select=id,driver_name,driver_email,driver_phone,license_number,status";
    $urlPath = "drivers?{$filters}";

    $response = $supabase->getWithToken($urlPath, $accessToken);

    if (is_array($response) && isset($response['error'])) {
        throw new Exception('Failed to fetch drivers: ' . $response['error']);
    }

    $drivers = (is_array($response) && !isset($response['error']) && !isset($response['message'])) ? $response : [];
    if (isset($drivers['data']) && is_array($drivers['data'])) {
        $drivers = $drivers['data'];
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