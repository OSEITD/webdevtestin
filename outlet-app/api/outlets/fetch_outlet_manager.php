<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: Not authenticated"]);
    exit;
}

if (!isset($_SESSION['outlet_id'])) {
    
    echo json_encode([
        "name" => "Company Manager",
        "note" => "No specific outlet manager assigned"
    ]);
    exit;
}

$outlet_id = $_SESSION['outlet_id'];

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$ch = curl_init("$supabaseUrl/rest/v1/profiles?outlet_id=eq.$outlet_id&role=eq.outlet_manager&select=full_name");

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
    $manager = json_decode($response, true);
    if (!empty($manager)) {
        echo json_encode(["name" => $manager[0]['full_name']]);
    } else {
        echo json_encode(["error" => "Manager not found"]);
    }
} else {
    echo json_encode(["error" => "Supabase error"]);
}
?>
