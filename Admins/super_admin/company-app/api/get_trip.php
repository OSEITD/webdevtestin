<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/supabase-client.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['id']) || !isset($_SESSION['access_token'])) {
        throw new Exception('Not authenticated', 401);
    }

    $companyId = $_SESSION['id'] ?? $_SESSION['company_id'] ?? null;
    $accessToken = $_SESSION['access_token'];

    $tripId = $_GET['id'] ?? null;
    if (!$tripId) {
        throw new Exception('Missing trip id', 400);
    }

    $supabase = new SupabaseClient();

    $select = '*,stops:trip_stops(*),parcels:parcel_list(*,parcel:parcels(*)),driver:drivers(*),vehicle:vehicle(*)';
    $queryParams = [
        'select' => $select,
        'id' => "eq.{$tripId}",
        'company_id' => "eq.{$companyId}"
    ];
    $endpoint = 'trips?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

    try {
        $result = $supabase->getWithToken($endpoint, $accessToken);
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, "Could not find a relationship") !== false) {
            // Retry with simpler select that does not attempt nested parcel relation
            $simpleSelect = '*,stops:trip_stops(*),parcels:parcel_list(*),driver:drivers(*),vehicle:vehicle(*)';
            $queryParams['select'] = $simpleSelect;
            $simpleEndpoint = 'trips?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
            error_log("get_trip.php: retrying with simplified select due to schema mismatch: {$simpleEndpoint}");
            $result = $supabase->getWithToken($simpleEndpoint, $accessToken);
        } else {
            throw $e;
        }
    }
    if ($result === null) throw new Exception('Empty response from Supabase');

    $trips = $result;
    if (is_object($result) && isset($result->data)) $trips = $result->data;

    if (!is_array($trips) || count($trips) === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Trip not found', 'trip' => null]);
        exit;
    }

    echo json_encode(['success' => true, 'trip' => $trips[0]]);
    exit;

} catch (Exception $e) {
    $code = $e->getCode() >= 100 && $e->getCode() <= 599 ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

?>
