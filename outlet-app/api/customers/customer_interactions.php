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
header("Access-Control-Allow-Methods: GET, POST");

$company_id = $_SESSION['company_id'];
$outlet_id = $_SESSION['outlet_id'];
$staff_id = $_SESSION['user_id'];

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $today_start = date('Y-m-d') . 'T00:00:00';
    $today_end = date('Y-m-d') . 'T23:59:59';
    
    $interactions_url = "$supabaseUrl/rest/v1/customer_interactions?company_id=eq.$company_id&created_at=gte.$today_start&created_at=lte.$today_end&order=created_at.desc&select=*";
    
    $ch = curl_init($interactions_url);
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
        $interactions = json_decode($response, true);
        
        $stats = [
            'total_interactions_today' => count($interactions),
            'interaction_types' => [],
            'channels' => [],
            'customer_types' => []
        ];
        
        foreach ($interactions as $interaction) {
            $type = $interaction['interaction_type'];
            $stats['interaction_types'][$type] = ($stats['interaction_types'][$type] ?? 0) + 1;
            
            $channel = $interaction['channel'];
            if ($channel) {
                $stats['channels'][$channel] = ($stats['channels'][$channel] ?? 0) + 1;
            }
            
            $customer_type = $interaction['customer_type'];
            $stats['customer_types'][$customer_type] = ($stats['customer_types'][$customer_type] ?? 0) + 1;
        }
        
        echo json_encode([
            'success' => true,
            'interactions_today' => $interactions,
            'statistics' => $stats
        ]);
    } else {
        echo json_encode(['error' => 'Failed to fetch customer interactions']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $interaction_data = [
        'company_id' => $company_id,
        'customer_id' => $input['customer_id'] ?? null,
        'customer_type' => $input['customer_type'],
        'interaction_type' => $input['interaction_type'],
        'channel' => $input['channel'] ?? 'web',
        'description' => $input['description'],
        'parcel_id' => $input['parcel_id'] ?? null,
        'staff_id' => $staff_id
    ];
    
    $ch = curl_init("$supabaseUrl/rest/v1/customer_interactions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($interaction_data),
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
        echo json_encode(['success' => true, 'interaction' => json_decode($response, true)]);
    } else {
        echo json_encode(['error' => 'Failed to log customer interaction', 'details' => $response]);
    }
}
?>
