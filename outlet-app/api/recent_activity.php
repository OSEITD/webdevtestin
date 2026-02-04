<?php

ob_start();
error_reporting(0); 

session_start();

ob_clean();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Please log in']);
    exit;
}

$outletId = $_SESSION['outlet_id'] ?? null;
$companyId = $_SESSION['company_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'customer';

if (empty($outletId)) {
    if (in_array($userRole, ['admin', 'super_admin']) && !empty($companyId)) {
        
        $showCompanyWide = true;
    } else {
        
        echo json_encode([
            'success' => true,
            'message' => 'No outlet access assigned',
            'data' => []
        ]);
        exit;
    }
} else {
    $showCompanyWide = false;
}
$supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';

$headers = [
    "apikey: $supabaseKey",
    "Authorization: Bearer $supabaseKey",
    "Content-Type: application/json"
];

if ($showCompanyWide) {
    
    $activityUrl = "$supabaseUrl/rest/v1/parcels?company_id=eq.$companyId&order=created_at.desc&limit=10&select=track_number,status,created_at,origin_outlet_name,destination_outlet_name,receiver_name,sender_name";
} else {
    
    $activityUrl = "$supabaseUrl/rest/v1/parcels?or=(origin_outlet_id.eq.$outletId,destination_outlet_id.eq.$outletId)&order=created_at.desc&limit=10&select=track_number,status,created_at,origin_outlet_name,destination_outlet_name,receiver_name,sender_name";
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers)
    ]
]);

$response = file_get_contents($activityUrl, false, $context);
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch recent activity', 'debug' => 'Network error']);
    exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from database', 'debug' => 'JSON decode error']);
    exit;
}

$activities = [];
if (is_array($data)) {
    foreach ($data as $parcel) {
        $activities[] = [
            'track_number' => $parcel['track_number'] ?? 'N/A',
            'status' => $parcel['status'] ?? 'Unknown',
            'timestamp' => $parcel['created_at'] ?? date('c'),
            'origin_outlet_name' => $parcel['origin_outlet_name'] ?? 'N/A',
            'destination_outlet_name' => $parcel['destination_outlet_name'] ?? '',
            'receiver_name' => $parcel['receiver_name'] ?? '',
        ];
    }
}

ob_clean();
echo json_encode([
    'success' => true,
    'data' => $activities,
    'count' => count($activities)
]);
?>
