<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../includes/session_manager.php';

try {
    
    ob_clean();
    
    
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated'
        ]);
        ob_end_flush();
        exit();
    }

    
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    $outletId = $_SESSION['outlet_id'] ?? null;

    
    $supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
    $supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';
    $accessToken = $_SESSION['access_token'] ?? $supabaseKey;

    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ]
        ]
    ]);

    
    $today = date('Y-m-d');
    $todayStart = $today . 'T00:00:00';
    $todayEnd = $today . 'T23:59:59';

    
    $metrics = [
        'parcels_received_today' => ['count' => 0, 'details' => []],
        'parcels_dispatched_today' => ['count' => 0, 'details' => []],
        'parcels_delivered_today' => ['count' => 0, 'details' => []],
        'parcels_pending' => ['count' => 0, 'details' => []],
        'active_drivers' => 0,
        'revenue_today' => [
            'amount' => 0.00,
            'formatted' => 'K 0.00',
            'count' => 0,
            'details' => []
        ],
        'last_updated' => date('Y-m-d H:i:s')
    ];

    
    $companyFilter = $companyId ? "&company_id=eq.$companyId" : "";
    $outletFilter = $outletId ? "&origin_outlet_id=eq.$outletId" : "";

    
    function urlEncodeStatus($status) {
        return str_replace(' ', '%20', $status);
    }

    
    $uniqueParcelIds = [
        'received' => [],
        'dispatched' => [],
        'pending' => [],
        'delivered' => []
    ];

    
    $revenueToday = 0;
    $revenueCount = 0;
    $revenueDetails = [];

    
    
    
    $deliveredStatus = urlEncodeStatus('delivered');
    $atOutletStatus = urlEncodeStatus('At Outlet');
    $parcelsReceivedUrl = "$supabaseUrl/rest/v1/parcels?select=id,track_number,package_details,delivery_fee,calculated_delivery_fee,status,created_at&status=in.($deliveredStatus,$atOutletStatus)&created_at=gte.$todayStart&created_at=lte.$todayEnd$companyFilter$outletFilter";
    
    $parcelsReceivedData = file_get_contents($parcelsReceivedUrl, false, $context);
    $parcelsReceived = [];
    if ($parcelsReceivedData !== false) {
        $parcelsReceived = json_decode($parcelsReceivedData, true) ?: [];
        foreach ($parcelsReceived as $parcel) {
            $parcelId = $parcel['id'];
            $uniqueParcelIds['received'][$parcelId] = $parcel;
            
            
            if ($parcel['status'] === 'delivered') {
                $fee = floatval($parcel['delivery_fee'] ?? $parcel['calculated_delivery_fee'] ?? 0);
                if ($fee > 0) {
                    $revenueToday += $fee;
                    $revenueCount++;
                    $revenueDetails[] = [
                        'track_number' => $parcel['track_number'],
                        'fee' => $fee,
                        'source' => 'parcels_delivered',
                        'date' => $parcel['created_at']
                    ];
                }
            }
        }
    }
    
    
    $deliveriesReceivedUrl = "$supabaseUrl/rest/v1/deliveries?select=id,parcel_id,tracking_number,delivery_fee,delivery_status,created_at,actual_delivery_date&delivery_status=in.(delivered,at_outlet)&created_at=gte.$todayStart&created_at=lte.$todayEnd$companyFilter";
    if ($outletId) {
        $deliveriesReceivedUrl .= "&outlet_id=eq.$outletId";
    }
    
    $deliveriesReceivedData = file_get_contents($deliveriesReceivedUrl, false, $context);
    if ($deliveriesReceivedData !== false) {
        $deliveriesReceived = json_decode($deliveriesReceivedData, true) ?: [];
        foreach ($deliveriesReceived as $delivery) {
            $parcelId = $delivery['parcel_id'];
            
            
            if (!isset($uniqueParcelIds['received'][$parcelId])) {
                $uniqueParcelIds['received'][$parcelId] = [
                    'id' => $parcelId,
                    'track_number' => $delivery['tracking_number'],
                    'delivery_fee' => $delivery['delivery_fee'],
                    'status' => $delivery['delivery_status'],
                    'source' => 'deliveries_table'
                ];
            }
            
            
            if ($delivery['delivery_status'] === 'delivered' && 
                $delivery['actual_delivery_date'] >= $todayStart && 
                $delivery['actual_delivery_date'] <= $todayEnd) {
                $fee = floatval($delivery['delivery_fee'] ?? 0);
                if ($fee > 0) {
                    $revenueToday += $fee;
                    $revenueCount++;
                    $revenueDetails[] = [
                        'track_number' => $delivery['tracking_number'],
                        'fee' => $fee,
                        'source' => 'deliveries_completed',
                        'date' => $delivery['actual_delivery_date']
                    ];
                }
            }
        }
    }
    
    $metrics['parcels_received_today']['count'] = count($uniqueParcelIds['received']);
    $metrics['parcels_received_today']['details'] = array_slice(array_values($uniqueParcelIds['received']), 0, 10);

    
    
    
    $outForDeliveryStatus = urlEncodeStatus('Out for Delivery');
    $parcelsDispatchedUrl = "$supabaseUrl/rest/v1/parcels?select=id,track_number,package_details,status,created_at&status=eq.$outForDeliveryStatus&created_at=gte.$todayStart&created_at=lte.$todayEnd$companyFilter$outletFilter";
    
    $parcelsDispatchedData = file_get_contents($parcelsDispatchedUrl, false, $context);
    if ($parcelsDispatchedData !== false) {
        $parcelsDispatched = json_decode($parcelsDispatchedData, true) ?: [];
        foreach ($parcelsDispatched as $parcel) {
            $parcelId = $parcel['id'];
            $uniqueParcelIds['dispatched'][$parcelId] = $parcel;
        }
    }
    
    
    $deliveriesDispatchedUrl = "$supabaseUrl/rest/v1/deliveries?select=id,parcel_id,tracking_number,delivery_status,created_at&delivery_status=in.(out_for_delivery,in_transit)&created_at=gte.$todayStart&created_at=lte.$todayEnd$companyFilter";
    if ($outletId) {
        $deliveriesDispatchedUrl .= "&outlet_id=eq.$outletId";
    }
    
    $deliveriesDispatchedData = file_get_contents($deliveriesDispatchedUrl, false, $context);
    if ($deliveriesDispatchedData !== false) {
        $deliveriesDispatched = json_decode($deliveriesDispatchedData, true) ?: [];
        foreach ($deliveriesDispatched as $delivery) {
            $parcelId = $delivery['parcel_id'];
            
            
            if (!isset($uniqueParcelIds['dispatched'][$parcelId])) {
                $uniqueParcelIds['dispatched'][$parcelId] = [
                    'id' => $parcelId,
                    'track_number' => $delivery['tracking_number'],
                    'status' => $delivery['delivery_status'],
                    'source' => 'deliveries_table'
                ];
            }
        }
    }
    
    $metrics['parcels_dispatched_today']['count'] = count($uniqueParcelIds['dispatched']);
    $metrics['parcels_dispatched_today']['details'] = array_slice(array_values($uniqueParcelIds['dispatched']), 0, 10);

    
    $deliveriesCompletedUrl = "$supabaseUrl/rest/v1/deliveries?select=id,parcel_id,tracking_number,delivery_fee,delivery_status,actual_delivery_date&delivery_status=eq.delivered&actual_delivery_date=gte.$todayStart&actual_delivery_date=lte.$todayEnd$companyFilter";
    if ($outletId) {
        $deliveriesCompletedUrl .= "&outlet_id=eq.$outletId";
    }
    
    $deliveriesCompletedData = file_get_contents($deliveriesCompletedUrl, false, $context);
    if ($deliveriesCompletedData !== false) {
        $deliveriesCompleted = json_decode($deliveriesCompletedData, true) ?: [];
        foreach ($deliveriesCompleted as $delivery) {
            $parcelId = $delivery['parcel_id'];
            $uniqueParcelIds['delivered'][$parcelId] = [
                'id' => $parcelId,
                'track_number' => $delivery['tracking_number'],
                'delivery_fee' => $delivery['delivery_fee'],
                'status' => $delivery['delivery_status'],
                'delivered_date' => $delivery['actual_delivery_date'],
                'source' => 'deliveries_table'
            ];
            
            
            $fee = floatval($delivery['delivery_fee'] ?? 0);
            if ($fee > 0) {
                
                $alreadyCounted = false;
                foreach ($revenueDetails as $existing) {
                    if ($existing['track_number'] === $delivery['tracking_number']) {
                        $alreadyCounted = true;
                        break;
                    }
                }
                
                if (!$alreadyCounted) {
                    $revenueToday += $fee;
                    $revenueCount++;
                    $revenueDetails[] = [
                        'track_number' => $delivery['tracking_number'],
                        'fee' => $fee,
                        'source' => 'deliveries_today',
                        'date' => $delivery['actual_delivery_date']
                    ];
                }
            }
        }
    }
    
    $metrics['parcels_delivered_today']['count'] = count($uniqueParcelIds['delivered']);
    $metrics['parcels_delivered_today']['details'] = array_slice(array_values($uniqueParcelIds['delivered']), 0, 10);

    
    
    
    $pendingStatus = urlEncodeStatus('pending');
    $atOutletStatus = urlEncodeStatus('At Outlet');
    $parcelsPendingUrl = "$supabaseUrl/rest/v1/parcels?select=id,track_number,package_details,status,created_at&status=in.($pendingStatus,$atOutletStatus)$companyFilter$outletFilter";
    
    $parcelsPendingData = file_get_contents($parcelsPendingUrl, false, $context);
    if ($parcelsPendingData !== false) {
        $parcelsPending = json_decode($parcelsPendingData, true) ?: [];
        foreach ($parcelsPending as $parcel) {
            $parcelId = $parcel['id'];
            $uniqueParcelIds['pending'][$parcelId] = $parcel;
        }
    }
    
    
    $deliveriesPendingUrl = "$supabaseUrl/rest/v1/deliveries?select=id,parcel_id,tracking_number,delivery_status,created_at&delivery_status=in.(pending,scheduled)$companyFilter";
    if ($outletId) {
        $deliveriesPendingUrl .= "&outlet_id=eq.$outletId";
    }
    
    $deliveriesPendingData = file_get_contents($deliveriesPendingUrl, false, $context);
    if ($deliveriesPendingData !== false) {
        $deliveriesPending = json_decode($deliveriesPendingData, true) ?: [];
        foreach ($deliveriesPending as $delivery) {
            $parcelId = $delivery['parcel_id'];
            
            
            if (!isset($uniqueParcelIds['pending'][$parcelId])) {
                $uniqueParcelIds['pending'][$parcelId] = [
                    'id' => $parcelId,
                    'track_number' => $delivery['tracking_number'],
                    'status' => $delivery['delivery_status'],
                    'source' => 'deliveries_table'
                ];
            }
        }
    }
    
    $metrics['parcels_pending']['count'] = count($uniqueParcelIds['pending']);
    $metrics['parcels_pending']['details'] = array_slice(array_values($uniqueParcelIds['pending']), 0, 10);

    
    $metrics['revenue_today'] = [
        'amount' => round($revenueToday, 2),
        'formatted' => 'K ' . number_format($revenueToday, 2),
        'count' => $revenueCount,
        'details' => array_slice($revenueDetails, 0, 10)
    ];

    
    $parcelRevenueUrl = "$supabaseUrl/rest/v1/parcels?select=id,track_number,delivery_fee,calculated_delivery_fee,delivery_date&delivery_date=eq.$today&or=(delivery_fee.gt.0,calculated_delivery_fee.gt.0)$companyFilter$outletFilter";
    $parcelRevenueData = file_get_contents($parcelRevenueUrl, false, $context);
    if ($parcelRevenueData !== false) {
        $parcelRevenues = json_decode($parcelRevenueData, true) ?: [];
        foreach ($parcelRevenues as $parcel) {
            
            $alreadyCounted = false;
            foreach ($revenueDetails as $existing) {
                if ($existing['track_number'] === $parcel['track_number']) {
                    $alreadyCounted = true;
                    break;
                }
            }
            
            if (!$alreadyCounted) {
                $fee = floatval($parcel['delivery_fee'] ?? $parcel['calculated_delivery_fee'] ?? 0);
                if ($fee > 0) {
                    $revenueToday += $fee;
                    $revenueCount++;
                    $revenueDetails[] = [
                        'track_number' => $parcel['track_number'],
                        'fee' => $fee,
                        'source' => 'parcel_delivery_date',
                        'date' => $parcel['delivery_date']
                    ];
                }
            }
        }
    }

    
    $paymentsRevenueUrl = "$supabaseUrl/rest/v1/payments?select=id,parcel_id,amount,method,status,paid_at&status=eq.paid&paid_at=gte.$todayStart&paid_at=lte.$todayEnd";
    $paymentsRevenueData = file_get_contents($paymentsRevenueUrl, false, $context);
    if ($paymentsRevenueData !== false) {
        $paymentRevenues = json_decode($paymentsRevenueData, true) ?: [];
        foreach ($paymentRevenues as $payment) {
            $amount = floatval($payment['amount'] ?? 0);
            if ($amount > 0) {
                $revenueToday += $amount;
                $revenueCount++;
                $revenueDetails[] = [
                    'parcel_id' => $payment['parcel_id'],
                    'fee' => $amount,
                    'source' => 'payment_completed',
                    'date' => $payment['paid_at'],
                    'method' => $payment['method']
                ];
            }
        }
    }

    
    $metrics['revenue_today'] = [
        'amount' => round($revenueToday, 2),
        'formatted' => 'K ' . number_format($revenueToday, 2),
        'count' => $revenueCount,
        'details' => array_slice($revenueDetails, 0, 10)
    ];

    
    $activeDriversUrl = "$supabaseUrl/rest/v1/drivers?select=count&status=eq.available$companyFilter";
    if ($outletId) {
        $activeDriversUrl .= "&outlet_id=eq.$outletId";
    }
    $activeDriversData = file_get_contents($activeDriversUrl, false, $context);
    if ($activeDriversData !== false) {
        $activeDriversResult = json_decode($activeDriversData, true);
        if (isset($activeDriversResult[0]['count'])) {
            $metrics['active_drivers'] = intval($activeDriversResult[0]['count']);
        }
    }

    
    $response = [
        'success' => true,
        'metrics' => $metrics,
        'message' => 'Dashboard loaded successfully with real data',
        'user' => [
            'id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? 'outlet_manager',
            'outlet_id' => $_SESSION['outlet_id'] ?? null,
            'company_id' => $_SESSION['company_id'] ?? null
        ],
        'debug' => [
            'today' => $today,
            'company_filter' => $companyFilter,
            'outlet_filter' => $outletFilter,
            'status_mapping' => [
                'received_today' => [
                    'parcels_table' => ['delivered', 'At Outlet'],
                    'deliveries_table' => ['at_outlet']
                ],
                'dispatched_today' => [
                    'parcels_table' => ['Out for Delivery'],
                    'deliveries_table' => ['in_transit', 'out_for_delivery']
                ],
                'delivered_today' => [
                    'deliveries_table' => ['delivered']
                ],
                'pending_dispatch' => [
                    'parcels_table' => ['pending', 'At Outlet'],
                    'deliveries_table' => ['scheduled']
                ]
            ]
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
    ob_end_flush();

} catch (Exception $e) {
    
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
    ob_end_flush();
}
?>
