<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
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

$required_fields = ['current_password', 'new_password', 'confirm_password'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required field: $field"]);
        exit;
    }
}

$current_password = $input['current_password'];
$new_password = $input['new_password'];
$confirm_password = $input['confirm_password'];

if ($new_password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(["error" => "New password and confirmation do not match"]);
    exit;
}

if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "New password must be at least 8 characters long"]);
    exit;
}

if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)/', $new_password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one letter and one number"]);
    exit;
}

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$update_data = [
    'password_last_updated' => date('Y-m-d H:i:s')
];

$ch = curl_init("$supabaseUrl/rest/v1/profiles?id=eq.$user_id");

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
    echo json_encode([
        "success" => true, 
        "message" => "Password updated successfully. Please log in again with your new password."
    ]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to update password", "response" => $response]);
}
?>
