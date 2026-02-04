<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed. Use GET."]);
    exit;
}

try {
    require_once __DIR__ . '/../../includes/supabase-helper.php';

    if (!class_exists('SupabaseHelper')) {
        echo json_encode([
            'success' => false, 
            'error' => 'SupabaseHelper class not found.'
        ]);
        exit;
    }

    
    $trackingNumber = $_GET['tracking_number'] ?? null;
    $parcelId = $_GET['parcel_id'] ?? null;

    if (!$trackingNumber && !$parcelId) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Either tracking_number or parcel_id is required"
        ]);
        exit;
    }

    $supabaseHelper = new SupabaseHelper();

    
    $query = '';
    if ($parcelId) {
        $query = "id=eq.$parcelId";
    } else {
        $query = "track_number=eq.$trackingNumber";
    }

    
    $parcels = $supabaseHelper->get('parcels', $query);

    if (empty($parcels)) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Parcel not found"
        ]);
        exit;
    }

    $parcel = $parcels[0];
    $barcodeUrl = $parcel['barcode_url'] ?? null;

    echo json_encode([
        "success" => true,
        "parcel_id" => $parcel['id'],
        "tracking_number" => $parcel['track_number'],
        "barcode_url" => $barcodeUrl,
        "has_barcode" => $barcodeUrl !== null,
        "parcel_details" => [
            "sender_name" => $parcel['sender_name'],
            "receiver_name" => $parcel['receiver_name'],
            "status" => $parcel['status'],
            "created_at" => $parcel['created_at']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in barcode view API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Internal server error: " . $e->getMessage()
    ]);
}
?>
