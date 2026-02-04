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

ob_clean();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header('Cache-Control: no-cache, must-revalidate');

try {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
        !isset($_SESSION['company_id']) || !isset($_SESSION['outlet_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required'
        ]);
        ob_end_flush();
        exit();
    }
    
    $company_id = $_SESSION['company_id'];
    $outlet_id = $_SESSION['outlet_id'];

    $supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $customers_url = "$supabaseUrl/rest/v1/business_customers?company_id=eq.$company_id&select=*";
    
    $ch = curl_init($customers_url);
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
        $customers = json_decode($response, true);
        
        foreach ($customers as &$customer) {
            $parcel_count_url = "$supabaseUrl/rest/v1/parcels?company_id=eq.$company_id&sender_name=eq." . urlencode($customer['business_name']) . "&select=id";
            
            $ch_count = curl_init($parcel_count_url);
            curl_setopt_array($ch_count, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey",
                    "Content-Type: application/json"
                ],
            ]);
            
            $count_response = curl_exec($ch_count);
            $count_http_code = curl_getinfo($ch_count, CURLINFO_HTTP_CODE);
            curl_close($ch_count);
            
            if ($count_http_code >= 200 && $count_http_code < 300) {
                $parcels = json_decode($count_response, true);
                $customer['total_parcels'] = count($parcels);
            } else {
                $customer['total_parcels'] = 0;
            }
            
            $customer['balance_status'] = $customer['current_balance'] > $customer['credit_limit'] ? 'overlimit' : 'within_limit';
        }
    
        
        echo json_encode([
            'success' => true,
            'customers' => $customers,
            'total_customers' => count($customers)
        ]);
    } else {
        echo json_encode(['error' => 'Failed to fetch business customers']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $customer_data = [
        'company_id' => $company_id,
        'business_name' => $input['business_name'],
        'contact_person' => $input['contact_person'],
        'phone' => $input['phone'],
        'email' => $input['email'] ?? null,
        'address' => $input['address'],
        'business_type' => $input['business_type'] ?? null,
        'payment_terms' => $input['payment_terms'] ?? 'prepaid',
        'credit_limit' => floatval($input['credit_limit'] ?? 0),
        'tax_id' => $input['tax_id'] ?? null
    ];
    
    $ch = curl_init("$supabaseUrl/rest/v1/business_customers");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($customer_data),
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
        echo json_encode(['success' => true, 'customer' => json_decode($response, true)]);
    } else {
        echo json_encode(['error' => 'Failed to create business customer', 'details' => $response]);
    }
}

    ob_end_flush();
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    ob_end_flush();
}
?>
