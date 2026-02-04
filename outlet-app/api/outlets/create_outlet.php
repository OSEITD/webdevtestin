<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
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

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$insert_data = [
    'outlet_name' => filter_var($input['outlet_name'], FILTER_SANITIZE_STRING),
    'address' => filter_var($input['address'], FILTER_SANITIZE_STRING),
    'contact_person' => filter_var($input['contact_person'], FILTER_SANITIZE_STRING),
    'contact_email' => filter_var($input['contact_email'], FILTER_SANITIZE_EMAIL),
    'contact_phone' => filter_var($input['contact_phone'], FILTER_SANITIZE_STRING),
    'company_id' => $_SESSION['company_id'] ?? null,
    'status' => 'active',
    'created_at' => date('c'),
    'updated_at' => date('c')
];

$required_fields = ['outlet_name', 'address', 'contact_person', 'contact_email', 'contact_phone'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required field: $field"]);
        exit;
    }
}

if (!filter_var($input['contact_email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email format"]);
    exit;
}

if (!preg_match('/^[0-9+\-\s]+$/', $input['contact_phone'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid phone number format"]);
    exit;
}

$ch = curl_init("$supabaseUrl/rest/v1/outlets");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($insert_data),
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
    $new_outlet = json_decode($response, true);
    echo json_encode(["success" => true, "message" => "Outlet created successfully", "data" => $new_outlet[0]]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to create outlet"]);
}
?>
