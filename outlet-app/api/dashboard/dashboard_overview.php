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
$outlet_id = $_SESSION['outlet_id'] ?? null;

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

$today_start = date('Y-m-d') . 'T00:00:00';
$today_end = date('Y-m-d') . 'T23:59:59';
$week_start = date('Y-m-d', strtotime('-7 days')) . 'T00:00:00';
$current_time = date('c');

function makeApiCall($url, $supabaseKey) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
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
    error_log("API Call failed: $url - HTTP $http_code");
    return [];
}

try {
    
    $dashboardData = [
        'parcels_overview' => [],
        'trip_status' => [],
        'driver_availability' => [],
        'revenue_snapshot' => [],
        'last_updated' => $current_time
    ];

    if (!$outlet_id) {
        throw new Exception('No outlet assigned to user');
    }

    
    
    
    $parcels_pending = makeApiCall(
        "$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outlet_id&status=in.(pending,At Outlet)&select=id,status,parcel_value,cod_amount,delivery_fee,created_at,track_number", 
        $supabaseKey
    );
    
    
    $parcels_in_transit_query = "$supabaseUrl/rest/v1/parcel_list?outlet_id=eq.$outlet_id&status=eq.in_transit&select=parcel_id,parcels(id,track_number,status,parcel_value,cod_amount,delivery_fee)";
    $parcels_in_transit_data = makeApiCall($parcels_in_transit_query, $supabaseKey);
    $parcels_in_transit = [];
    foreach ($parcels_in_transit_data as $item) {
        if ($item['parcels']) {
            $parcels_in_transit[] = $item['parcels'];
        }
    }
    
    
    $parcels_completed = makeApiCall(
        "$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outlet_id&status=eq.delivered&delivery_date=eq." . date('Y-m-d') . "&select=id,status,parcel_value,cod_amount,delivery_fee,created_at,track_number", 
        $supabaseKey
    );
    
    
    $today_date = date('Y-m-d');
    $parcels_all_active = makeApiCall(
        "$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outlet_id&status=in.(pending,At Outlet,Out for Delivery)&select=id,track_number,estimated_delivery_date,special_instructions,status", 
        $supabaseKey
    );
    
    $parcels_delayed_urgent = [];
    foreach ($parcels_all_active as $parcel) {
        $is_delayed = $parcel['estimated_delivery_date'] && $parcel['estimated_delivery_date'] < $today_date;
        $is_urgent = stripos($parcel['special_instructions'] ?? '', 'urgent') !== false;
        
        if ($is_delayed || $is_urgent) {
            $parcels_delayed_urgent[] = $parcel;
        }
    }

    $dashboardData['parcels_overview'] = [
        'pending_at_outlet' => count($parcels_pending),
        'in_transit' => count($parcels_in_transit),
        'completed_delivered' => count($parcels_completed),
        'delayed_urgent' => count($parcels_delayed_urgent),
        'details' => [
            'pending' => $parcels_pending,
            'in_transit' => $parcels_in_transit,
            'completed' => $parcels_completed,
            'delayed_urgent' => $parcels_delayed_urgent
        ]
    ];

    
    
    
    $upcoming_trips = makeApiCall(
        "$supabaseUrl/rest/v1/trip_stops?outlet_id=eq.$outlet_id&departure_time=gte.$current_time&select=*,trips(id,trip_status,departure_time,arrival_time,vehicle(name,number)),parcel_list(count)", 
        $supabaseKey
    );
    
    
    $intransit_trips = makeApiCall(
        "$supabaseUrl/rest/v1/trip_stops?outlet_id=eq.$outlet_id&select=*,trips!inner(id,trip_status,departure_time,arrival_time,vehicle(name,number))&trips.trip_status=eq.in_transit", 
        $supabaseKey
    );
    
    
    $completed_trips = makeApiCall(
        "$supabaseUrl/rest/v1/trip_stops?outlet_id=eq.$outlet_id&arrival_time=gte.$today_start&arrival_time=lte.$today_end&select=*,trips!inner(id,trip_status,departure_time,arrival_time,vehicle(name,number))&trips.trip_status=eq.completed", 
        $supabaseKey
    );

    $dashboardData['trip_status'] = [
        'upcoming_trips' => count($upcoming_trips),
        'in_transit_trips' => count($intransit_trips),
        'completed_trips_today' => count($completed_trips),
        'details' => [
            'upcoming' => $upcoming_trips,
            'in_transit' => $intransit_trips,
            'completed' => $completed_trips
        ]
    ];

    
    
    
    $all_drivers = makeApiCall(
        "$supabaseUrl/rest/v1/drivers?company_id=eq.$company_id&select=id,status,driver_name,driver_phone", 
        $supabaseKey
    );
    
    
    $active_trips = makeApiCall(
        "$supabaseUrl/rest/v1/trips?trip_status=in.(scheduled,in_transit)&select=id,vehicle(id,name),outlet_manager_id,profiles(full_name)", 
        $supabaseKey
    );
    
    $available_drivers = array_filter($all_drivers, function($d) { return $d['status'] === 'available'; });
    $unavailable_drivers = array_filter($all_drivers, function($d) { return $d['status'] === 'unavailable'; });
    
    $dashboardData['driver_availability'] = [
        'total_drivers' => count($all_drivers),
        'available' => count($available_drivers),
        'unavailable' => count($unavailable_drivers),
        'assigned_to_trips' => count($active_trips),
        'details' => [
            'available_drivers' => $available_drivers,
            'unavailable_drivers' => $unavailable_drivers,
            'active_trips' => $active_trips
        ]
    ];

    
    
    
    // fetch transactions from payment_transactions table scoped to company and outlet
    $todays_payments = makeApiCall(
        "$supabaseUrl/rest/v1/payment_transactions?company_id=eq.$company_id&outlet_id=eq.$outlet_id&status=eq.successful&paid_at=gte.$today_start&paid_at=lte.$today_end&select=amount,payment_method,parcel_id", 
        $supabaseKey
    );

    $outlet_payments_today = $todays_payments; // already filtered by outlet

    $weeks_payments = makeApiCall(
        "$supabaseUrl/rest/v1/payment_transactions?company_id=eq.$company_id&outlet_id=eq.$outlet_id&status=eq.successful&paid_at=gte.$week_start&paid_at=lte.$today_end&select=amount,payment_method,parcel_id", 
        $supabaseKey
    );

    $outlet_payments_week = $weeks_payments;
    
    
    $cod_collections = makeApiCall(
        "$supabaseUrl/rest/v1/parcels?origin_outlet_id=eq.$outlet_id&cod_amount=gt.0&status=eq.delivered&delivery_date=eq." . date('Y-m-d') . "&select=cod_amount,track_number", 
        $supabaseKey
    );
    
    
    $payment_methods = [];
    foreach ($outlet_payments_today as $payment) {
        $method = $payment['method'] ?? 'unknown';
        $payment_methods[$method] = ($payment_methods[$method] ?? 0) + $payment['amount'];
    }

    $dashboardData['revenue_snapshot'] = [
        'total_today' => array_sum(array_column($outlet_payments_today, 'amount')),
        'total_week' => array_sum(array_column($outlet_payments_week, 'amount')),
        'cod_collected_today' => array_sum(array_column($cod_collections, 'cod_amount')),
        'payment_method_breakdown' => $payment_methods,
        'transaction_count_today' => count($outlet_payments_today),
        'cod_transaction_count' => count($cod_collections),
        'details' => [
            'todays_payments' => $outlet_payments_today,
            'cod_collections' => $cod_collections
        ]
    ];

    echo json_encode([
        'success' => true,
        'data' => $dashboardData,
        'outlet_id' => $outlet_id,
        'company_id' => $company_id
    ]);

} catch (Exception $e) {
    error_log("Dashboard Overview Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'data' => [
            'parcels_overview' => [
                'pending_at_outlet' => 0,
                'in_transit' => 0,
                'completed_delivered' => 0,
                'delayed_urgent' => 0
            ],
            'trip_status' => [
                'upcoming_trips' => 0,
                'in_transit_trips' => 0,
                'completed_trips_today' => 0
            ],
            'driver_availability' => [
                'total_drivers' => 0,
                'available' => 0,
                'unavailable' => 0,
                'assigned_to_trips' => 0
            ],
            'revenue_snapshot' => [
                'total_today' => 0,
                'total_week' => 0,
                'cod_collected_today' => 0,
                'payment_method_breakdown' => [],
                'transaction_count_today' => 0
            ]
        ]
    ]);
}
?>
