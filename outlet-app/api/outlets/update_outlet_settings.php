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

$required_fields = ['outlet_name', 'address', 'contact_email', 'contact_phone'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required field: $field"]);
        exit;
    }
}

$outlet_name = filter_var($input['outlet_name'], FILTER_SANITIZE_STRING);
$address = filter_var($input['address'], FILTER_SANITIZE_STRING);
$contact_email = filter_var($input['contact_email'], FILTER_SANITIZE_EMAIL);
$contact_phone_raw = filter_var($input['contact_phone'], FILTER_SANITIZE_STRING);
$contact_phone = preg_replace('/\D/', '', $contact_phone_raw); 
$contact_person = isset($input['contact_person']) ? filter_var($input['contact_person'], FILTER_SANITIZE_STRING) : '';
$status = isset($input['status']) ? filter_var($input['status'], FILTER_SANITIZE_STRING) : 'active';
$latitude = isset($input['latitude']) ? floatval($input['latitude']) : null;
$longitude = isset($input['longitude']) ? floatval($input['longitude']) : null;

if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email format"]);
    exit;
}

if (empty($contact_phone) || strlen($contact_phone) < 9 || strlen($contact_phone) > 15) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid phone number format. Phone must be 9-15 digits."]);
    exit;
}

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$update_data = [
    'outlet_name' => $outlet_name,
    'address' => $address,
    'contact_email' => $contact_email,
    'contact_phone' => intval($contact_phone), 
    'contact_person' => $contact_person,
    'status' => $status,
    'updated_at' => date('c')
];

if ($latitude !== null) {
    $update_data['latitude'] = $latitude;
}
if ($longitude !== null) {
    $update_data['longitude'] = $longitude;
}

$ch = curl_init("$supabaseUrl/rest/v1/outlets?id=eq.$outlet_id");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "PATCH",
    CURLOPT_POSTFIELDS => json_encode($update_data),
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
    $updated_outlet = json_decode($response, true);
    echo json_encode(["success" => true, "message" => "Outlet settings updated successfully", "data" => $updated_outlet[0]]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to update outlet settings"]);
}
?>
