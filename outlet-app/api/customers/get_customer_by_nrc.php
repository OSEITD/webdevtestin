<?php
session_start();
require_once '../../includes/MultiTenantSupabaseHelper.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$nrc = isset($_GET['nrc']) ? trim($_GET['nrc']) : '';
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';

if (empty($nrc) && empty($phone)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing NRC or phone"]);
    exit;
}

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    
    if (!empty($nrc)) {
        $encodedNrc = str_replace('/', '%2F', $nrc);
        $url = $supabase->getUrl() . "/rest/v1/global_customers?nrc=eq." . $encodedNrc . "&select=id,full_name,phone,email,nrc,address&limit=1";
    } else {
        $encodedPhone = urlencode($phone);
        $url = $supabase->getUrl() . "/rest/v1/global_customers?phone=eq." . $encodedPhone . "&select=id,full_name,phone,email,nrc,address&limit=1";
    }
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer " . $supabase->getKey() . "\r\n" .
                       "apikey: " . $supabase->getKey() . "\r\n" .
                       "Content-Type: application/json\r\n",
            'timeout' => 3
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception("Failed to fetch customer data");
    }
    
    $customer = json_decode($response, true);
    
    if ($customer && count($customer) > 0) {
        echo json_encode([
            "success" => true,
            "customer" => $customer[0]
        ]);
        exit;
    }

    echo json_encode([
        "success" => false, 
        "error" => "Customer not found"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
