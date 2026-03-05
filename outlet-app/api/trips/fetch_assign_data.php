<?php
ob_start();
require_once __DIR__ . '/../../includes/session_manager.php';
initSession();

try {
    require_once '../../includes/OutletAwareSupabaseHelper.php';
} catch (Exception $e) {
    ob_end_clean();
    header("Content-Type: application/json; charset=utf-8");
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Server configuration error"]);
    exit;
}

ob_end_clean();

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

try {
    $supabase = new OutletAwareSupabaseHelper();

    $vehicles = $supabase->get('vehicle', 'deleted_at=is.null&order=name.asc', 'id,name,plate_number,status') ?: [];
    $drivers = $supabase->get('drivers', 'deleted_at=is.null&order=driver_name.asc', 'id,driver_name,driver_phone,status') ?: [];

    echo json_encode([
        'success'  => true,
        'vehicles' => array_values($vehicles),
        'drivers'  => array_values($drivers),
    ]);

} catch (Exception $e) {
    error_log("fetch_assign_data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
