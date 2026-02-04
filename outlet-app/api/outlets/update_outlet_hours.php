<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT");
header("Access-Control-Allow-Headers: Content-Type");

if (!isset($_SESSION['outlet_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: outlet_id not set in session"]);
    exit;
}

$outlet_id = $_SESSION['outlet_id'];

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input data"]);
    exit;
}

$required_fields = ['opening_time', 'closing_time', 'days_of_operation'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required field: $field"]);
        exit;
    }
}

$opening_time = $input['opening_time'];
$closing_time = $input['closing_time'];
$days_of_operation = $input['days_of_operation'];

if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $opening_time) || 
    !preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $closing_time)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid time format. Please use HH:MM format."]);
    exit;
}

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$ch = curl_init("$supabaseUrl/rest/v1/outlet_hours?outlet_id=eq.$outlet_id");

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

$existing_hours = json_decode($response, true);

$hours_data = [
    'outlet_id' => $outlet_id,
    'opening_time' => $opening_time,
    'closing_time' => $closing_time,
    'days_of_operation' => $days_of_operation,
    'updated_at' => date('c')
];

if (!empty($existing_hours)) {
    
    $ch = curl_init("$supabaseUrl/rest/v1/outlet_hours?outlet_id=eq.$outlet_id");
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_POSTFIELDS => json_encode($hours_data),
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ],
    ]);
} else {
    
    $hours_data['created_at'] = date('c');
    
    $ch = curl_init("$supabaseUrl/rest/v1/outlet_hours");
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($hours_data),
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ],
    ]);
}

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    $updated_hours = json_decode($response, true);
    echo json_encode([
        "success" => true, 
        "message" => "Outlet hours updated successfully", 
        "data" => $updated_hours[0] ?? $updated_hours
    ]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to update outlet hours", "response" => $response]);
}
?>
