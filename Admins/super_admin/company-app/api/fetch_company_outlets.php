<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

try {
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
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new Exception('Session is not active', 500);
    }

    $companyId = $_SESSION['id'] ?? $_SESSION['company_id'] ?? null;
    $accessToken = $_SESSION['access_token'] ?? null;

    if (!$companyId) {
        throw new Exception('Company ID not found in session', 401);
    }

    $supabase = new SupabaseClient();

    $filters = "company_id=eq.{$companyId}&deleted_at=is.null&order=created_at.desc&select=id,company_id,outlet_name,address,contact_person,contact_email,contact_phone,status";
    $urlPath = "outlets?{$filters}";

    $outlets = [];
    $tokenFailed = false;

    if (!empty($accessToken)) {
        $response = $supabase->getWithToken($urlPath, $accessToken);
        if (is_array($response) && isset($response['error'])) {
            $tokenFailed = true;
        } else {
            $outlets = (is_array($response) && !isset($response['message'])) ? $response : [];
            if (isset($outlets['data']) && is_array($outlets['data'])) {
                $outlets = $outlets['data'];
            }
        }
    }

    if (empty($accessToken) || $tokenFailed) {
        $serviceResult = $supabase->getRecord($urlPath, true);
        $outlets = is_object($serviceResult) && isset($serviceResult->data) ? $serviceResult->data : ($serviceResult ?? []);
    }

    if (!is_array($outlets)) {
        $outlets = [];
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
} catch (Exception $e) {
    ErrorHandler::handleException($e, 'fetch_company_outlets.php');
}
