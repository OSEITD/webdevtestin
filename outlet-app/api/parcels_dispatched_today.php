<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

function makeApiCall($url, $apiKey) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "apikey: $apiKey",
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || !empty($curlError)) {
        throw new Exception("cURL Error: $curlError");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("API returned HTTP $httpCode: $response");
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response: ' . json_last_error_msg());
    }
    
    return $decoded;
}

try {
    require_once '../includes/auth_guard.php';

    
    auth_guard(false);
    
    $current_user = getCurrentUser();
    $user_role = $current_user['role'] ?? 'customer';
    
    
    if ($user_role !== 'outlet_manager') {
        throw new Exception('Access denied. Only outlet managers can access dispatch data.');
    }
    
    $outlet_id = $current_user['outlet_id'];
    $company_id = $current_user['company_id'];
    
    
    if (!$outlet_id) {
        throw new Exception('No outlet assigned. Please contact your administrator.');
    }
    
    
    $supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
    
    
    $dispatched_status = urlencode('Out for Delivery');
    $dispatched_url = "$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outlet_id&status=eq.$dispatched_status&select=id,track_number,sender_name,receiver_name,receiver_address,delivery_fee,declared_value,created_at,parcel_weight,package_details,delivery_date,driver_id";
    
    $response = makeApiCall($dispatched_url, $supabaseKey);
    
    
    $total_revenue = 0;
    $total_parcels = count($response);
    $total_weight = 0;
    $total_declared_value = 0;
    
    foreach ($response as $parcel) {
        $total_revenue += floatval($parcel['delivery_fee'] ?? 0);
        $total_weight += floatval($parcel['parcel_weight'] ?? 0);
        $total_declared_value += floatval($parcel['declared_value'] ?? 0);
    }
    
    
    $result = [
        'success' => true,
        'data' => [
            'parcels' => $response,
            'summary' => [
                'total_parcels' => $total_parcels,
                'total_revenue' => $total_revenue,
                'total_weight' => $total_weight,
                'total_declared_value' => $total_declared_value,
                'average_fee' => $total_parcels > 0 ? $total_revenue / $total_parcels : 0
            ],
            'date' => date('Y-m-d'),
            'outlet_id' => $outlet_id,
            'company_id' => $company_id,
            'status_filter' => 'Out for Delivery'
        ]
    ];
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c'),
        'debug' => [
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
}
?>
