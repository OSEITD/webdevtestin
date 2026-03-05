<?php
require_once __DIR__ . '/../../includes/session_manager.php';
initSession();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

if (!isset($_SESSION['outlet_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: outlet_id not set in session"]);
    exit;
}

$outlet_id = $_SESSION['outlet_id'];

if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../../includes/env.php'; }
EnvLoader::load();
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

$ch = curl_init("$supabaseUrl/rest/v1/outlets?id=eq.$outlet_id&select=*");

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
    $outlet = json_decode($response, true);
    if (!empty($outlet)) {
        echo json_encode(["success" => true, "data" => $outlet[0]]);
    } else {
        echo json_encode(["success" => false, "error" => "Outlet not found"]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Failed to fetch outlet details"]);
}
?>
