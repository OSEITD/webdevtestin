<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: Please log in"]);
    exit;
}

$current_outlet_id = $_SESSION['outlet_id'] ?? null;
$company_id = $_SESSION['company_id'];
$userRole = $_SESSION['role'] ?? 'customer';

if (empty($current_outlet_id)) {
    if (in_array($userRole, ['admin', 'super_admin'])) {
        
        $showCompanyWide = true;
    } else {
        
        echo json_encode([
            "success" => true,
            "message" => "No outlet access assigned",
            "counts" => [
                "received" => 0,
                "dispatched" => 0,
                "pending" => 0,
                "at_outlet" => 0
            ],
            "parcels" => []
        ]);
        exit;
    }
} else {
    $showCompanyWide = false;
}

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$today_start = date('Y-m-d') . 'T00:00:00';
$today_end = date('Y-m-d') . 'T23:59:59';

$counts = [
    'received' => 0,
    'dispatched' => 0,
    'pending' => 0,
    'at_outlet' => 0
];

function makeApiCall($url, $supabaseKey) {
    $ch = curl_init($url);
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
    curl_close($ch);
    
    if ($http_code >= 200 && $http_code < 300) {
        return json_decode($response, true);
    }
    return [];
}

$newly_dropped_url = "$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$current_outlet_id&company_id=eq.$company_id&status=in.(confirmed,pending)&select=*";
$newly_dropped_parcels = makeApiCall($newly_dropped_url, $supabaseKey);

$completed_parcel_list_url = "$supabaseUrl/rest/v1/parcel_list?status=eq.completed&company_id=eq.$company_id&select=parcel_id";
$completed_parcel_list = makeApiCall($completed_parcel_list_url, $supabaseKey);
$completed_parcel_ids = array_column($completed_parcel_list, 'parcel_id');

$returned_parcels = [];
if (!empty($completed_parcel_ids)) {
    $ids_str = implode(',', array_map(function($id) { return "'$id'"; }, $completed_parcel_ids));
    $returned_parcels_url = "$supabaseUrl/rest/v1/parcels?id=in.($ids_str)&status=eq.at_outlet&destination_outlet_id=eq.$current_outlet_id&company_id=eq.$company_id&select=*";
    $returned_parcels = makeApiCall($returned_parcels_url, $supabaseKey);
}

$delivered_not_collected_url = "$supabaseUrl/rest/v1/parcels?status=eq.at_outlet&destination_outlet_id=eq.$current_outlet_id&company_id=eq.$company_id&select=*";
$delivered_not_collected_parcels = makeApiCall($delivered_not_collected_url, $supabaseKey);

$all_parcels = [];
$parcel_ids_seen = [];

function addParcelsUnique(&$all_parcels, &$parcel_ids_seen, $parcels) {
    foreach ($parcels as $parcel) {
        if (!in_array($parcel['id'], $parcel_ids_seen)) {
            $all_parcels[] = $parcel;
            $parcel_ids_seen[] = $parcel['id'];
        }
    }
}

addParcelsUnique($all_parcels, $parcel_ids_seen, $newly_dropped_parcels);
addParcelsUnique($all_parcels, $parcel_ids_seen, $returned_parcels);
addParcelsUnique($all_parcels, $parcel_ids_seen, $delivered_not_collected_parcels);

$counts['at_outlet'] = count($all_parcels);
$counts['received'] = 0; 
$counts['dispatched'] = 0;
$counts['pending'] = 0;

$driver_ids = array_filter(array_column($all_parcels, 'driver_id'));
$drivers_data = [];

error_log("🔥 Driver IDs found in parcels: " . json_encode($driver_ids));

if (!empty($driver_ids)) {
    $driver_ids_str = implode(',', array_map(function($id) { return "'$id'"; }, $driver_ids));
    $drivers_url = "$supabaseUrl/rest/v1/drivers?id=in.($driver_ids_str)&select=id,driver_name,driver_phone,status,vehicle_number,vehicle_type,license_number";
    
    error_log("🔥 Driver fetch URL: " . $drivers_url);
    
    $drivers_result = makeApiCall($drivers_url, $supabaseKey);
    
    error_log("🔥 Drivers API result: " . json_encode($drivers_result));
    
    
    foreach ($drivers_result as $driver) {
        $drivers_data[$driver['id']] = $driver;
    }
}

foreach ($all_parcels as &$parcel) {
    if (!empty($parcel['driver_id']) && isset($drivers_data[$parcel['driver_id']])) {
        $parcel['driver_info'] = $drivers_data[$parcel['driver_id']];
        error_log("🔥 Driver info added for parcel " . $parcel['track_number'] . ": " . json_encode($parcel['driver_info']));
    } else {
        $parcel['driver_info'] = null;
        if (!empty($parcel['driver_id'])) {
            error_log("🔥 Driver ID " . $parcel['driver_id'] . " found but no driver data retrieved for parcel " . $parcel['track_number']);
        }
    }
}

$counts['received'] = 0;
$counts['dispatched'] = 0;
$counts['pending'] = 0;
$counts['at_outlet'] = count($all_parcels);

echo json_encode([
    'success' => true,
    'counts' => $counts,
    'parcels' => $all_parcels,
    'detailed_data' => [
        'at_outlet' => $all_parcels,
        'received_today' => [],
        'dispatched_today' => [],
        'pending' => []
    ],
    'debug_info' => [
        'current_outlet_id' => $current_outlet_id,
        'company_id' => $company_id,
        'user_role' => $userRole,
        'today_range' => [$today_start, $today_end]
    ]
]);
?>
