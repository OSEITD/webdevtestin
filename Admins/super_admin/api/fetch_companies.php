<?php
// ../api/fetch_companies.php

require_once 'supabase-client.php';

global $supabaseUrl, $supabaseKey;

header('Content-Type: application/json');

$url = "$supabaseUrl/companies?select=name,contact_person,contact_email,status";
$options = [
    'http' => [
        'method' => 'GET',
        'header' => "apikey: $supabaseKey\r\nAuthorization: Bearer $supabaseKey\r\n"
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch companies']);
    exit;
}

echo $response;
?>
