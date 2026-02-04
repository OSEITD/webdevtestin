<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

function generateQRCode($data, $size = 200) {
    $qrData = urlencode($data);
    return "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$qrData}";
}

function generateBarcode($data, $width = 2, $height = 100) {
    $barcodeData = urlencode($data);
    return "https://barcode.tec-it.com/barcode.ashx?data={$barcodeData}&code=Code128&translate-esc=true&unit=Fit&dpi=96&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth={$width}";
}

function saveImageFromUrl($url, $savePath) {
    try {
        $imageContent = file_get_contents($url);
        if ($imageContent !== false) {
            file_put_contents($savePath, $imageContent);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Failed to save image: " . $e->getMessage());
        return false;
    }
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['tracking_number'])) {
        throw new Exception('Tracking number is required');
    }

    $trackingNumber = $input['tracking_number'];
    $parcelId = $input['parcel_id'] ?? $trackingNumber;
    
    $qrData = json_encode([
        'tracking_number' => $trackingNumber,
        'parcel_id' => $parcelId,
        'type' => 'parcel_tracking',
        'generated_at' => date('c')
    ]);

    $qrCodeUrl = generateQRCode($qrData);
    $barcodeUrl = generateBarcode($trackingNumber);
    
    $uploadsDir = '../assets/uploads/codes/';
    if (!file_exists($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    $qrCodePath = $uploadsDir . $trackingNumber . '_qr.gif';
    $qrCodeSaved = saveImageFromUrl($qrCodeUrl, $qrCodePath);
    
    $barcodePath = $uploadsDir . $trackingNumber . '_barcode.gif';
    $barcodeSaved = saveImageFromUrl($barcodeUrl, $barcodePath);
    
    $response = [
        'success' => true,
        'tracking_number' => $trackingNumber,
        'qr_code' => [
            'url' => $qrCodeUrl,
            'local_path' => $qrCodeSaved ? './assets/uploads/codes/' . basename($qrCodePath) : null,
            'data' => $qrData
        ],
        'barcode' => [
            'url' => $barcodeUrl,
            'local_path' => $barcodeSaved ? './assets/uploads/codes/' . basename($barcodePath) : null
        ]
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Generate codes error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'success' => false
    ]);
}
?>
