<?php
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../includes/auth_guard.php';

auth_guard();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$company_id = $_SESSION['company_id'];
$outlet_id = $_SESSION['outlet_id'];

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$today_start = date('Y-m-d') . 'T00:00:00';
$today_end = date('Y-m-d') . 'T23:59:59';
$week_start = date('Y-m-d', strtotime('-7 days')) . 'T00:00:00';
$month_start = date('Y-m-d', strtotime('-30 days')) . 'T00:00:00';

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

$payments_today = makeApiCall("$supabaseUrl/rest/v1/payments?created_at=gte.$today_start&created_at=lte.$today_end&status=eq.paid&select=amount,method", $supabaseKey);
$payments_week = makeApiCall("$supabaseUrl/rest/v1/payments?created_at=gte.$week_start&status=eq.paid&select=amount", $supabaseKey);
$payments_month = makeApiCall("$supabaseUrl/rest/v1/payments?created_at=gte.$month_start&status=eq.paid&select=amount", $supabaseKey);

$parcels_today = makeApiCall("$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outlet_id&created_at=gte.$today_start&created_at=lte.$today_end&select=id,parcel_weight,delivery_fee,status", $supabaseKey);
$parcels_week = makeApiCall("$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outlet_id&created_at=gte.$week_start&select=id", $supabaseKey);
$parcels_month = makeApiCall("$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outlet_id&created_at=gte.$month_start&select=id", $supabaseKey);

$deliveries_today = makeApiCall("$supabaseUrl/rest/v1/deliveries?origin_outlet_id=eq.$outlet_id&pickup_date=gte.$today_start&pickup_date=lte.$today_end&select=delivery_status,delivery_fee,estimated_delivery_date,actual_delivery_date", $supabaseKey);

$business_customers = makeApiCall("$supabaseUrl/rest/v1/business_customers?company_id=eq.$company_id&select=id,status,current_balance,credit_limit", $supabaseKey);
$guest_customers_today = makeApiCall("$supabaseUrl/rest/v1/guest_customers?company_id=eq.$company_id&created_at=gte.$today_start&created_at=lte.$today_end&select=id", $supabaseKey);

$analytics = [
    'revenue' => [
        'today' => array_sum(array_column($payments_today, 'amount')),
        'week' => array_sum(array_column($payments_week, 'amount')),
        'month' => array_sum(array_column($payments_month, 'amount')),
        'payment_methods_today' => []
    ],
    'parcels' => [
        'created_today' => count($parcels_today),
        'created_week' => count($parcels_week),
        'created_month' => count($parcels_month),
        'total_weight_today' => array_sum(array_column($parcels_today, 'parcel_weight')),
        'status_breakdown' => []
    ],
    'deliveries' => [
        'dispatched_today' => count($deliveries_today),
        'on_time_delivery_rate' => 0,
        'delivery_status_breakdown' => []
    ],
    'customers' => [
        'total_business_customers' => count($business_customers),
        'active_business_customers' => count(array_filter($business_customers, function($c) { return $c['status'] === 'active'; })),
        'overlimit_customers' => count(array_filter($business_customers, function($c) { return $c['current_balance'] > $c['credit_limit']; })),
        'new_guest_customers_today' => count($guest_customers_today)
    ]
];

foreach ($payments_today as $payment) {
    $method = $payment['method'];
    $analytics['revenue']['payment_methods_today'][$method] = ($analytics['revenue']['payment_methods_today'][$method] ?? 0) + $payment['amount'];
}

foreach ($parcels_today as $parcel) {
    $status = $parcel['status'];
    $analytics['parcels']['status_breakdown'][$status] = ($analytics['parcels']['status_breakdown'][$status] ?? 0) + 1;
}

$on_time_deliveries = 0;
foreach ($deliveries_today as $delivery) {
    $status = $delivery['delivery_status'];
    $analytics['deliveries']['delivery_status_breakdown'][$status] = ($analytics['deliveries']['delivery_status_breakdown'][$status] ?? 0) + 1;
    
    if ($delivery['actual_delivery_date'] && $delivery['estimated_delivery_date']) {
        if (strtotime($delivery['actual_delivery_date']) <= strtotime($delivery['estimated_delivery_date'])) {
            $on_time_deliveries++;
        }
    }
}

if (count($deliveries_today) > 0) {
    $analytics['deliveries']['on_time_delivery_rate'] = round(($on_time_deliveries / count($deliveries_today)) * 100, 2);
}

$analytics['growth_rates'] = [
    'parcels_week_vs_prev' => 0,
    'revenue_week_vs_prev' => 0,
];

echo json_encode([
    'success' => true,
    'analytics' => $analytics,
    'last_updated' => date('c')
]);
?>
