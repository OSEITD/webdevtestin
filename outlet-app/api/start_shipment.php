<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$requiredFields = ['parcel_id', 'company_id'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing required field: $field"]);
        exit;
    }
}

$supabaseHelperPath = __DIR__ . '/../includes/supabase-helper.php';
if (!file_exists($supabaseHelperPath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Supabase helper not found"]);
    exit;
}

require_once $supabaseHelperPath;

require_once __DIR__ . '/../includes/CustomerManager.php';

function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

try {
    $supabase = new SupabaseHelper();
    $customerManager = new CustomerManager($supabase);
    
    
    
    
    
    $parcel = $supabase->get('parcels', "id=eq.{$input['parcel_id']}");
    if (empty($parcel)) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Parcel not found"]);
        exit;
    }
    
    $parcelData = $parcel[0];
    error_log("Found parcel for shipment: " . json_encode($parcelData));
    
    
    $existingDelivery = $supabase->get('deliveries', "parcel_id=eq.{$input['parcel_id']}");
    if ($existingDelivery !== null && !empty($existingDelivery)) {
        http_response_code(400);
        echo json_encode([
            "success" => false, 
            "error" => "Delivery already exists for this parcel",
            "delivery_id" => $existingDelivery[0]['id']
        ]);
        exit;
    }
    
    
    
    
    
    
    $senderData = ['sender_id' => null];
    if (!empty($parcelData['sender_id'])) {
        
        $senderCustomer = $supabase->get('customers', "id=eq.{$parcelData['sender_id']}");
        if (!empty($senderCustomer)) {
            $senderData = ['sender_id' => $parcelData['sender_id']];
        }
    }
    
    
    $recipientData = [
        'recipient_id' => null,
        'recipient_type' => 'guest',
        'guest_recipient_id' => null,
        'business_recipient_id' => null
    ];
    
    if (!empty($parcelData['receiver_id'])) {
        
        try {
            $receiverResult = $customerManager->findOrCreateCustomer(
                $input['company_id'],
                $parcelData['receiver_name'],
                $parcelData['receiver_phone'],
                $parcelData['receiver_address'],
                null 
            );
            
            
            switch ($receiverResult['type']) {
                case 'registered':
                    $recipientData = [
                        'recipient_id' => $receiverResult['id'],
                        'recipient_type' => 'registered',
                        'guest_recipient_id' => null,
                        'business_recipient_id' => null
                    ];
                    break;
                    
                case 'guest':
                    $recipientData = [
                        'recipient_id' => null,
                        'recipient_type' => 'guest',
                        'guest_recipient_id' => $receiverResult['id'],
                        'business_recipient_id' => null
                    ];
                    break;
                    
                case 'business':
                    $recipientData = [
                        'recipient_id' => null,
                        'recipient_type' => 'business',
                        'guest_recipient_id' => null,
                        'business_recipient_id' => $receiverResult['id']
                    ];
                    break;
            }
        } catch (Exception $e) {
            error_log("Customer type determination failed, using guest: " . $e->getMessage());
            
        }
    }
    
    
    
    
    
    $deliveryId = generate_uuid();
    $currentDateTime = date('c');
    $estimatedDeliveryDate = date('c', strtotime('+3 days'));
    
    $deliveryData = array_merge([
        'id' => $deliveryId,
        'parcel_id' => $input['parcel_id'],
        'outlet_id' => $parcelData['origin_outlet_id'], 
        'company_id' => $input['company_id'],
        'tracking_number' => $parcelData['track_number'],
        
        
        'delivery_status' => 'pending',
        'pickup_date' => null, 
        'estimated_delivery_date' => $estimatedDeliveryDate,
        'delivery_date' => null, 
        'actual_delivery_date' => null, 
        
        
        'delivery_address' => $parcelData['receiver_address'],
        'delivery_fee' => $parcelData['delivery_fee'] ?? 0,
        'weight' => $parcelData['parcel_weight'],
        
        
        'driver_id' => isset($input['driver_id']) ? $input['driver_id'] : null,
        'delivery_notes' => isset($input['delivery_notes']) ? $input['delivery_notes'] : null,
        
        
        'created_at' => $currentDateTime,
        'updated_at' => $currentDateTime
    ], $senderData, $recipientData);
    
    error_log("Creating delivery record: " . json_encode($deliveryData));
    
    $deliveryResult = $supabase->post('deliveries', $deliveryData);
    
    
    $updateData = [
        'status' => 'in_process'
    ];
    $updateResult = $supabase->put('parcels', $updateData, "id=eq.{$input['parcel_id']}");
    
    
    echo json_encode([
        "success" => true,
        "message" => "Shipment process started successfully",
        "parcel_id" => $input['parcel_id'],
        "delivery_id" => $deliveryId,
        "tracking_number" => $parcelData['track_number'],
        "delivery_status" => "pending",
        "parcel_status" => "in_process",
        "estimated_delivery" => $estimatedDeliveryDate,
        "recipient_type" => $recipientData['recipient_type'],
        "driver_assigned" => isset($input['driver_id']) ? true : false,
        "next_steps" => [
            "assign_driver" => !isset($input['driver_id']) ? "Assign driver to begin pickup process" : "Driver assigned",
            "schedule_pickup" => "Schedule pickup date when ready",
            "track_delivery" => "Delivery tracking now active"
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Start shipment failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to start shipment process: " . $e->getMessage()
    ]);
}
?>
