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
            'received' => 0,
            'dispatched' => 0,
            'pending' => 0,
            'delivered' => 0
        ]);
        exit;
    }
} else {
    $showCompanyWide = false;
}
$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZycWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$headers = [
    "apikey: $supabaseKey",
    "Authorization: Bearer $supabaseKey",
    "Content-Type: application/json"
];

error_log("Outlet ID in parcel_counts.php: " . $outletId);

function fetchCount($url, $headers) {
    error_log("Fetching URL: " . $url);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers)
        ]
    ]);
    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        error_log("Failed to fetch data from URL: " . $url);
        return 0;
    }
    error_log("Response from URL: " . $response);
    $data = json_decode($response, true);
    return count($data);
}

$today = date('Y-m-d');

$receivedUrl = "$supabaseUrl/rest/v1/parcels?destination_outlet_id=eq.$outletId&delivery_date=eq.$today";
$receivedCount = fetchCount($receivedUrl, $headers);

$dispatchedUrl = "$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outletId&delivery_date=eq.$today";
$dispatchedCount = fetchCount($dispatchedUrl, $headers);

$pendingUrl = "$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outletId&status=eq.pending";
$pendingCount = fetchCount($pendingUrl, $headers);

echo json_encode(array(
    'received_today' => $receivedCount,
    'dispatched_today' => $dispatchedCount,
    'pending_dispatch' => $pendingCount
));
?>
