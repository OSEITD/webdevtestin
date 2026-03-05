<?php
require_once __DIR__ . '/../../includes/session_manager.php';
initSession();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

if (!isset($_SESSION['outlet_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: outlet_id or company_id not set in session"]);
    exit;
}

$origin_outlet_id = $_SESSION['outlet_id'];
$company_id = $_SESSION['company_id'];

if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../../includes/env.php'; }
EnvLoader::load();
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

$ch = curl_init("$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$origin_outlet_id&company_id=eq.$company_id&status=eq.pending&select=id,track_number,sender_name,receiver_name,status,parcel_weight,package_details");

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
$curl_error = curl_error($ch);

if ($http_code >= 200 && $http_code < 300) {
    $parcels = json_decode($response, true);
    echo json_encode([
        'success' => true,
        'parcels' => $parcels
    ]);
} else {
    echo json_encode([
        "success" => false,
        "http_code" => $http_code,
        "response" => $response,
        "curl_error" => $curl_error,
        "error" => "Supabase error"
    ]);
}

curl_close($ch);
?>
