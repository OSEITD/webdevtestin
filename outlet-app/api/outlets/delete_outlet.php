<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE");
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

if (!isset($input['outlet_id']) || $input['outlet_id'] !== $outlet_id) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid outlet ID"]);
    exit;
}

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$ch = curl_init("$supabaseUrl/rest/v1/outlets?id=eq.$outlet_id");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "DELETE",
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
    echo json_encode(["success" => true, "message" => "Outlet deleted successfully"]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to delete outlet"]);
}
?>
