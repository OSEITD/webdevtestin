<?php
session_start();
require_once '../includes/MultiTenantSupabaseHelper.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $required = ['full_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $existingCustomer = null;
    if (!empty($input['nrc'])) {
        $existing = $supabase->get('global_customers', "nrc=eq.{$input['nrc']}", 'id,full_name,phone,email');
        if (!empty($existing)) {
            $existingCustomer = $existing[0];
        }
    } elseif (!empty($input['email'])) {
        $existing = $supabase->get('global_customers', "email=eq.{$input['email']}", 'id,full_name,phone,email');
        if (!empty($existing)) {
            $existingCustomer = $existing[0];
        }
    }
    
    if ($existingCustomer) {
        echo json_encode([
            "success" => true,
            "customer" => $existingCustomer,
            "created" => false,
            "message" => "Customer already exists in global database"
        ]);
        exit;
    }
    
    $customerId = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    $customerData = [
        'id' => $customerId,
        'full_name' => $input['full_name'],
        'nrc' => $input['nrc'] ?? null,
        'phone' => $input['phone'] ?? null,
        'email' => $input['email'] ?? null
    ];
    
    $result = $supabase->postGlobal('global_customers', $customerData);
    
    if (!$result) {
        throw new Exception("Failed to create global customer");
    }
    
    echo json_encode([
        "success" => true,
        "customer" => $customerData,
        "created" => true,
        "message" => "Customer created successfully in global database"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
