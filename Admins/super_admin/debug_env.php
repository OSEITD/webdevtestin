<?php
// Safe debug endpoint for Render environment troubleshooting
// Usage:
//  - GET /Admins/super_admin/debug_env.php           -> shows masked env + service key ref
//  - GET /Admins/super_admin/debug_env.php?test=1&email=...&password=...  -> runs auth test (careful: do not expose in public)

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../api/supabase-client.php';

function mask_val($v) {
    if ($v === null || $v === '') return 'NULL';
    $v = trim($v);
    if (strlen($v) <= 12) return substr($v,0,4) . '...' . substr($v,-4);
    return substr($v,0,4) . str_repeat('*', min(20, strlen($v)-8)) . substr($v,-4);
}

function decode_ref_from_jwt($jwt) {
    if (empty($jwt) || substr_count($jwt, '.') < 2) return null;
    list(, $payload,) = explode('.', $jwt);
    $payload = str_replace(['-', '_'], ['+', '/'], $payload);
    $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $decoded = base64_decode($payload);
    if ($decoded === false) return null;
    $data = json_decode($decoded, true);
    return is_array($data) ? ($data['ref'] ?? $data['project_ref'] ?? null) : null;
}

$supabaseUrl = trim(getenv('SUPABASE_URL') ?: EnvLoader::get('SUPABASE_URL') ?: '');
$supabaseAnon = trim(getenv('SUPABASE_ANON_KEY') ?: EnvLoader::get('SUPABASE_ANON_KEY') ?: '');
$supabaseService = trim(getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY') ?: EnvLoader::get('SUPABASE_SERVICE_ROLE_KEY') ?: EnvLoader::get('SUPABASE_SERVICE_KEY') ?: '');

$out = [
    'supabase_url' => $supabaseUrl ?: 'NULL',
    'supabase_anon_masked' => mask_val($supabaseAnon),
    'supabase_service_masked' => mask_val($supabaseService),
    'service_key_ref' => decode_ref_from_jwt($supabaseService),
];

// If test requested, perform an auth POST using anon key
if (!empty($_GET['test']) && isset($_GET['email']) && isset($_GET['password'])) {
    $email = $_GET['email'];
    $password = $_GET['password'];
    $authUrl = rtrim($supabaseUrl, '/') . '/auth/v1/token?grant_type=password';

    $ch = curl_init($authUrl);
    $payload = json_encode(['email' => $email, 'password' => $password]);
    $headers = [
        'apikey: ' . $supabaseAnon,
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $out['test'] = [
        'request_url' => $authUrl,
        'http_code' => $http,
        'curl_error' => $err ?: null,
        'response_snippet' => $resp ? substr($resp, 0, 1000) : null,
    ];
}

header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);

// Note: Remove this file after debugging. Do not expose on public production without protections.

?>
