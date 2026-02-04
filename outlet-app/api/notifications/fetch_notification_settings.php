<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: user_id not set in session"]);
    exit;
}

$user_id = $_SESSION['user_id'];

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$ch = curl_init("$supabaseUrl/rest/v1/profiles?id=eq.$user_id&select=notifications_enabled,notify_parcel,notify_dispatch,notify_urgent,session_timeout");

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
    $profile = json_decode($response, true);
    if (!empty($profile)) {
        echo json_encode(["success" => true, "data" => $profile[0]]);
    } else {
        
        echo json_encode([
            "success" => true, 
            "data" => [
                "notifications_enabled" => true,
                "notify_parcel" => true,
                "notify_dispatch" => true,
                "notify_urgent" => true,
                "session_timeout" => 30
            ]
        ]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Failed to fetch notification settings"]);
}
?>
