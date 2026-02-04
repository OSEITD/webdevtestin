<?php
session_start();
require_once '../includes/MultiTenantSupabaseHelper.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
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

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    
    $nrc = $_GET['nrc'] ?? null;
    $phone = $_GET['phone'] ?? null;
    $email = $_GET['email'] ?? null;
    
    if (!$nrc && !$phone && !$email) {
        echo json_encode([
            "success" => false,
            "error" => "At least one search parameter (NRC, phone, or email) is required"
        ]);
        exit;
    }
    
    $queryParts = [];
    if ($nrc) {
        $queryParts[] = "nrc=eq.$nrc";
    }
    if ($phone) {
        $queryParts[] = "phone=eq.$phone";
    }
    if ($email) {
        $queryParts[] = "email=eq.$email";
    }
    
    $query = implode('&', $queryParts);
    
    $customers = $supabase->get('global_customers', $query, 'id,full_name,nrc,phone,email,created_at');
    
    if (empty($customers)) {
        echo json_encode([
            "success" => true,
            "customer" => null,
            "message" => "Customer not found in global database"
        ]);
        exit;
    }
    
    $customer = $customers[0];
    
    $senderParcels = $supabase->get('parcels', "sender_phone=eq.{$customer['phone']}", 'id,created_at');
    $receiverParcels = $supabase->get('parcels', "receiver_phone=eq.{$customer['phone']}", 'id,created_at');
    
    $totalParcels = count($senderParcels ?? []) + count($receiverParcels ?? []);
    $isReturningCustomer = $totalParcels > 0;
    
    $latestParcelDate = null;
    $allParcels = array_merge($senderParcels ?? [], $receiverParcels ?? []);
    if (!empty($allParcels)) {
        $dates = array_column($allParcels, 'created_at');
        $latestParcelDate = max($dates);
    }
    
    echo json_encode([
        "success" => true,
        "customer" => [
            "id" => $customer['id'],
            "full_name" => $customer['full_name'],
            "nrc" => $customer['nrc'],
            "phone" => $customer['phone'],
            "email" => $customer['email'],
            "created_at" => $customer['created_at'],
            "is_returning_customer" => $isReturningCustomer,
            "total_parcels" => $totalParcels,
            "latest_parcel_date" => $latestParcelDate
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
