<?php
require_once __DIR__ . '/../includes/supabase.php';

header('Content-Type: application/json');

try {
    $supabase = getSupabaseClient();
    

    $url = $supabase->getRestUrl() . '/driver_locations?select=*&limit=10';
    
    $headers = [
        'apikey: ' . $supabase->getApiKey(),
        'Authorization: Bearer ' . $supabase->getApiKey(),
        'Content-Type: application/json'
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    echo json_encode([
        'success' => $httpCode === 200,
        'http_code' => $httpCode,
        'driver_locations_data' => $httpCode === 200 ? json_decode($response, true) : $response,
        'data_count' => $httpCode === 200 ? count(json_decode($response, true)) : 0
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>