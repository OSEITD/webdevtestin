<?php
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
ob_end_clean();

require_once __DIR__ . '/env.php';

EnvLoader::load();

$supabaseUrl = EnvLoader::get('SUPABASE_URL');

// Prefer the strongest key available for outbound API calls (service role > service > anon)
$supabaseServiceKey = EnvLoader::get('SUPABASE_SERVICE_ROLE_KEY');
if (empty($supabaseServiceKey)) {
    $supabaseServiceKey = EnvLoader::get('SUPABASE_SERVICE_KEY');
}

$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');

// If a service key exists, use it for requests; otherwise fall back to anon
if (!empty($supabaseServiceKey)) {
    $supabaseKey = $supabaseServiceKey;
}

if (empty($supabaseUrl) || empty($supabaseKey)) {
    throw new Exception('Supabase configuration missing: ensure SUPABASE_URL and at least one key (anon/service) are set in your .env');
}

function getSupabaseClient() {
    global $supabaseUrl, $supabaseKey;

    
    $baseUri = rtrim($supabaseUrl, '/') . '/rest/v1';

    $client = new \GuzzleHttp\Client([
        'base_uri' => $baseUri,
        'headers' => [
            'Authorization' => "Bearer $supabaseKey",
            'apikey' => $supabaseKey,
            'Content-Type' => 'application/json',
        ],
    ]);

    return $client;
}

function executeQuery($query) {
    $client = getSupabaseClient();
    try {
        // When base_uri already contains /rest/v1, we can call /rpc directly.
        $response = $client->request('POST', '/rpc', [
            'json' => $query
        ]);
        return json_decode($response->getBody(), true);
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}
