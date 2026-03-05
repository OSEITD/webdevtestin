<?php
require_once __DIR__ . '/../includes/session_manager.php';
initSession();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$input   = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid request body"]);
    exit;
}

$payload = [];

if (isset($input['notify_parcel']))          $payload['notify_parcel']          = (bool) $input['notify_parcel'];
if (isset($input['notify_dispatch']))        $payload['notify_dispatch']        = (bool) $input['notify_dispatch'];
if (isset($input['notify_urgent']))          $payload['notify_urgent']          = (bool) $input['notify_urgent'];
if (isset($input['notifications_enabled'])) $payload['notifications_enabled']  = (bool) $input['notifications_enabled'];
if (isset($input['session_timeout']))        $payload['session_timeout']        = (int)  $input['session_timeout'];

if (empty($payload)) {
    echo json_encode(["success" => false, "error" => "No valid fields to update"]);
    exit;
}

if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../includes/env.php'; }
EnvLoader::load();
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

$ch = curl_init("$supabaseUrl/rest/v1/profiles?id=eq.$user_id");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=representation",
    ],
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    echo json_encode(["success" => true, "message" => "Notification settings saved successfully"]);
} else {
    $err = json_decode($response, true);
    echo json_encode([
        "success" => false,
        "error"   => isset($err['message']) ? $err['message'] : "Failed to save notification settings (HTTP $http_code)",
    ]);
}
?>
