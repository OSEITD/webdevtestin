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

if (!isset($_SESSION['outlet_id'])) {
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

if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../includes/env.php'; }
EnvLoader::load();
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

$checkCh = curl_init("$supabaseUrl/rest/v1/outlet_hours?outlet_id=eq.$outlet_id&select=id");
curl_setopt_array($checkCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
    ],
]);
$checkResponse = curl_exec($checkCh);
$checkCode     = curl_getinfo($checkCh, CURLINFO_HTTP_CODE);
curl_close($checkCh);

$existingRows = ($checkCode === 200) ? json_decode($checkResponse, true) : [];

$payload = [
    'outlet_id'         => $outlet_id,
    'opening_time'      => $input['opening_time']      ?? '09:00',
    'closing_time'      => $input['closing_time']      ?? '17:00',
    'days_of_operation' => $input['days_of_operation'] ?? 'Monday - Friday',
    'updated_at'        => date('c'),
];

if (!empty($existingRows)) {
    // ── Update existing record ────────────────────────────────────────────
    $ch = curl_init("$supabaseUrl/rest/v1/outlet_hours?outlet_id=eq.$outlet_id");
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
} else {
    // ── Insert new record ─────────────────────────────────────────────────
    $payload['created_at'] = date('c');
    $ch = curl_init("$supabaseUrl/rest/v1/outlet_hours");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation",
        ],
    ]);
}

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    echo json_encode(["success" => true, "message" => "Operating hours saved successfully"]);
} else {
    $err = json_decode($response, true);
    echo json_encode([
        "success" => false,
        "error"   => isset($err['message']) ? $err['message'] : "Failed to save operating hours (HTTP $http_code)",
    ]);
}
?>
