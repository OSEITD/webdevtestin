<?php

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

$companyId = $_SESSION['company_id'] ?? $_GET['company_id'] ?? null;

if (!$companyId) {
    ob_clean();
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET");
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Company ID is required"]);
    exit();
}

ob_clean();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$SUPABASE_URL = "https://xerpchdsykqafrsxbqef.supabase.co";
$SUPABASE_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

try {
    
    $ch = curl_init("$SUPABASE_URL/rest/v1/outlets?company_id=eq.$companyId&select=id,outlet_name,address&order=outlet_name");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "apikey: $SUPABASE_API_KEY",
            "Authorization: Bearer $SUPABASE_API_KEY"
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Debug logging
    error_log("fetch_company_outlets: company_id=$companyId, HTTP=$http_code, error=$curl_error");
    error_log("fetch_company_outlets: response=" . substr($response, 0, 500));

    if ($curl_error) {
        throw new Exception("cURL error: $curl_error");
    }

    if ($response === false || $http_code < 200 || $http_code >= 300) {
        throw new Exception("Failed to fetch outlets from Supabase (HTTP $http_code)");
    }

    $outlets = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from Supabase");
    }

    
    $formattedOutlets = array_map(function($outlet) {
        return [
            'id' => $outlet['id'],
            'name' => $outlet['outlet_name'] ?? 'Unnamed Outlet',
            'location' => $outlet['address'] ?? 'Unknown Location'
        ];
    }, $outlets);

    echo json_encode([
        "success" => true,
        "outlets" => $formattedOutlets,
        "message" => count($formattedOutlets) . " outlets found"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "outlets" => [],
        "message" => "No outlets available"
    ]);
}