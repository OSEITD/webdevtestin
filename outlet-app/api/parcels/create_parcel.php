<?php
require_once __DIR__ . '/../../includes/security_headers.php';
SecurityHeaders::apply();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
session_start();

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

require_once __DIR__ . '/../../includes/notification_helper.php';
require_once __DIR__ . '/../../includes/sms_service.php';
require_once __DIR__ . '/../../includes/EnhancedParcelDeliveryManager.php';
require_once __DIR__ . '/../../includes/barcode_generator.php';
require_once __DIR__ . '/../../includes/supabase-helper.php';

function getBasicCustomerInfo($supabase, $companyId, $name, $phone, $email, $address) {
    
    $filters = ["company_id=eq.$companyId"];
    
    if (!empty($phone)) {
        $filters[] = "phone=eq." . urlencode($phone);
    }
    if (!empty($email)) {
        $filters[] = "email=ilike." . urlencode(strtolower($email));
    }
    
    $query = implode('&', $filters);
    try {
        $existing = $supabase->get('customers', $query);
        if (!empty($existing)) {
            return [
                'customer_id' => $existing[0]['id'],
                'type' => 'registered',
                'was_created' => false
            ];
        }
    } catch (Exception $e) {
        error_log("Customer search failed: " . $e->getMessage());
    }
    
    return [
        'customer_id' => null,
        'type' => 'guest',
        'was_created' => false
    ];
}

function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function uploadPhotoToSupabaseStorage($file, $bucket = 'parcel-photos') {
    
    require_once __DIR__ . '/../../config/supabase_config.php';
    
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        error_log("Photo upload failed: File error " . ($file['error'] ?? 'unknown'));
        return null;
    }

    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        error_log("Photo upload failed: Invalid file type " . $file['type']);
        return null;
    }

    
    if ($file['size'] > 50 * 1024 * 1024) {
        error_log("Photo upload failed: File too large " . $file['size'] . " bytes");
        return null;
    }

    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = date('Y/m/d') . '/' . uniqid('parcel_') . '.' . $ext;
    
    
    $fileContent = file_get_contents($file['tmp_name']);
    if ($fileContent === false) {
        error_log("Photo upload failed: Cannot read file content");
        return null;
    }
    
    
    $url = SUPABASE_URL . "/storage/v1/object/$bucket/$filename";
    
    $headers = [
        "Authorization: Bearer " . SUPABASE_SERVICE_ROLE_KEY,
        "Content-Type: " . $file['type']
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        error_log("Photo upload failed: HTTP $httpCode - $response");
    }
    
    if ($httpCode === 200 || $httpCode === 201) {
        $publicUrl = SUPABASE_URL . "/storage/v1/object/public/$bucket/$filename";
        error_log("Photo uploaded successfully: $publicUrl");
        return $publicUrl;
    }
    
    return null;
}

$input = $_POST;

if (empty($input['originOutletId'])) {
    
    if (!empty($_SESSION['outlet_id'])) {
        $input['originOutletId'] = $_SESSION['outlet_id'];
        error_log('DEBUG: originOutletId retrieved from session: ' . $_SESSION['outlet_id']);
    } 
    
    elseif (!empty($_SESSION['user_id'])) {
        try {
            require_once __DIR__ . '/../../includes/supabase-helper.php';
            $supabaseHelper = new SupabaseHelper();
            $userProfile = $supabaseHelper->get('profiles', 'id=eq.' . urlencode($_SESSION['user_id']));
            
            if (!empty($userProfile) && !empty($userProfile[0]['outlet_id'])) {
                $input['originOutletId'] = $userProfile[0]['outlet_id'];
                
                $_SESSION['outlet_id'] = $userProfile[0]['outlet_id'];
                error_log('DEBUG: originOutletId retrieved from user profile: ' . $userProfile[0]['outlet_id']);
            }
        } catch (Exception $e) {
            error_log('DEBUG: Failed to retrieve outlet_id from profile: ' . $e->getMessage());
        }
    }
}

if (empty($input['companyId'])) {
    
    if (!empty($_SESSION['company_id'])) {
        $input['companyId'] = $_SESSION['company_id'];
        error_log('DEBUG: companyId retrieved from session: ' . $_SESSION['company_id']);
    }
    
    elseif (!empty($_SESSION['user_id'])) {
        try {
            if (!isset($supabaseHelper)) {
                require_once __DIR__ . '/../../includes/supabase-helper.php';
                $supabaseHelper = new SupabaseHelper();
            }
            if (!isset($userProfile)) {
                $userProfile = $supabaseHelper->get('profiles', 'id=eq.' . urlencode($_SESSION['user_id']));
            }
            
            if (!empty($userProfile) && !empty($userProfile[0]['company_id'])) {
                $input['companyId'] = $userProfile[0]['company_id'];
                
                $_SESSION['company_id'] = $userProfile[0]['company_id'];
                error_log('DEBUG: companyId retrieved from user profile: ' . $userProfile[0]['company_id']);
            }
        } catch (Exception $e) {
            error_log('DEBUG: Failed to retrieve company_id from profile: ' . $e->getMessage());
        }
    }
}

$required = [
    'companyId', 'originOutletId', 'senderName', 'senderPhone', 'senderAddress',
    'recipientName', 'recipientPhone', 'recipientAddress', 'parcelWeight', 'deliveryOption'
];

$missing = [];
foreach ($required as $field) {
    if (empty($input[$field])) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode([
        "success" => false, 
        "error" => "Missing required fields", 
        "fields" => $missing,
        "debug_info" => [
            "session_user_id" => $_SESSION['user_id'] ?? null,
            "session_outlet_id" => $_SESSION['outlet_id'] ?? null,
            "session_company_id" => $_SESSION['company_id'] ?? null,
            "received_fields" => array_keys($input)
        ]
    ]);
    exit;
}

if (empty($input['companyId']) && empty($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false, 
        "error" => "Authentication required. Missing company information."
    ]);
    exit;
}

function validateCompanyOwnership($supabaseHelper, $companyId, $entityType, $entityId) {
    try {
        if ($entityType === 'outlet') {
            $entity = $supabaseHelper->get('outlets', 'id=eq.' . urlencode($entityId));
            $entityCompanyId = $entity[0]['company_id'] ?? null;
        } elseif ($entityType === 'trip') {
            $entity = $supabaseHelper->get('trips', 'id=eq.' . urlencode($entityId));
            $entityCompanyId = $entity[0]['company_id'] ?? null;
        } else {
            return ['valid' => false, 'error' => 'Invalid entity type for validation'];
        }

        if ($entityCompanyId === null) {
            return ['valid' => false, 'error' => ucfirst($entityType) . ' not found'];
        }

        if ($entityCompanyId !== $companyId) {
            error_log("COMPANY MISMATCH: $entityType $entityId belongs to company $entityCompanyId, but parcel is for company $companyId");
            return [
                'valid' => false,
                'error' => ucfirst($entityType) . ' does not belong to the specified company',
                'details' => [
                    'entity_id' => $entityId,
                    'entity_company_id' => $entityCompanyId,
                    'parcel_company_id' => $companyId
                ]
            ];
        }

        return ['valid' => true];
    } catch (Exception $e) {
        error_log("Error validating $entityType company ownership: " . $e->getMessage());
        return ['valid' => false, 'error' => "Failed to validate $entityType company ownership"];
    }
}

try {
    $supabaseHelper = new SupabaseHelper();

    
    $originValidation = validateCompanyOwnership($supabaseHelper, $input['companyId'], 'outlet', $input['originOutletId']);
    if (!$originValidation['valid']) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Company validation failed for origin outlet",
            "details" => $originValidation['error'],
            "validation_details" => $originValidation['details'] ?? null
        ]);
        exit;
    }

    
    if (!empty($input['destinationOutlet'])) {
        $destinationValidation = validateCompanyOwnership($supabaseHelper, $input['companyId'], 'outlet', $input['destinationOutlet']);
        if (!$destinationValidation['valid']) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Company validation failed for destination outlet",
                "details" => $destinationValidation['error'],
                "validation_details" => $destinationValidation['details'] ?? null
            ]);
            exit;
        }
    }

    
    if (!empty($input['tripId'])) {
        $tripValidation = validateCompanyOwnership($supabaseHelper, $input['companyId'], 'trip', $input['tripId']);
        if (!$tripValidation['valid']) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Company validation failed for trip",
                "details" => $tripValidation['error'],
                "validation_details" => $tripValidation['details'] ?? null
            ]);
            exit;
        }
    }
    
    $senderNrc = trim($input['senderNRC'] ?? '');
    $senderEmail = !empty($input['senderEmail']) ? strtolower(trim($input['senderEmail'])) : null;
    $senderPhone = trim($input['senderPhone'] ?? '');
    $senderId = null;
    
    try {
        
        $searchFilters = [];
        if (!empty($senderNrc)) {
            $searchFilters[] = 'nrc=eq.' . urlencode($senderNrc);
        }
        if (!empty($senderEmail)) {
            $searchFilters[] = 'email=eq.' . urlencode($senderEmail);
        }
        if (!empty($senderPhone)) {
            $searchFilters[] = 'phone=eq.' . urlencode($senderPhone);
        }
        
        
        if (!empty($searchFilters)) {
            foreach ($searchFilters as $filter) {
                try {
                    $existingSender = $supabaseHelper->get('global_customers', $filter);
                    if (!empty($existingSender) && isset($existingSender[0]['id'])) {
                        $senderId = $existingSender[0]['id'];
                        error_log("DEBUG: Found existing sender with ID: $senderId");
                        break;
                    }
                } catch (Exception $e) {
                    
                    error_log("DEBUG: Search filter failed: " . $e->getMessage());
                }
            }
        }
        
        
        if (empty($senderId)) {
            $newSenderData = [
                'full_name' => trim($input['senderName']),
                'phone' => !empty($senderPhone) ? $senderPhone : null,
                'address' => !empty($input['senderAddress']) ? trim($input['senderAddress']) : null
            ];
            
            
            if (!empty($senderNrc)) {
                $newSenderData['nrc'] = $senderNrc;
            }
            
            
            if (!empty($senderEmail)) {
                $newSenderData['email'] = $senderEmail;
            }
            
            error_log("DEBUG: Attempting to insert new sender: " . json_encode($newSenderData));
            
            try {
                $insertResult = $supabaseHelper->post('global_customers', $newSenderData);
                if (!empty($insertResult) && isset($insertResult[0]['id'])) {
                    $senderId = $insertResult[0]['id'];
                    error_log("DEBUG: Successfully created sender with ID: $senderId");
                } else {
                    error_log("ERROR: Sender insert returned unexpected result: " . json_encode($insertResult));
                    http_response_code(500);
                    echo json_encode([
                        "success" => false,
                        "error" => "Failed to register sender customer - unexpected response",
                        "details" => $insertResult,
                        "payload" => $newSenderData
                    ]);
                    exit;
                }
            } catch (Exception $e) {
                error_log("ERROR: Sender insert exception: " . $e->getMessage());
                
                
                if (strpos($e->getMessage(), 'duplicate key') !== false || 
                    strpos($e->getMessage(), 'unique constraint') !== false) {
                    
                    foreach ($searchFilters as $filter) {
                        try {
                            $existingSender = $supabaseHelper->get('global_customers', $filter);
                            if (!empty($existingSender) && isset($existingSender[0]['id'])) {
                                $senderId = $existingSender[0]['id'];
                                error_log("DEBUG: Found existing sender after unique constraint error: $senderId");
                                break;
                            }
                        } catch (Exception $e2) {
                            continue;
                        }
                    }
                }
                
                
                if (empty($senderId)) {
                    http_response_code(500);
                    echo json_encode([
                        "success" => false,
                        "error" => "Failed to register sender customer",
                        "details" => $e->getMessage(),
                        "payload" => $newSenderData
                    ]);
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        error_log("ERROR: Sender registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to process sender information",
            "details" => $e->getMessage()
        ]);
        exit;
    }

    
    $receiverNrc = trim($input['recipientNRC'] ?? '');
    $receiverEmail = !empty($input['recipientEmail']) ? strtolower(trim($input['recipientEmail'])) : null;
    $receiverPhone = trim($input['recipientPhone'] ?? '');
    $receiverId = null;
    
    try {
        
        $searchFilters = [];
        if (!empty($receiverNrc)) {
            $searchFilters[] = 'nrc=eq.' . urlencode($receiverNrc);
        }
        if (!empty($receiverEmail)) {
            $searchFilters[] = 'email=eq.' . urlencode($receiverEmail);
        }
        if (!empty($receiverPhone)) {
            $searchFilters[] = 'phone=eq.' . urlencode($receiverPhone);
        }
        
        
        if (!empty($searchFilters)) {
            foreach ($searchFilters as $filter) {
                try {
                    $existingReceiver = $supabaseHelper->get('global_customers', $filter);
                    if (!empty($existingReceiver) && isset($existingReceiver[0]['id'])) {
                        $receiverId = $existingReceiver[0]['id'];
                        error_log("DEBUG: Found existing receiver with ID: $receiverId");
                        break;
                    }
                } catch (Exception $e) {
                    
                    error_log("DEBUG: Search filter failed: " . $e->getMessage());
                }
            }
        }
        
        
        if (empty($receiverId)) {
            $newReceiverData = [
                'full_name' => trim($input['recipientName']),
                'phone' => !empty($receiverPhone) ? $receiverPhone : null,
                'address' => !empty($input['recipientAddress']) ? trim($input['recipientAddress']) : null
            ];
            
            
            if (!empty($receiverNrc)) {
                $newReceiverData['nrc'] = $receiverNrc;
            }
            
            
            if (!empty($receiverEmail)) {
                $newReceiverData['email'] = $receiverEmail;
            }
            
            error_log("DEBUG: Attempting to insert new receiver: " . json_encode($newReceiverData));
            
            try {
                $insertResult = $supabaseHelper->post('global_customers', $newReceiverData);
                if (!empty($insertResult) && isset($insertResult[0]['id'])) {
                    $receiverId = $insertResult[0]['id'];
                    error_log("DEBUG: Successfully created receiver with ID: $receiverId");
                } else {
                    error_log("ERROR: Receiver insert returned unexpected result: " . json_encode($insertResult));
                    http_response_code(500);
                    echo json_encode([
                        "success" => false,
                        "error" => "Failed to register receiver customer - unexpected response",
                        "details" => $insertResult,
                        "payload" => $newReceiverData
                    ]);
                    exit;
                }
            } catch (Exception $e) {
                error_log("ERROR: Receiver insert exception: " . $e->getMessage());
                
                
                if (strpos($e->getMessage(), 'duplicate key') !== false || 
                    strpos($e->getMessage(), 'unique constraint') !== false) {
                    
                    foreach ($searchFilters as $filter) {
                        try {
                            $existingReceiver = $supabaseHelper->get('global_customers', $filter);
                            if (!empty($existingReceiver) && isset($existingReceiver[0]['id'])) {
                                $receiverId = $existingReceiver[0]['id'];
                                error_log("DEBUG: Found existing receiver after unique constraint error: $receiverId");
                                break;
                            }
                        } catch (Exception $e2) {
                            continue;
                        }
                    }
                }
                
                
                if (empty($receiverId)) {
                    http_response_code(500);
                    echo json_encode([
                        "success" => false,
                        "error" => "Failed to register receiver customer",
                        "details" => $e->getMessage(),
                        "payload" => $newReceiverData
                    ]);
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        error_log("ERROR: Receiver registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to process receiver information",
            "details" => $e->getMessage()
        ]);
        exit;
    }

   
    $parcelManager = new EnhancedParcelDeliveryManager(true); 
    
    $photoUrls = [];
    if (isset($_FILES['parcelPhotos']) && is_array($_FILES['parcelPhotos']['name'])) {
        $fileCount = count($_FILES['parcelPhotos']['name']);
        for ($i = 0; $i < $fileCount && $i < 5; $i++) { 
            if ($_FILES['parcelPhotos']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['parcelPhotos']['name'][$i],
                    'type' => $_FILES['parcelPhotos']['type'][$i],
                    'tmp_name' => $_FILES['parcelPhotos']['tmp_name'][$i],
                    'error' => $_FILES['parcelPhotos']['error'][$i],
                    'size' => $_FILES['parcelPhotos']['size'][$i]
                ];
                $photoUrl = uploadPhotoToSupabaseStorage($file);
                if ($photoUrl) {
                    $photoUrls[] = $photoUrl;
                }
            }
        }
    }

    
    $microtime = str_replace('.', '', microtime(true));
    $trackingNumber = 'PKG' . strtoupper(substr(uniqid(), -5)) . substr($microtime, -6) . sprintf('%02d', rand(0, 99));

    
    $parcelData = [
        'track_number' => $trackingNumber,
        'company_id' => $input['companyId'],
        'origin_outlet_id' => $input['originOutletId'],
        'destination_outlet_id' => $input['destinationOutlet'] ?? null,
        'sender_name' => $input['senderName'],
        'sender_email' => $input['senderEmail'] ?? null,
        'sender_phone' => $input['senderPhone'],
        'sender_address' => $input['senderAddress'],
        'global_sender_id' => $senderId,
        'receiver_name' => $input['recipientName'],
        'receiver_address' => $input['recipientAddress'],
        'receiver_phone' => $input['recipientPhone'],
        'global_receiver_id' => $receiverId,
    'package_details' => $input['itemDescription'] ?? '',
    'parcel_weight' => (float)$input['parcelWeight'],
    'delivery_option' => $input['deliveryOption'],
    'declared_value' => isset($input['declaredValue']) ? (float)$input['declaredValue'] : 0,
    'parcel_value' => isset($input['declaredValue']) ? (float)$input['declaredValue'] : 0,
    'delivery_fee' => isset($input['deliveryFee']) ? (float)$input['deliveryFee'] : 0,
        'insurance_amount' => isset($input['insuranceAmount']) ? (float)$input['insuranceAmount'] : 0,
        'cod_amount' => isset($input['codAmount']) ? (float)$input['codAmount'] : 0,
        'special_instructions' => $input['specialInstructions'] ?? null,
        'photo_urls' => $photoUrls,
        
        'payment_status' => (isset($input['paymentMethod']) && in_array($input['paymentMethod'], ['cash', 'cod'])) ? 'paid' : 'pending',
    ];

    // Parse dimensions from JSON string (sent as {"L":x,"W":y,"H":z} from frontend)
    $parcelLength = null;
    $parcelWidth = null;
    $parcelHeight = null;
    if (!empty($input['dimensions'])) {
        $dimData = json_decode($input['dimensions'], true);
        if (is_array($dimData)) {
            $parcelLength = isset($dimData['L']) ? (float)$dimData['L'] : null;
            $parcelWidth = isset($dimData['W']) ? (float)$dimData['W'] : null;
            $parcelHeight = isset($dimData['H']) ? (float)$dimData['H'] : null;
        }
    }
    // Also check individual fields as fallback
    if ($parcelLength === null && isset($input['parcelLength'])) $parcelLength = (float)$input['parcelLength'];
    if ($parcelWidth === null && isset($input['parcelWidth'])) $parcelWidth = (float)$input['parcelWidth'];
    if ($parcelHeight === null && isset($input['parcelHeight'])) $parcelHeight = (float)$input['parcelHeight'];

    // validate that if any dimension was provided it is positive and numeric
    foreach (['Length' => $parcelLength, 'Width' => $parcelWidth, 'Height' => $parcelHeight] as $name => $val) {
        if ($val !== null && (!is_numeric($val) || $val <= 0)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "$name must be a positive number"]);
            exit;
        }
    }

    $parcelData['parcel_length'] = $parcelLength;
    $parcelData['parcel_width'] = $parcelWidth;
    $parcelData['parcel_height'] = $parcelHeight;
    $parcelData['estimated_delivery_date'] = $estimatedDeliveryDate ?? null;
    $parcelData['barcode_url'] = $barcodeUrl ?? null;
    $parcelData['nrc'] = $input['senderNRC'] ?? null;
    $parcelData['created_by'] = $_SESSION['user_id'] ?? null;
    $parcelData['driver_id'] = $input['driverId'] ?? null;
    $parcelData['status'] = 'pending';
    $parcelData['updated_at'] = date('c');

    
    try {
        $parcelResult = $parcelManager->createParcelWithDelivery($parcelData);
    } catch (Exception $e) {
        error_log('ERROR: Exception during parcel creation: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Parcel and delivery creation failed: " . $e->getMessage(),
            "payload" => $parcelData
        ]);
        exit;
    }
    if (!$parcelResult['success']) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Parcel and delivery creation failed: " . ($parcelResult['message'] ?? 'Unknown error'),
            "payload" => $parcelData,
            "result" => $parcelResult
        ]);
        exit;
    }
    
    
    $parcelId = $parcelResult['parcel']['id'] ?? null;
    $deliveryEventId = $parcelResult['delivery']['id'] ?? null;
    
    if (!$parcelId || !$deliveryEventId) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to get parcel ID or delivery event ID from creation result",
            "raw_result" => $parcelResult
        ]);
        exit;
    }

    
    $barcodeUrl = null;
    $barcodeError = null;
    try {
        $supabaseHelper = new SupabaseHelper();
        $barcodeGenerator = new BarcodeGenerator($supabaseHelper);
        $barcodeResult = $barcodeGenerator->generateBarcode($trackingNumber);
        
        if ($barcodeResult['success']) {
            $barcodeUrl = $barcodeResult['barcode_url'];
            
            
            $updateData = [['barcode_url' => $barcodeUrl]];
            try {
                $supabaseHelper->patch('parcels', $updateData, "id=eq.$parcelId");
                error_log("Barcode generated and saved successfully: $barcodeUrl");
            } catch (Exception $e) {
                error_log("Failed to update parcel with barcode URL: $barcodeUrl - " . $e->getMessage());
                $barcodeError = "Barcode generated but failed to save to database";
            }
        } else {
            $barcodeError = $barcodeResult['error'] ?? 'Unknown barcode generation error';
            error_log("Barcode generation failed: " . $barcodeError);
        }
    } catch (Exception $e) {
        $barcodeError = "Barcode generation exception: " . $e->getMessage();
        error_log($barcodeError);
    }

    
    $response = [
        "success" => true,
        "trackingNumber" => $trackingNumber ?? '',
        "track_number" => $trackingNumber ?? '',
        "parcel_id" => $parcelId,
        "delivery_id" => $deliveryEventId,
        "parcel_status" => "pending",
        "delivery_status" => "pending",
        "delivery_created" => true,
        "uploaded_photos" => count($photoUrls),
        "photo_urls" => $photoUrls,
        "barcode_url" => $barcodeUrl,
        "barcode_generated" => $barcodeUrl !== null,
        "trip_assigned" => false,
        "parcel" => [
            "id" => $parcelId,
            "track_number" => $trackingNumber ?? '',
        ],
        "message" => "Parcel and delivery records created successfully using Enhanced ParcelDeliveryManager.",
        "next_steps" => [
            "assign_driver" => "Use assignment tracking to assign driver and update delivery status",
            "track_parcel" => "Track parcel status using tracking number: " . ($trackingNumber ?? '')
        ],
        "business_flow" => "Both parcel and delivery records are now PENDING. Ready for driver assignment."
    ];

    
    $tripId = $input['tripId'] ?? null;
    require_once __DIR__ . '/../../includes/MultiTenantSupabaseHelper.php';
    $tripHelper = new MultiTenantSupabaseHelper($input['companyId']);
    $assignedTripId = null;
    $tripAssignmentError = null;
    if (!empty($tripId)) {
        
        $destinationOutletId = $input['destinationOutlet'] ?? $input['originOutletId'];
        
        
        $tripData = $tripHelper->get('trips', 'id=eq.' . urlencode($tripId));
        $tripStatus = $tripData[0]['trip_status'] ?? 'scheduled';
        
        
        $parcelStatus = 'assigned';
        $parcelListStatus = 'assigned';
        if ($tripStatus === 'in_transit') {
            $parcelStatus = 'in_transit';
            $parcelListStatus = 'in_transit';
        }
        
        
        $tripStops = $tripHelper->get('trip_stops', 'trip_id=eq.' . urlencode($tripId) . '&outlet_id=eq.' . urlencode($destinationOutletId));
        
        if (empty($tripStops)) {
            
            error_log("INFO: Trip $tripId does not include destination outlet $destinationOutletId - trip assignment skipped");
            
        } else {
            
            $tripStopId = $tripStops[0]['id'] ?? null;
            
            $assignmentData = [
                'parcel_id' => $parcelId,
                'trip_id' => $tripId,
                'outlet_id' => $input['originOutletId'], 
                'status' => $parcelListStatus,
                'company_id' => $input['companyId']
            ];
            
            
            if ($tripStopId) {
                $assignmentData['trip_stop_id'] = $tripStopId;
            }
            
            
            if (empty($assignmentData['parcel_id'])) {
                error_log("ERROR: Missing parcel_id for parcel_list creation");
                return;
            }
            if (empty($assignmentData['outlet_id'])) {
                error_log("ERROR: Missing outlet_id for parcel_list creation");
                return;
            }
            if (empty($assignmentData['company_id'])) {
                error_log("ERROR: Missing company_id for parcel_list creation");
                return;
            }
            
            try {
                error_log("DEBUG: Attempting to create parcel_list entry with data: " . json_encode($assignmentData));
                error_log("DEBUG: Trip status is: $tripStatus, Parcel status will be: $parcelStatus, ParcelList status will be: $parcelListStatus");
                
                
                $assignmentResult = $tripHelper->createParcelListAssignment($assignmentData);
                error_log("DEBUG: parcel_list creation result: " . ($assignmentResult ? 'SUCCESS' : 'FAILED'));
                
                if ($assignmentResult) {
                    error_log("DEBUG: parcel_list created successfully, result: " . json_encode($assignmentResult));
                    
                    
                    $parcelUpdateData = ['status' => $parcelStatus];
                    error_log("DEBUG: Updating parcel $parcelId with status: $parcelStatus");
                    try {
                        $tripHelper->put("parcels?id=eq.$parcelId", $parcelUpdateData);
                        error_log("DEBUG: Parcel $parcelId status successfully updated to $parcelStatus");
                        
                        
                        $response["parcel_status"] = $parcelStatus;
                    } catch (Exception $e) {
                        error_log("ERROR: Failed to update parcel status: " . $e->getMessage());
                    }
                    
                    $response["trip_assigned"] = true;
                    $response["trip_id"] = $tripId;
                    $response["message"] = "Parcel created successfully and assigned to trip with status: $parcelStatus";
                    error_log("Parcel $parcelId successfully assigned to trip $tripId at stop $tripStopId with status $parcelStatus");
                } else {
                    error_log("INFO: Failed to assign parcel $parcelId to trip $tripId - assignment skipped");
                    
                }
            } catch (Exception $e) {
                error_log("ERROR: Trip assignment exception for parcel $parcelId: " . $e->getMessage());
                error_log("DEBUG: Assignment data was: " . json_encode($assignmentData));
                
            }
        }
    }
    
    
    // Handle Payment Transaction Creation
    $paymentTransactionId = null;
    $paymentResult = null;
    
    try {
        // Create payment transactions for ALL payment methods
        $paymentMethod = $input['paymentMethod'] ?? '';
        $paymentProvider = $input['paymentProvider'] ?? $paymentMethod;
        
        require_once __DIR__ . '/../../includes/PaymentTransactionDB.php';
        $paymentDB = new PaymentTransactionDB();
        
        // Calculate total amount based on payment method
        $deliveryFee = (float)($input['deliveryFee'] ?? 0);
        $insuranceAmount = (float)($input['insuranceAmount'] ?? 0);
        $codAmount = (float)($input['codAmount'] ?? 0);
        $cashAmount = (float)($input['cashAmount'] ?? 0);
        
        // For cash payments at outlet, use the cash amount field
        // Fall back to deliveryFee + insurance if cashAmount wasn't entered
        if ($paymentMethod === 'cash') {
            $totalAmount = ($cashAmount > 0) ? $cashAmount : ($deliveryFee + $insuranceAmount);
        }
        // For COD, use delivery fee + insurance + cod amount
        elseif ($paymentMethod === 'cod') {
            $totalAmount = $deliveryFee + $insuranceAmount + $codAmount;
        }
        // For online payments (lenco), use delivery fee + insurance
        else {
            $totalAmount = $deliveryFee + $insuranceAmount;
        }
        
        // Determine company commission percentage (fetch from companies table)
        $companyCommissionPercent = 0;
        try {
            $companyIdForCommission = $input['companyId'] ?? null;
            if ($companyIdForCommission) {
                // Use the initialized Supabase helper instance
                if (!isset($supabaseHelper)) {
                    require_once __DIR__ . '/../../includes/supabase-helper.php';
                    $supabaseHelper = new SupabaseHelper();
                }
                $companyResp = $supabaseHelper->get('companies', 'id=eq.' . urlencode($companyIdForCommission) . '&select=commission_rate');
                error_log('DEBUG: Company commission lookup response: ' . json_encode($companyResp));
                if (!empty($companyResp) && isset($companyResp[0]['commission_rate'])) {
                    $companyCommissionPercent = floatval($companyResp[0]['commission_rate']);
                } else {
                    $companyCommissionPercent = floatval(getenv('DEFAULT_COMMISSION_RATE') ?: 0);
                }
            } else {
                $companyCommissionPercent = floatval(getenv('DEFAULT_COMMISSION_RATE') ?: 0);
            }
        } catch (Exception $e) {
            error_log('Failed to fetch company commission: ' . $e->getMessage());
            $companyCommissionPercent = floatval(getenv('DEFAULT_COMMISSION_RATE') ?: 0);
        }

        // Determine payment status based on method
        // Cash: Already paid at outlet -> 'successful'
        // COD: To be paid on delivery -> 'pending'
        // Online: Awaiting payment -> 'pending'
        $paymentStatus = ($paymentMethod === 'cash') ? 'successful' : 'pending';
        $paidAt = ($paymentMethod === 'cash') ? date('Y-m-d H:i:s') : null;

        // Calculate commission and net amounts
        $commissionAmount = round(($totalAmount * $companyCommissionPercent / 100), 2);
        $netAmount = round($totalAmount - $commissionAmount, 2);

        // Customer email is required by payment_transactions table — use a fallback
        $customerEmail = $input['senderEmail'] ?? '';
        if (empty($customerEmail)) {
            $customerEmail = 'noemail@outlet.local';
        }

        // Prepare payment transaction data
        $paymentData = [
            'tx_ref' => 'TXN-' . $trackingNumber . '-' . time(),
            'company_id' => $input['companyId'],
            'outlet_id' => $input['originOutletId'],
            'user_id' => $_SESSION['user_id'] ?? null,
            'parcel_id' => $parcelId,
            'amount' => $totalAmount,
            'transaction_fee' => 0,
            'commission_percentage' => $companyCommissionPercent,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'total_amount' => $totalAmount,
            'currency' => 'ZMW',
            'payment_method' => $paymentMethod,
            'payment_type' => $paymentProvider === 'lenco_mobile' ? 'mobile_money' : 
                            ($paymentProvider === 'lenco_card' ? 'card' : 
                            ($paymentMethod === 'cash' ? 'cash' : 
                            ($paymentMethod === 'cod' ? 'cod' : 'online'))),
            'customer_name' => $input['senderName'],
            'customer_email' => $customerEmail,
            'customer_phone' => $input['senderPhone'],
            'status' => $paymentStatus,
            'paid_at' => $paidAt,
            'metadata' => [
                'parcel_tracking_number' => $trackingNumber,
                'delivery_fee' => $deliveryFee,
                'insurance_amount' => $insuranceAmount,
                'cod_amount' => $codAmount,
                'cash_amount' => $cashAmount,
                'delivery_option' => $input['deliveryOption'] ?? 'standard',
                'payment_provider' => $paymentProvider,
                'commission_percentage' => $companyCommissionPercent,
                'commission_amount' => $commissionAmount,
                'net_amount' => $netAmount,
                'payment_note' => $paymentMethod === 'cash' ? 'Paid at outlet' : 
                                ($paymentMethod === 'cod' ? 'To be paid on delivery' : 'Online payment pending')
            ]
        ];
        
        // Add mobile money specific data
        if ($paymentProvider === 'lenco_mobile' || $paymentMethod === 'mobile_money') {
            $paymentData['mobile_network'] = $input['mobileProvider'] ?? null;
            $paymentData['mobile_number'] = $input['mobileNumber'] ?? null;
        }
        
        // Add card specific data (if available from payment processor)
        if ($paymentProvider === 'lenco_card' || $paymentMethod === 'card') {
            $paymentData['payment_type'] = 'card';
        }
        
        // Create payment transaction
        $paymentResult = $paymentDB->createTransaction($paymentData);
        
        if ($paymentResult['success']) {
            $paymentTransactionId = $paymentResult['data']['id'];
            $response['payment_transaction'] = [
                'id' => $paymentTransactionId,
                'tx_ref' => $paymentData['tx_ref'],
                'status' => $paymentStatus,
                'amount' => $totalAmount,
                'payment_method' => $paymentMethod
            ];
            error_log("Payment transaction created successfully: " . $paymentTransactionId . " with status: " . $paymentStatus);
        } else {
            error_log("Failed to create payment transaction: " . ($paymentResult['error'] ?? 'Unknown error'));
            // Don't fail the parcel creation, but log the issue
            $response['payment_warning'] = 'Payment transaction creation failed, but parcel was created successfully';
        }
        
    } catch (Exception $e) {
        error_log("Payment transaction creation error: " . $e->getMessage());
        // Don't fail parcel creation for payment issues
        $response['payment_error'] = 'Payment setup failed, but parcel was created successfully';
    }
    
    
    

    if ($barcodeError) {
        $response["barcode_error"] = $barcodeError;
        $response["warning"] = "Parcel created successfully but barcode generation had issues";
    }

    
    try {
        $notificationHelper = new NotificationHelper();
        
        
        $notificationParcelData = [
            'id' => $parcelId,
            'track_number' => $trackingNumber,
            'company_id' => $input['companyId'],
            'origin_outlet_id' => $input['originOutletId'],
            'sender_name' => $input['senderName'],
            'receiver_name' => $input['recipientName'],
            'parcel_weight' => (float)$input['parcelWeight'],
            'status' => 'pending'
        ];
        
        
        $currentUserId = $_SESSION['user_id'] ?? null;
        if ($currentUserId) {
            $notificationHelper->createParcelCreatedNotification($notificationParcelData, $currentUserId);
        }
        
    } catch (Exception $e) {
        
        error_log("Failed to create notification: " . $e->getMessage());
    }

    
    try {
        $smsService = new SMSService();
        $smsResults = [];
        
        
        if (!empty($input['senderPhone'])) {
            $senderResult = $smsService->notifySender(
                $input['senderPhone'],
                $trackingNumber,
                $input['recipientName']
            );
            $smsResults['sender'] = $senderResult;
            error_log("SMS to sender: " . json_encode($senderResult));
        }
        
        
        if (!empty($input['recipientPhone'])) {
            $receiverResult = $smsService->notifyReceiver(
                $input['recipientPhone'],
                $trackingNumber,
                $input['senderName']
            );
            $smsResults['receiver'] = $receiverResult;
            error_log("SMS to receiver: " . json_encode($receiverResult));
        }
        
        
        if (!empty($smsResults)) {
            $response['sms_notifications'] = $smsResults;
            if (!$smsService->isEnabled()) {
                $response['sms_status'] = 'SMS notifications will be sent when service is enabled';
            }
        }
        
    } catch (Exception $e) {
        
        error_log("Failed to send SMS notifications: " . $e->getMessage());
        $response['sms_error'] = 'SMS notification failed but parcel created successfully';
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('DEBUG: Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Parcel creation failed: " . $e->getMessage()
    ]);
}
?>