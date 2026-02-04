<?php

if (ob_get_level()) {
    ob_end_clean();
}

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

@session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['test'])) {
    jsonResponse(["success" => true, "message" => "API is working", "timestamp" => date('Y-m-d H:i:s')]);
}

try {
    
    require_once __DIR__ . '/../../includes/MultiTenantSupabaseHelper.php';

    
    $required = ['companyId', 'originOutletId', 'senderName', 'senderPhone', 'recipientName', 'recipientPhone', 'parcelWeight', 'deliveryOption', 'destinationOutletId'];

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(["success" => false, "error" => "Missing required field: $field", "received_fields" => array_keys($_POST)], 400);
        }
    }

    
    $supabase = new MultiTenantSupabaseHelper($_POST['companyId']);

    
    $originOutlet = $supabase->get('outlets', "id=eq.{$_POST['originOutletId']}", 'id,company_id');
    if (empty($originOutlet)) {
        jsonResponse(["success" => false, "error" => "Origin outlet not found"], 400);
    }
    
    
    $destinationOutletId = $_POST['destinationOutletId'] ?? null;
    if (empty($destinationOutletId)) {
        jsonResponse(["success" => false, "error" => "Destination outlet is required"], 400);
    }
    
    $destinationOutlet = $supabase->get('outlets', "id=eq.$destinationOutletId", 'id,company_id');
    if (empty($destinationOutlet)) {
        jsonResponse(["success" => false, "error" => "Destination outlet not found"], 400);
    }

    
    $senderId = null;
    $receiverId = null;
    
    
    if (!empty($_POST['senderName'])) {
        try {
            $senderData = [
                'full_name' => $_POST['senderName'],
                'phone' => $_POST['senderPhone'] ?? null,
                'email' => $_POST['senderEmail'] ?? null,
                'nrc' => $_POST['senderNRC'] ?? null
            ];
            
            
            $existingSender = null;
            if (!empty($_POST['senderNRC'])) {
                error_log("Looking up sender by NRC in global_customers: " . $_POST['senderNRC']);
                $existing = $supabase->getGlobal('global_customers', "nrc=eq.{$_POST['senderNRC']}", 'id');
                error_log("Sender lookup result from global_customers: " . json_encode($existing));
                if (!empty($existing)) {
                    $existingSender = $existing[0];
                    error_log("Found existing sender in global_customers: " . $existingSender['id']);
                }
            } elseif (!empty($_POST['senderPhone'])) {
                error_log("Looking up sender by phone in global_customers: " . $_POST['senderPhone']);
                $existing = $supabase->getGlobal('global_customers', "phone=eq.{$_POST['senderPhone']}", 'id');
                error_log("Sender phone lookup result from global_customers: " . json_encode($existing));
                if (!empty($existing)) {
                    $existingSender = $existing[0];
                    error_log("Found existing sender by phone in global_customers: " . $existingSender['id']);
                }
            }
            
            if ($existingSender) {
                $senderId = $existingSender['id'];
            } else {
                
                $senderId = sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                
                $customerData = [
                    'id' => $senderId,
                    'full_name' => $_POST['senderName'],
                    'phone' => $_POST['senderPhone'] ?? null,
                    'email' => $_POST['senderEmail'] ?? null,
                    'nrc' => $_POST['senderNRC'] ?? null,
                    'Address' => $_POST['senderAddress'] ?? null
                ];
                
                error_log("Creating new sender customer with data: " . json_encode($customerData));
                $result = $supabase->postGlobal('global_customers', $customerData);
                
                
                if (!$result) {
                    error_log("postGlobal returned empty result for sender customer");
                    throw new Exception("Failed to create sender customer record");
                }
                error_log("Sender customer created successfully with ID: " . $senderId);
            }
        } catch (Exception $e) {
            error_log("Failed to register sender in global_customers: " . $e->getMessage());
            jsonResponse(["success" => false, "error" => "Failed to register sender customer"], 500);
        }
    }
    
    
    if (!empty($_POST['recipientName'])) {
        try {
            $receiverData = [
                'full_name' => $_POST['recipientName'],
                'phone' => $_POST['recipientPhone'] ?? null,
                'email' => $_POST['recipientEmail'] ?? null,
                'nrc' => $_POST['recipientNRC'] ?? null
            ];
            
            
            $existingReceiver = null;
            if (!empty($_POST['recipientNRC'])) {
                $existing = $supabase->getGlobal('global_customers', "nrc=eq.{$_POST['recipientNRC']}", 'id');
                if (!empty($existing)) {
                    $existingReceiver = $existing[0];
                }
            } elseif (!empty($_POST['recipientPhone'])) {
                $existing = $supabase->getGlobal('global_customers', "phone=eq.{$_POST['recipientPhone']}", 'id');
                if (!empty($existing)) {
                    $existingReceiver = $existing[0];
                }
            }
            
            if ($existingReceiver) {
                $receiverId = $existingReceiver['id'];
            } else {
                
                $receiverId = sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                
                $customerData = [
                    'id' => $receiverId,
                    'full_name' => $_POST['recipientName'],
                    'phone' => $_POST['recipientPhone'] ?? null,
                    'email' => $_POST['recipientEmail'] ?? null,
                    'nrc' => $_POST['recipientNRC'] ?? null,
                    'Address' => $_POST['recipientAddress'] ?? null
                ];
                
                $result = $supabase->postGlobal('global_customers', $customerData);
                
                
                if (!$result) {
                    throw new Exception("Failed to create receiver customer record");
                }
            }
        } catch (Exception $e) {
            error_log("Failed to register receiver in global_customers: " . $e->getMessage());
            jsonResponse(["success" => false, "error" => "Failed to register receiver customer"], 500);
        }
    }
    
    
    if (empty($senderId)) {
        jsonResponse(["success" => false, "error" => "Sender information is required and could not be processed"], 400);
    }
    
    if (empty($receiverId)) {
        jsonResponse(["success" => false, "error" => "Receiver information is required and could not be processed"], 400);
    }

    
    $parcelId = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    
    $trackingNumber = 'PKG' . date('Ymd') . strtoupper(substr(uniqid('', true), -8));
    
    
    $maxRetries = 5;
    $retry = 0;
    
    while ($retry < $maxRetries) {
        try {
            
            $existingParcel = $supabase->get('parcels', "track_number=eq.$trackingNumber", 'id');
            
            if (empty($existingParcel)) {
                break; 
            }
            
            
            $trackingNumber = 'PKG' . date('Ymd') . strtoupper(substr(uniqid('', true), -8));
            $retry++;
            
        } catch (Exception $e) {
            
            break;
        }
    }
    
    if ($retry >= $maxRetries) {
        jsonResponse(["success" => false, "error" => "Unable to generate unique tracking number"], 500);
    }

    
    $parsedDimensions = null;
    if (!empty($_POST['dimensions'])) {
        $dimStr = trim($_POST['dimensions']);
        
        $jsonDims = json_decode($dimStr, true);
        if ($jsonDims && isset($jsonDims['L'], $jsonDims['W'], $jsonDims['H'])) {
            $parsedDimensions = $jsonDims;
        } else {
            
            $dims = preg_split('/[x,×\s]+/', $dimStr);
            $dims = array_map('trim', $dims);
            $dims = array_filter($dims, function($d) { return is_numeric($d); });
            if (count($dims) >= 3) {
                $parsedDimensions = [
                    'L' => (float)$dims[0],
                    'W' => (float)$dims[1], 
                    'H' => (float)$dims[2]
                ];
            }
        }
    }

    
    $parcelData = [
        'id' => $parcelId,
        'track_number' => $trackingNumber,
        'company_id' => $_POST['companyId'],
        'origin_outlet_id' => $_POST['originOutletId'],
        'destination_outlet_id' => $destinationOutletId,
        
        
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'global_sender_id' => $senderId,  
        'global_receiver_id' => $receiverId,
        
        
        'sender_name' => $_POST['senderName'],
        'sender_email' => $_POST['senderEmail'] ?? null,
        'sender_phone' => $_POST['senderPhone'],
        'sender_address' => $_POST['senderAddress'] ?? null,
        'nrc' => $_POST['senderNRC'] ?? null,
        
        
        'receiver_name' => $_POST['recipientName'],
        'receiver_phone' => $_POST['recipientPhone'],
        'receiver_address' => $_POST['recipientAddress'] ?? null,
        
        
        'package_details' => $_POST['itemDescription'] ?? null,
        'parcel_weight' => (float)$_POST['parcelWeight'],
        'delivery_option' => $_POST['deliveryOption'],
        'declared_value' => isset($_POST['declaredValue']) ? (float)$_POST['declaredValue'] : 0,
        'delivery_fee' => isset($_POST['deliveryFee']) ? (float)$_POST['deliveryFee'] : 0,
        'insurance_amount' => isset($_POST['insuranceAmount']) ? (float)$_POST['insuranceAmount'] : 0,
        'cod_amount' => isset($_POST['codAmount']) ? (float)$_POST['codAmount'] : 0,
        'special_instructions' => $_POST['specialInstructions'] ?? null,
        
        
        'parcel_length' => $parsedDimensions ? $parsedDimensions['L'] : null,
        'parcel_width' => $parsedDimensions ? $parsedDimensions['W'] : null,
        'parcel_height' => $parsedDimensions ? $parsedDimensions['H'] : null,
        
        
        'status' => 'pending',
        'payment_status' => 'pending',
        '_skip_tracking_check' => true 
    ];

    
    $photoUrls = null;
    if (!empty($_FILES['parcelPhotos'])) {
        require_once __DIR__ . '/supabase_storage_helper.php';
        $storage = new SupabaseStorageHelper();
        $photoArray = [];
        $photoCount = is_array($_FILES['parcelPhotos']['name']) ? count($_FILES['parcelPhotos']['name']) : 1;
        
        error_log("Photo upload: found $photoCount photos");
        
        for ($i = 0; $i < $photoCount; $i++) {
            $fileName = $_FILES['parcelPhotos']['name'][$i];
            $tmpName = $_FILES['parcelPhotos']['tmp_name'][$i];
            
            
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $baseFileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
            $uniqueFileName = date('Y/m/d') . '/' . uniqid() . '_' . $baseFileName . '.' . $extension;
            
            error_log("Uploading photo: $fileName as $uniqueFileName");
            
            $uploadResult = $storage->upload('parcel-photos', $uniqueFileName, $tmpName);
            if ($uploadResult['success']) {
                $photoArray[] = $uploadResult['publicUrl'];
                error_log("Photo uploaded successfully: " . $uploadResult['publicUrl']);
            } else {
                error_log("Photo upload failed: " . ($uploadResult['error'] ?? 'Unknown error'));
            }
        }
        $photoUrls = $photoArray;
    }
    if ($photoUrls !== null) {
        $parcelData['photo_urls'] = $photoUrls;
    }

    
    

    
    try {
        error_log("Attempting to create parcel with data: " . json_encode($parcelData));
        
        $parcelResult = $supabase->createParcel($parcelData);
        
        if (!$parcelResult) {
            error_log("Parcel creation returned no result");
            jsonResponse(["success" => false, "error" => "Failed to create parcel record"], 500);
        }
        
        error_log("Parcel creation successful: " . json_encode($parcelResult));
        
    } catch (Exception $e) {
        
        error_log("Parcel creation error: " . $e->getMessage());
        error_log("Parcel data: " . json_encode($parcelData));
        error_log("Error trace: " . $e->getTraceAsString());
        
        
        $errorMessage = $e->getMessage();
        if (strpos($errorMessage, 'ON CONFLICT') !== false) {
            $errorMessage = "Parcel registration failed due to data conflict. Please try again or contact support.";
        } else if (strpos($errorMessage, 'foreign key') !== false) {
            $errorMessage = "Invalid outlet reference. Please check your outlet selection and try again.";
        } else if (strpos($errorMessage, 'constraint') !== false) {
            $errorMessage = "Data validation failed. Please check all fields and try again.";
        }
        
        jsonResponse([
            "success" => false, 
            "error" => $errorMessage,
            "debug" => [
                "original_error" => $e->getMessage(),
                "tracking_number" => $trackingNumber,
                "parcel_id" => $parcelId
            ]
        ], 500);
    }

    
    if (!empty($_POST['tripId']) && $_POST['tripId'] !== 'test-trip-id' && $_POST['tripId'] !== 'no-trip') {
        
        try {
            $tripStops = $supabase->get('trip_stops', "trip_id=eq.{$_POST['tripId']}&outlet_id=eq.{$_POST['originOutletId']}", 'id,stop_order');
            
            if (!empty($tripStops)) {
                $tripStopId = $tripStops[0]['id'];
                
                $parcelListId = sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );

                $parcelListData = [
                    'id' => $parcelListId,
                    'parcel_id' => $parcelId,
                    'trip_id' => $_POST['tripId'],
                    'outlet_id' => $_POST['originOutletId'],
                    'trip_stop_id' => $tripStopId,
                    'status' => 'pending'
                    
                    
                ];

                $parcelListResult = $supabase->createParcelListAssignment($parcelListData);
                error_log("Parcel list assignment successful for parcel: $parcelId with trip_stop: $tripStopId");
                
            } else {
                error_log("No trip stops found for trip: {$_POST['tripId']} and outlet: {$_POST['originOutletId']}");
                $parcelListResult = null; 
            }
            
        } catch (Exception $e) {
            
            error_log("Failed to create parcel_list entry for parcel: $parcelId - " . $e->getMessage());
            $parcelListResult = null;
        }
    } else {
        
        error_log("Parcel created without trip assignment - trip_id: " . ($_POST['tripId'] ?? 'none'));
        $parcelListResult = null;
    }

    
    $qrData = json_encode([
        'tracking_number' => $trackingNumber,
        'parcel_id' => $parcelId,
        'trip_id' => $_POST['tripId'],
        'type' => 'parcel_tracking',
        'generated_at' => date('c')
    ]);

    
    $qrCodeUrl = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($qrData);
    $barcodeUrl = "https://barcode.tec-it.com/barcode.ashx?data=" . urlencode($trackingNumber) . "&code=Code128&translate-esc=true&unit=Fit&dpi=96&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=2";

    
    $updateResult = $supabase->put("parcels?id=eq.$parcelId", ['barcode_url' => $barcodeUrl]);

    
    $paymentResult = null;
    if (!empty($_POST['deliveryFee']) || !empty($_POST['insuranceAmount']) || !empty($_POST['codAmount'])) {
        $totalAmount = (float)($_POST['deliveryFee'] ?? 0) + (float)($_POST['insuranceAmount'] ?? 0) + (float)($_POST['codAmount'] ?? 0);
        
        if ($totalAmount > 0) {
            $paymentId = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $paymentData = [
                'id' => $paymentId,
                'parcel_id' => $parcelId,
                'amount' => $totalAmount,
                'method' => $_POST['paymentMethod'] ?? 'cash',
                'status' => 'pending'
                
                
            ];

            try {
                $paymentResult = $supabase->createPayment($paymentData);
                error_log("Payment record created successfully for parcel: $parcelId, amount: $totalAmount");
            } catch (Exception $e) {
                error_log("Failed to create payment record for parcel: $parcelId - " . $e->getMessage());
                
                $paymentResult = null;
            }
        }
    }

    if ($parcelResult) {
        $responseData = [
            "success" => true,
            "tracking_number" => $trackingNumber,
            "parcel_id" => $parcelId,
            "parcel_status" => "pending",
            "message" => "Parcel registered successfully"
        ];
        
        
        if (!empty($_POST['tripId']) && $_POST['tripId'] !== 'no-trip') {
            $responseData["trip_id"] = $_POST['tripId'];
            $responseData["trip_assignment"] = $parcelListResult ? "success" : "failed";
            
            if (!$parcelListResult) {
                $responseData["warning"] = "Parcel created but could not be assigned to trip. Please assign manually.";
            } else {
                $responseData["parcel_list_id"] = $parcelListData['id'] ?? null;
            }
        }
        
        
        if (!empty($photoUrls)) {
            $responseData["uploaded_photos"] = count($photoUrls);
            $responseData["photo_urls"] = $photoUrls;
        }
        
        
        $responseData["payment_record"] = $paymentResult ? "created" : "not_needed";
        
        
        $responseData["codes"] = [
            "qr_code_url" => $qrCodeUrl,
            "barcode_url" => $barcodeUrl
        ];
        
        jsonResponse($responseData);
    } else {
        jsonResponse(["success" => false, "error" => "Failed to create parcel in database"], 500);
    }

} catch (Exception $e) {
    jsonResponse(["success" => false, "error" => "Server error: " . $e->getMessage()], 500);
}
