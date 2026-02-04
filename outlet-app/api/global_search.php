<?php

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../includes/auth_guard.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    auth_guard(); 
    $current_user = getCurrentUser();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required', 'message' => 'Please log in to access this resource']);
    exit();
}

$current_user_id = $current_user['user_id'];
$outlet_id = $current_user['outlet_id'];
$company_id = $_SESSION['company_id'] ?? null;

if (!$current_user_id || !$company_id) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

function makeSupabaseRequest($url, $method = 'GET', $data = null, $supabaseKey = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ],
        CURLOPT_CUSTOMREQUEST => $method
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'data' => json_decode($response, true),
        'status_code' => $http_code
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $query = trim($input['query'] ?? '');
    $types = $input['types'] ?? ['parcels', 'deliveries', 'customers', 'notifications'];
    $limit = intval($input['limit'] ?? 20);
    
    if (empty($query) || strlen($query) < 2) {
        throw new Exception('Query must be at least 2 characters long');
    }
    
    $results = [];
    
    
    if (in_array('parcels', $types)) {
        $parcelResults = searchParcels($query, $company_id, $outlet_id, $supabaseUrl, $supabaseKey);
        $results = array_merge($results, $parcelResults);
    }
    
    
    if (in_array('deliveries', $types)) {
        $deliveryResults = searchDeliveries($query, $company_id, $outlet_id, $supabaseUrl, $supabaseKey);
        $results = array_merge($results, $deliveryResults);
    }
    
    
    if (in_array('customers', $types)) {
        $customerResults = searchCustomers($query, $company_id, $supabaseUrl, $supabaseKey);
        $results = array_merge($results, $customerResults);
    }
    
    
    if (in_array('notifications', $types)) {
        $notificationResults = searchNotifications($query, $current_user_id, $company_id, $outlet_id, $supabaseUrl, $supabaseKey);
        $results = array_merge($results, $notificationResults);
    }
    
    
    usort($results, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    
    $results = array_slice($results, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'total' => count($results),
        'query' => $query
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function searchParcels($query, $company_id, $outlet_id, $supabaseUrl, $supabaseKey) {
    $filters = "company_id=eq.$company_id";
    if ($outlet_id) {
        $filters .= "&origin_outlet_id=eq.$outlet_id";
    }
    
    
    $searchFilter = "&or=(track_number.ilike.*$query*,sender_name.ilike.*$query*,receiver_name.ilike.*$query*)";
    
    $url = "$supabaseUrl/rest/v1/parcels?$filters$searchFilter&select=id,track_number,sender_name,receiver_name,status,created_at&limit=10";
    
    $response = makeSupabaseRequest($url, 'GET', null, $supabaseKey);
    
    if ($response['status_code'] !== 200) {
        return [];
    }
    
    $results = [];
    foreach ($response['data'] as $parcel) {
        $results[] = [
            'id' => $parcel['id'],
            'type' => 'parcel',
            'title' => "Parcel #{$parcel['track_number']}",
            'subtitle' => "From: {$parcel['sender_name']} → To: {$parcel['receiver_name']}",
            'status' => $parcel['status'],
            'date' => $parcel['created_at'],
            'url' => "#",
            'onclick' => "viewParcelDetails('{$parcel['id']}')"
        ];
    }
    
    return $results;
}

function searchDeliveries($query, $company_id, $outlet_id, $supabaseUrl, $supabaseKey) {
    $filters = "company_id=eq.$company_id";
    if ($outlet_id) {
        $filters .= "&outlet_id=eq.$outlet_id";
    }
    
    
    $searchFilter = "&or=(tracking_number.ilike.*$query*,delivery_status.ilike.*$query*)";
    
    $url = "$supabaseUrl/rest/v1/deliveries?$filters$searchFilter&select=id,tracking_number,delivery_status,estimated_delivery_date,created_at&limit=10";
    
    $response = makeSupabaseRequest($url, 'GET', null, $supabaseKey);
    
    if ($response['status_code'] !== 200) {
        return [];
    }
    
    $results = [];
    foreach ($response['data'] as $delivery) {
        $results[] = [
            'id' => $delivery['id'],
            'type' => 'delivery',
            'title' => "Delivery #{$delivery['tracking_number']}",
            'subtitle' => "Status: {$delivery['delivery_status']} | ETA: " . date('M d, Y', strtotime($delivery['estimated_delivery_date'])),
            'status' => $delivery['delivery_status'],
            'date' => $delivery['created_at'],
            'url' => "delivery_details.php?id={$delivery['id']}"
        ];
    }
    
    return $results;
}

function searchCustomers($query, $company_id, $supabaseUrl, $supabaseKey) {
    $filters = "company_id=eq.$company_id";
    
    
    $searchFilter = "&or=(name.ilike.*$query*,email.ilike.*$query*,phone.ilike.*$query*)";
    
    $url = "$supabaseUrl/rest/v1/customers?$filters$searchFilter&select=id,name,email,phone,created_at&limit=10";
    
    $response = makeSupabaseRequest($url, 'GET', null, $supabaseKey);
    
    if ($response['status_code'] !== 200) {
        return [];
    }
    
    $results = [];
    foreach ($response['data'] as $customer) {
        $results[] = [
            'id' => $customer['id'],
            'type' => 'customer',
            'title' => $customer['name'],
            'subtitle' => "{$customer['email']} | {$customer['phone']}",
            'status' => 'active',
            'date' => $customer['created_at'],
            'url' => "customer_details.php?id={$customer['id']}"
        ];
    }
    
    return $results;
}

function searchNotifications($query, $current_user_id, $company_id, $outlet_id, $supabaseUrl, $supabaseKey) {
    $filters = "recipient_id=eq.$current_user_id&company_id=eq.$company_id";
    if ($outlet_id) {
        $filters .= "&outlet_id=eq.$outlet_id";
    }
    
    
    $searchFilter = "&or=(title.ilike.*$query*,message.ilike.*$query*)";
    
    $url = "$supabaseUrl/rest/v1/notifications?$filters$searchFilter&select=id,title,message,notification_type,status,created_at&limit=10";
    
    $response = makeSupabaseRequest($url, 'GET', null, $supabaseKey);
    
    if ($response['status_code'] !== 200) {
        return [];
    }
    
    $results = [];
    foreach ($response['data'] as $notification) {
        $results[] = [
            'id' => $notification['id'],
            'type' => 'notification',
            'title' => $notification['title'],
            'subtitle' => substr($notification['message'], 0, 100) . (strlen($notification['message']) > 100 ? '...' : ''),
            'status' => $notification['status'],
            'date' => $notification['created_at'],
            'url' => "notifications.php?highlight={$notification['id']}"
        ];
    }
    
    return $results;
}
?>
