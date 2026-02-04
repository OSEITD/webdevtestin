<?php
session_start();
require_once '../../includes/MultiTenantSupabaseHelper.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id']) || !isset($_SESSION['outlet_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    $outletId = $_SESSION['outlet_id'];

    
    $parcelLists = $supabase->get('parcel_list', "outlet_id=eq.$outletId", '*');

    echo json_encode([
        "success" => true,
        "parcel_lists" => $parcelLists
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
