<?php
session_start();
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

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

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
