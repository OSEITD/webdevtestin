<?php
session_start();
require_once '../../includes/MultiTenantSupabaseHelper.php';

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
    
    
    error_log("Fetching outlets for company: " . $_SESSION['company_id']);
    $outlets = $supabase->get('outlets', '', 'id,outlet_name,location,address,contact_person');
    
    error_log("Outlets result type: " . gettype($outlets) . ", count: " . (is_array($outlets) ? count($outlets) : 'N/A'));
    
    if ($outlets === false || $outlets === null) {
        throw new Exception("Failed to fetch outlets");
    }
    
    echo json_encode([
        "success" => true,
        "outlets" => $outlets
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
