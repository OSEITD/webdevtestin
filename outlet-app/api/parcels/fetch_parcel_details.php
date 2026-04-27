<?php
require_once __DIR__ . '/../../includes/session_manager.php';
initSession();
header("Content-Type: application/json");

error_log("fetch_parcel_details.php called with track_number: " . ($_GET['track_number'] ?? 'not set') . ", id: " . ($_GET['parcel_id'] ?? $_GET['id'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$track_number = $_GET['track_number'] ?? null;
$id = $_GET['parcel_id'] ?? $_GET['id'] ?? null;

if (empty($track_number) && empty($id)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameter: parcel_id or track_number"]);
    exit;
}

if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../../includes/env.php'; }
EnvLoader::load();
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

$query = '';
if (!empty($id)) {
    $query = 'id=eq.' . urlencode($id);
} else {
    $query = 'track_number=eq.' . urlencode($track_number);
}

$ch = curl_init("$supabaseUrl/rest/v1/parcels?$query&select=*");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

if ($http_code >= 200 && $http_code < 300) {
    $data = json_decode($response, true);
    if (count($data) === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Parcel not found"]);
        curl_close($ch);
        exit;
    }

    $parcel = $data[0];

    // Helper to query Supabase REST easily
    function supabase_get($path) {
        global $supabaseUrl, $supabaseKey;
        $url = "$supabaseUrl/rest/v1/" . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json"
            ],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $d = json_decode($resp, true);
            return $d;
        }
        error_log("supabase_get failed ($path): code=$code err=$err resp=" . substr($resp,0,200));
        return null;
    }

    // Enrich parcel with related data where available
    $parcel_id = $parcel['id'] ?? null;
    $driver_id = $parcel['driver_id'] ?? null;
    $origin_id = $parcel['origin_outlet_id'] ?? null;
    $destination_id = $parcel['destination_outlet_id'] ?? null;

    $origin = null;
    $destination = null;
    $driver = null;
    $events = [];
    $payments = [];

    if (!empty($origin)) {}

    if (!empty($origin_id)) {
        $res = supabase_get("outlets?id=eq." . urlencode($origin_id) . "&select=*");
        if (!empty($res) && is_array($res)) $origin = $res[0];
    }

    if (!empty($destination_id)) {
        $res = supabase_get("outlets?id=eq." . urlencode($destination_id) . "&select=*");
        if (!empty($res) && is_array($res)) $destination = $res[0];
    }

    if (!empty($driver_id)) {
        $res = supabase_get("drivers?id=eq." . urlencode($driver_id) . "&select=*");
        if (!empty($res) && is_array($res)) $driver = $res[0];
    }

    if (!empty($parcel_id)) {
        $res = supabase_get("delivery_events?shipment_id=eq." . urlencode($parcel_id) . "&order=event_timestamp.desc&select=*");
        if (!empty($res) && is_array($res)) $events = $res;

        $res2 = supabase_get("payment_transactions?parcel_id=eq." . urlencode($parcel_id) . "&select=*");
        if (!empty($res2) && is_array($res2)) $payments = $res2;
    }

    // Build driver_info object to match frontend expectations
    $driver_info = null;
    if (!empty($driver)) {
        $driver_info = [
            'driver_id' => $driver['id'] ?? null,
            'driver_name' => $driver['driver_name'] ?? ($driver['full_name'] ?? null),
            'driver_phone' => $driver['driver_phone'] ?? ($driver['phone'] ?? null),
            'status' => $driver['status'] ?? null,
        ];
    }

    // Attach enriched items
    $parcel['origin_outlet'] = $origin ?? null;
    $parcel['destination_outlet'] = $destination ?? null;
    $parcel['driver_info'] = $driver_info ?? ($parcel['driver_info'] ?? null);
    $parcel['delivery_events'] = $events;
    $parcel['payment_transactions'] = $payments;

    echo json_encode([ 'success' => true, 'parcel' => $parcel ]);

} else {
    http_response_code($http_code);
    echo json_encode([
        "error" => "Supabase error",
        "http_code" => $http_code,
        "curl_error" => $curl_error,
        "response" => $response
    ]);
}

curl_close($ch);
?>
