<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT");
header("Access-Control-Allow-Headers: Content-Type");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: user_id not set in session"]);
    exit;
}

$user_id = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input data"]);
    exit;
}

$notify_parcel = isset($input['notify_parcel']) ? $input['notify_parcel'] : true;
$notify_dispatch = isset($input['notify_dispatch']) ? $input['notify_dispatch'] : true;
$notify_urgent = isset($input['notify_urgent']) ? $input['notify_urgent'] : true;
$notifications_enabled = isset($input['notifications_enabled']) ? $input['notifications_enabled'] : true;
$session_timeout = isset($input['session_timeout']) ? intval($input['session_timeout']) : 30;

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$settings_data = [
    'notifications_enabled' => $notifications_enabled,
    'notify_parcel' => $notify_parcel,
    'notify_dispatch' => $notify_dispatch,
    'notify_urgent' => $notify_urgent,
    'session_timeout' => $session_timeout
];

$ch = curl_init("$supabaseUrl/rest/v1/profiles?id=eq.$user_id");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "PATCH",
    CURLOPT_POSTFIELDS => json_encode($settings_data),
    CURLOPT_HTTPHEADER => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    $updated_profile = json_decode($response, true);
    echo json_encode([
        "success" => true, 
        "message" => "Notification settings updated successfully", 
        "data" => $updated_profile[0] ?? $updated_profile
    ]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to update notification settings", "response" => $response]);
}
?>
