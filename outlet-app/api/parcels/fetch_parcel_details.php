<?php
session_start();
header("Content-Type: application/json");

error_log("fetch_parcel_details.php called with track_number: " . ($_GET['track_number'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$track_number = $_GET['track_number'] ?? null;
if (!$track_number) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameter: track_number"]);
    exit;
}

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$ch = curl_init("$supabaseUrl/rest/v1/parcels?track_number=eq.$track_number&select=*");

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
    $data = json_decode($response, true);
    if (count($data) > 0) {
        echo json_encode($data[0]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Parcel not found"]);
    }
} else {
    http_response_code($http_code);
    echo json_encode([
        "error" => "Supabase error",
        "http_code" => $http_code,
        "curl_error" => $curl_error,
        "response" => $response
    ]);
}

curl_close($ch);
?>
