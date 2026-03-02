<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['outlet_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$outlet_id = $_SESSION['outlet_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid request body"]);
    exit;
}

// Build update payload — only include fields present in the `outlets` table
$allowed = ['outlet_name', 'address', 'contact_person', 'contact_email', 'contact_phone', 'latitude', 'longitude', 'status'];
$payload = [];
foreach ($allowed as $field) {
    if (array_key_exists($field, $input)) {
        $payload[$field] = $input[$field];
    }
}

if (empty($payload)) {
    echo json_encode(["success" => false, "error" => "No valid fields to update"]);
    exit;
}

$payload['updated_at'] = date('c'); // ISO 8601

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$ch = curl_init("$supabaseUrl/rest/v1/outlets?id=eq.$outlet_id");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=representation",
    ],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    $result = json_decode($response, true);
    echo json_encode([
        "success" => true,
        "message" => "Outlet settings updated successfully",
        "data"    => is_array($result) && !empty($result) ? $result[0] : null,
    ]);
} else {
    $err = json_decode($response, true);
    echo json_encode([
        "success" => false,
        "error"   => isset($err['message']) ? $err['message'] : "Failed to update outlet settings (HTTP $http_code)",
    ]);
}
?>
