<?php
header('Content-Type: application/json');

if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../includes/env.php'; }
EnvLoader::load();
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

$input = json_decode(file_get_contents('php://input'), true);
$trackNumber = $input['track_number'] ?? null;
$outletId = $input['destination_outlet_id'] ?? null;
$outletName = $input['destination_outlet_name'] ?? null;

if (!$trackNumber || !$outletId || !$outletName) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

$url = "$supabaseUrl/rest/v1/parcels?track_number=eq.$trackNumber";
$data = json_encode([
    'destination_outlet_id' => $outletId,
    'destination_outlet_name' => $outletName
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $supabaseKey",
    "Authorization: Bearer $supabaseKey",
    "Content-Type: application/json",
    "Prefer: return=representation"
]);

$result = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'error' => $err]);
} else {
    echo $result;
}
?>