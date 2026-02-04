<?php

error_reporting(0);
ini_set('display_errors', 0);

ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/notification_helper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['outlet_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$track_number = $input['barcode'] ?? $input['track_number'] ?? null;
$new_status = $input['action'] ?? $input['status'] ?? null;

$statusMap = [
    'check-in' => 'at_outlet',
    'check-out' => 'In Transit',
    'check_in' => 'at_outlet',
    'check_out' => 'In Transit',
    'mark-delivered' => 'delivered',
    'mark_delivered' => 'delivered',
    'pending' => 'pending'
];

if (!$track_number || !$new_status) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters: barcode and action']);
    exit;
}

$mapped_status = $statusMap[strtolower($new_status)] ?? $new_status;

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$verifyUrl = "$supabaseUrl/rest/v1/parcels?track_number=eq.$track_number&company_id=eq." . $_SESSION['company_id'] . "&select=id";

$ch = curl_init($verifyUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ],
]);

$verifyResponse = curl_exec($ch);
$verifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$verifyData = json_decode($verifyResponse, true);
if (empty($verifyData)) {
    http_response_code(403);
    echo json_encode(['error' => 'Parcel not found or does not belong to your company']);
    exit;
}

$parcelUrl = "$supabaseUrl/rest/v1/parcels?track_number=eq.$track_number&company_id=eq." . $_SESSION['company_id'] . "&select=*";

$ch = curl_init($parcelUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ],
]);

$parcelResponse = curl_exec($ch);
curl_close($ch);

$parcelData = json_decode($parcelResponse, true);
$oldStatus = !empty($parcelData) ? $parcelData[0]['status'] : 'Unknown';

$updateUrl = "$supabaseUrl/rest/v1/parcels?track_number=eq.$track_number";

$payload = json_encode([
    'status' => $mapped_status,
    'updated_at' => date('c') 
]);

$ch = curl_init($updateUrl);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => "PATCH",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ],
    CURLOPT_POSTFIELDS => $payload,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

if ($http_code >= 200 && $http_code < 300) {
    $data = json_decode($response, true);
    
    
    if (!empty($data)) {
        $parcel = $data[0]; 
        
        
        try {
            error_log("Creating notification for parcel {$parcel['track_number']} status change: {$oldStatus} -> {$mapped_status}");
            $notificationHelper = new NotificationHelper();
            $result = $notificationHelper->createParcelStatusNotification($parcel, $oldStatus, $mapped_status);
            error_log("Notification creation result: " . json_encode($result));
        } catch (Exception $e) {
            
            error_log("Notification creation failed: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true, 
        'data' => $data,
        'broadcast' => $broadcastData ?? null,
        'old_status' => $oldStatus,
        'new_status' => $mapped_status
    ]);
} else {
    http_response_code($http_code);
    echo json_encode([
        'error' => 'Failed to update parcel status',
        'http_code' => $http_code,
        'curl_error' => $curl_error,
        'response' => $response
    ]);
}

curl_close($ch);

function createStatusChangeNotification($track_number, $old_status, $new_status, $parcel) {
    try {
        $notificationHelper = new NotificationHelper();
        
        
        $parcelData = [
            'id' => $parcel['id'],
            'track_number' => $track_number,
            'company_id' => $_SESSION['company_id'],
            'origin_outlet_id' => $_SESSION['outlet_id'],
            'sender_id' => $parcel['sender_id'] ?? null,
            'sender_name' => $parcel['sender_name'] ?? 'Unknown',
            'receiver_name' => $parcel['receiver_name'] ?? 'Unknown',
            'parcel_weight' => $parcel['parcel_weight'] ?? 0,
            'status' => $new_status
        ];
        
        
        if (!empty($parcel['sender_id'])) {
            $notificationHelper->createParcelStatusNotification(
                $parcelData, 
                $old_status, 
                $new_status, 
                $parcel['sender_id']
            );
        }
        
        
        if (!empty($parcel['receiver_id']) && in_array($new_status, ['Out for Delivery', 'Delivered'])) {
            $notificationHelper->createParcelStatusNotification(
                $parcelData, 
                $old_status, 
                $new_status, 
                $parcel['receiver_id']
            );
        }
        
        
        $currentUserId = $_SESSION['user_id'] ?? null;
        if ($currentUserId) {
            $notificationHelper->createParcelStatusNotification(
                $parcelData, 
                $old_status, 
                $new_status, 
                $currentUserId
            );
        }
        
    } catch (Exception $e) {
        
        error_log("Failed to create status change notification: " . $e->getMessage());
    }
}

function createNotificationRecord($user_id, $company_id, $type, $title, $message, $priority) {
    global $supabaseUrl, $supabaseKey;
    
    $notification = [
        'user_id' => $user_id,
        'company_id' => $company_id,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'priority' => $priority,
        'is_read' => false,
        'created_at' => date('Y-m-d\TH:i:s.u\Z')
    ];
    
    $url = "$supabaseUrl/rest/v1/notifications";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ],
        CURLOPT_POSTFIELDS => json_encode($notification),
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code < 200 || $http_code >= 300) {
        throw new Exception("Failed to create notification: HTTP $http_code");
    }
}
?>
