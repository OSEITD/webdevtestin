<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
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

if (!isset($_GET['parcel_list_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing parcel_list_id parameter"]);
    exit;
}

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    $parcelListId = $_GET['parcel_list_id'];
    $outletId = $_SESSION['outlet_id'];

    
    $parcelListRows = $supabase->get('parcel_list', "id=eq.$parcelListId", '*');
    if (empty($parcelListRows)) {
        throw new Exception("Parcel list not found");
    }

    
    $parcelIds = array_column($parcelListRows, 'parcel_id');
    if (empty($parcelIds)) {
        echo json_encode(["success" => true, "parcels" => []]);
        exit;
    }

    
    $parcelIdStr = implode(',', $parcelIds); 
    $parcels = $supabase->get('parcels', "id=in.($parcelIdStr)", '*');

    
    $filteredParcels = [];
    foreach ($parcels as $parcel) {
        if (
            $parcel['origin_outlet_id'] === $outletId ||
            $parcel['destination_outlet_id'] === $outletId ||
            $parcel['status'] === 'at_outlet'
        ) {
            $filteredParcels[] = $parcel;
        }
    }

    echo json_encode([
        "success" => true,
        "parcels" => $filteredParcels
    ]);
} catch (Exception $e) {
    http_response_code(500);
    
    error_log('fetch_parcels_by_list.php error: ' . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}
?>
