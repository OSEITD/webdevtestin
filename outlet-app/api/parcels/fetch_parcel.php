<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$barcode   = $_GET['barcode'] ?? null;
$parcelId  = $_GET['id']      ?? null;

if (!$barcode && !$parcelId) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameter: barcode or id"]);
    exit;
}

if (!isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: company_id not set in session"]);
    exit;
}

$company_id = $_SESSION['company_id'];

if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../../includes/env.php'; }
EnvLoader::load();
$SUPABASE_URL     = getenv('SUPABASE_URL');
$SUPABASE_API_KEY = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

// Build query filter: prefer UUID id lookup, fall back to track_number barcode
if ($parcelId) {
    $queryFilter = "id=eq." . urlencode($parcelId) . "&company_id=eq." . urlencode($company_id) . "&select=*";
} else {
    $queryFilter = "track_number=eq." . urlencode($barcode) . "&company_id=eq." . urlencode($company_id) . "&select=*";
}

$ch = curl_init("$SUPABASE_URL/rest/v1/parcels?$queryFilter");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: $SUPABASE_API_KEY",
        "Authorization: Bearer $SUPABASE_API_KEY",
        "Content-Type: application/json"
    ],
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    $parcels = json_decode($response, true);
    if (count($parcels) > 0) {
        echo json_encode([
            "success" => true,
            "parcel"  => $parcels[0]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error"   => "Parcel not found or does not belong to your company"
        ]);
    }
} else {
    echo json_encode([
        "success"    => false,
        "http_code"  => $http_code,
        "response"   => $response,
        "curl_error" => $curl_error,
        "error"      => "Supabase error"
    ]);
}
?>

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: $SUPABASE_API_KEY",
        "Authorization: Bearer $SUPABASE_API_KEY",
        "Content-Type: application/json"
    ],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

if ($http_code >= 200 && $http_code < 300) {
    $parcels = json_decode($response, true);
    if (count($parcels) > 0) {
        echo json_encode([
            "success" => true,
            "parcel" => $parcels[0]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Parcel not found or does not belong to your company"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "http_code" => $http_code,
        "response" => $response,
        "curl_error" => $curl_error,
        "error" => "Supabase error"
    ]);
}

curl_close($ch);
?>
