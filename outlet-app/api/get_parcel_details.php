<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$parcelId = $_GET['id'] ?? null;
if (!$parcelId) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing required parameter: id"]);
    exit;
}

$companyId = $_SESSION['company_id'] ?? null;
if (!$companyId) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Company ID not found in session"]);
    exit;
}

require_once '../includes/MultiTenantSupabaseHelper.php';

try {
    $supabase = new MultiTenantSupabaseHelper($companyId);

    // Fetch parcel details
    $parcelFilter = "id=eq." . urlencode($parcelId) . "&company_id=eq." . urlencode($companyId);
    $parcelData = $supabase->get('parcels', $parcelFilter);

    if (empty($parcelData)) {
        echo json_encode(["success" => false, "error" => "Parcel not found"]);
        exit;
    }

    $parcel = $parcelData[0];

    // Fetch outlet information
    $outletIds = [];
    if (!empty($parcel['origin_outlet_id'])) {
        $outletIds[] = $parcel['origin_outlet_id'];
    }
    if (!empty($parcel['destination_outlet_id'])) {
        $outletIds[] = $parcel['destination_outlet_id'];
    }

    $outletsMap = [];
    if (!empty($outletIds)) {
        $outletsFilter = "id=in.(" . implode(',', array_map('urlencode', $outletIds)) . ")";
        $outletsData = $supabase->get('outlets', $outletsFilter);
        foreach ($outletsData as $outlet) {
            $outletsMap[$outlet['id']] = $outlet;
        }
    }

    // Add outlet names to parcel
    $parcel['origin_outlet_name'] = isset($outletsMap[$parcel['origin_outlet_id']]) ?
        $outletsMap[$parcel['origin_outlet_id']]['outlet_name'] : 'Unknown';
    $parcel['destination_outlet_name'] = isset($outletsMap[$parcel['destination_outlet_id']]) ?
        $outletsMap[$parcel['destination_outlet_id']]['outlet_name'] : 'Unknown';

    echo json_encode([
        "success" => true,
        "parcel" => $parcel
    ]);

} catch (Exception $e) {
    error_log("Error in get_parcel_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to fetch parcel details: " . $e->getMessage()
    ]);
}
?>