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

auth_guard();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT");

$company_id = $_SESSION['company_id'];
$outlet_id = $_SESSION['outlet_id'];

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $today_start = date('Y-m-d') . 'T00:00:00';
    $today_end = date('Y-m-d') . 'T23:59:59';
    
    
    $payments_today_url = "$supabaseUrl/rest/v1/payments?created_at=gte.$today_start&created_at=lte.$today_end&select=*,parcels(track_number,sender_name,receiver_name,origin_outlet_id)";
    
    $ch = curl_init($payments_today_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code >= 200 && $http_code < 300) {
        $all_payments = json_decode($response, true);
        
        
        $outlet_payments = array_filter($all_payments, function($payment) use ($outlet_id) {
            return isset($payment['parcels']['origin_outlet_id']) && 
                   $payment['parcels']['origin_outlet_id'] === $outlet_id;
        });
        
        
        $stats = [
            'total_payments_today' => count($outlet_payments),
            'total_amount_today' => array_sum(array_column($outlet_payments, 'amount')),
            'payment_methods' => [],
            'payment_status_breakdown' => [
                'paid' => 0,
                'pending' => 0,
                'failed' => 0,
                'partial' => 0
            ]
        ];
        
        foreach ($outlet_payments as $payment) {
            
            $method = $payment['method'];
            $stats['payment_methods'][$method] = ($stats['payment_methods'][$method] ?? 0) + 1;
            
            
            $status = $payment['status'];
            if (isset($stats['payment_status_breakdown'][$status])) {
                $stats['payment_status_breakdown'][$status]++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'payments_today' => $outlet_payments,
            'statistics' => $stats
        ]);
    } else {
        echo json_encode(['error' => 'Failed to fetch payments data']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $payment_data = [
        'parcel_id' => $input['parcel_id'],
        'amount' => floatval($input['amount']),
        'method' => $input['method'],
        'status' => $input['status'] ?? 'paid',
        'transaction_ref' => $input['transaction_ref'] ?? null,
        'paid_at' => $input['status'] === 'paid' ? date('c') : null
    ];
    
    $ch = curl_init("$supabaseUrl/rest/v1/payments");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payment_data),
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ],
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code >= 200 && $http_code < 300) {
        
        $parcel_update = [
            'payment_status' => $input['status'] === 'paid' ? 'paid' : 'pending'
        ];
        
        $ch_update = curl_init("$supabaseUrl/rest/v1/parcels?id=eq." . $input['parcel_id']);
        curl_setopt_array($ch_update, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($parcel_update),
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json"
            ],
        ]);
        
        curl_exec($ch_update);
        curl_close($ch_update);
        
        echo json_encode(['success' => true, 'payment' => json_decode($response, true)]);
    } else {
        echo json_encode(['error' => 'Failed to record payment', 'details' => $response]);
    }
}
?>
