<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed. Use POST."]);
    exit;
}

try {
    
    require_once __DIR__ . '/../../includes/supabase-helper.php';
    require_once __DIR__ . '/../../includes/barcode_generator.php';

    
    if (!class_exists('SupabaseHelper')) {
        echo json_encode([
            'success' => false, 
            'error' => 'SupabaseHelper class not found.'
        ]);
        exit;
    }

    if (!class_exists('BarcodeGenerator')) {
        echo json_encode([
            'success' => false, 
            'error' => 'BarcodeGenerator class not found.'
        ]);
        exit;
    }

    
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Invalid JSON input"
        ]);
        exit;
    }

    
    $trackingNumber = $input['tracking_number'] ?? null;
    $parcelId = $input['parcel_id'] ?? null;
    $regenerate = $input['regenerate'] ?? false;

    if (!$trackingNumber && !$parcelId) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Either tracking_number or parcel_id is required"
        ]);
        exit;
    }

    
    $supabaseHelper = new SupabaseHelper();
    $barcodeGenerator = new BarcodeGenerator($supabaseHelper);

    
    if ($parcelId && !$trackingNumber) {
        $parcels = $supabaseHelper->get('parcels', "id=eq.$parcelId");
        if (empty($parcels)) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "Parcel not found"
            ]);
            exit;
        }
        $trackingNumber = $parcels[0]['track_number'];
        $existingBarcodeUrl = $parcels[0]['barcode_url'] ?? null;
    } else if ($trackingNumber && !$parcelId) {
        
        $parcels = $supabaseHelper->get('parcels', "track_number=eq.$trackingNumber");
        if (empty($parcels)) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "Parcel with tracking number not found"
            ]);
            exit;
        }
        $parcelId = $parcels[0]['id'];
        $existingBarcodeUrl = $parcels[0]['barcode_url'] ?? null;
    } else {
        
        $parcels = $supabaseHelper->get('parcels', "id=eq.$parcelId");
        $existingBarcodeUrl = !empty($parcels) ? ($parcels[0]['barcode_url'] ?? null) : null;
    }

    
    if ($existingBarcodeUrl && !$regenerate) {
        echo json_encode([
            "success" => true,
            "barcode_url" => $existingBarcodeUrl,
            "tracking_number" => $trackingNumber,
            "parcel_id" => $parcelId,
            "message" => "Barcode already exists. Use regenerate=true to create a new one.",
            "regenerated" => false
        ]);
        exit;
    }

    
    $barcodeOptions = $input['barcode_options'] ?? [];
    $barcodeResult = $barcodeGenerator->generateBarcode($trackingNumber, 'Code128', $barcodeOptions);

    if (!$barcodeResult['success']) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to generate barcode: " . ($barcodeResult['error'] ?? 'Unknown error')
        ]);
        exit;
    }

    $barcodeUrl = $barcodeResult['barcode_url'];

    
    $updateData = ['barcode_url' => $barcodeUrl];
    
    try {
        $supabaseHelper->put('parcels', $updateData, "id=eq.$parcelId");
    } catch (Exception $e) {
        
        echo json_encode([
            "success" => false,
            "error" => "Barcode generated successfully but failed to update parcel record",
            "barcode_url" => $barcodeUrl,
            "tracking_number" => $trackingNumber,
            "parcel_id" => $parcelId
        ]);
        exit;
    }

    
    echo json_encode([
        "success" => true,
        "barcode_url" => $barcodeUrl,
        "tracking_number" => $trackingNumber,
        "parcel_id" => $parcelId,
        "method" => $barcodeResult['method'] ?? 'unknown',
        "message" => "Barcode generated and saved successfully",
        "regenerated" => $regenerate || ($existingBarcodeUrl !== null),
        "previous_url" => $existingBarcodeUrl
    ]);

} catch (Exception $e) {
    error_log("Error in barcode generation API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Internal server error: " . $e->getMessage()
    ]);
}
?>
