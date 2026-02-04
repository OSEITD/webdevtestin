<?php
header('Content-Type: application/json');
error_reporting(0);
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
        throw new Exception('Access denied. Only outlet managers can access delivery data.');
    }
    
    $outlet_id = $current_user['outlet_id'];
    $company_id = $current_user['company_id'];
    
    if (!$outlet_id) {
        throw new Exception('No outlet assigned. Please contact your administrator.');
    }
    
    
    if (!$outlet_id) {
        if (in_array($user_role, ['admin', 'super_admin']) && $company_id) {
            $result = [
                'success' => true,
                'message' => 'No outlet assigned for delivery tracking',
                'data' => [
                    'parcels' => [],
                    'summary' => [
                        'total_parcels' => 0,
                        'total_revenue' => 0,
                        'total_weight' => 0,
                        'total_declared_value' => 0,
                        'average_fee' => 0
                    ],
                    'date' => date('Y-m-d'),
                    'outlet_id' => null,
                    'company_id' => $company_id
                ]
            ];
            echo json_encode($result);
            exit;
        } else {
            throw new Exception('Outlet access required for delivery tracking');
        }
    }
    
    if (!$company_id) {
        throw new Exception('Company information not available');
    }
    
    $supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
    
    $today_date = date('Y-m-d');
    
    $delivered_url = "$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outlet_id&status=eq.delivered&delivery_date=eq.$today_date&select=id,track_number,sender_name,receiver_name,receiver_address,delivery_fee,declared_value,delivery_date,created_at,parcel_weight,package_details";
    
    $response = makeApiCall($delivered_url, $supabaseKey);
    
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
            'date' => $today_date,
            'outlet_id' => $outlet_id,
            'company_id' => $company_id
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
