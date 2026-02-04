<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/env.php';

EnvLoader::load();

$supabaseUrl = EnvLoader::get('SUPABASE_URL', 'https://xerpchdsykqafrsxbqef.supabase.co');
$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');
$supabaseServiceKey = EnvLoader::get('SUPABASE_SERVICE_KEY');

function getSupabaseClient() {
    global $supabaseUrl, $supabaseKey;

    $client = new \GuzzleHttp\Client([
        'base_uri' => $supabaseUrl,
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
        $response = $client->request('POST', '/rest/v1/rpc', [
            'json' => $query
        ]);
        return json_decode($response->getBody(), true);
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}
