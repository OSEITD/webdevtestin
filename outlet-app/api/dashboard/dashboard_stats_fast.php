<?php

ini_set('display_errors', 0);
error_reporting(0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && $error['type'] === E_ERROR) {
        
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Dashboard API timeout - Database queries are too slow',
            'debug' => [
                'error_type' => 'fatal_error',
                'execution_timeout' => true,
                'suggestion' => 'Check database connection and optimize queries'
            ]
        ]);
        exit;
    }
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required'
    ]);
    exit;
}

class LightweightDashboardAPI {
    private $url;
    private $key;
    private $companyId;
    private $outletId;
    private $cacheTimeout = 60;

    public function __construct() {
        if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../../includes/env.php'; }
        EnvLoader::load();
        $this->url = getenv('SUPABASE_URL');
        $this->key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');
        if (empty($this->url) || empty($this->key)) {
            throw new RuntimeException('Supabase credentials are not configured in outlet-app/.env');
        }
        $this->companyId = $_SESSION['company_id'];
        $this->outletId  = $_SESSION['outlet_id'] ?? null;
    }

    public function getDashboardStats() {
        $startTime = microtime(true);

        // Cache key is scoped to both company AND outlet so different outlets never share data
        $cacheKey = 'dashboard_' . $this->companyId . '_' . ($this->outletId ?? 'no_outlet');
        $cached = $this->getCache($cacheKey);
        if ($cached) {
            $cached['debug'] = [
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'cached' => true,
                'api_version' => 'fast'
            ];
            return $cached;
        }

        $stats = [
            'parcels'  => $this->getQuickParcelCounts(),
            'trips'    => $this->getQuickTripCounts(),
            'vehicles' => $this->getQuickVehicleCounts(),
            'revenue'  => $this->getQuickRevenue(),
            'debug'    => [
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'cached'     => false,
                'api_version' => 'fast'
            ]
        ];

        $this->setCache($cacheKey, $stats);
        return $stats;
    }

    private function getQuickParcelCounts() {
        $company = $this->companyId;
        $outlet  = $this->outletId;
        $today   = date('Y-m-d');

        // Outlet scope: parcels whose origin is this outlet
        // If no outlet_id in session fall back to company-wide so the screen is never blank
        $outletFilter = $outlet ? "&origin_outlet_id=eq.$outlet" : '';

        // Parcels physically at the outlet (pending / at_outlet / assigned to a trip not yet departed)
        $atOutletCount = $this->quickCount('parcels',
            "company_id=eq.$company{$outletFilter}&status=in.(pending,at_outlet,scheduled,assigned)");

        // Parcels currently on a moving trip originating from this outlet
        $inTransitCount = $this->quickCount('parcels',
            "company_id=eq.$company{$outletFilter}&status=in.(in_transit)");

        // Parcels delivered today that originated from this outlet
        $deliveredCount = $this->quickCount('parcels',
            "company_id=eq.$company{$outletFilter}&status=eq.delivered&delivered_at=gte.{$today}T00:00:00");

        // Parcels that are overdue (older than 3 days, not yet delivered)
        $cutoff = date('Y-m-d', strtotime('-3 days'));
        $delayedCount = $this->quickCount('parcels',
            "company_id=eq.$company{$outletFilter}&status=in.(pending,assigned)&created_at=lt.{$cutoff}T00:00:00");

        return [
            'pending_at_outlet' => $atOutletCount,
            'at_outlet'         => $atOutletCount,
            'in_transit'        => $inTransitCount,
            'completed'         => $deliveredCount,
            'delayed_urgent'    => $delayedCount,
        ];
    }

    private function getQuickTripCounts() {
        $company = $this->companyId;
        $outlet  = $this->outletId;
        $today   = date('Y-m-d');

        // Trips are scoped to this outlet as the origin
        $outletFilter = $outlet ? "&origin_outlet_id=eq.$outlet" : '';

        $scheduled = $this->quickCount('trips',
            "company_id=eq.$company{$outletFilter}&trip_status=eq.scheduled");
        $inTransit = $this->quickCount('trips',
            "company_id=eq.$company{$outletFilter}&trip_status=eq.in_transit");
        $completed = $this->quickCount('trips',
            "company_id=eq.$company{$outletFilter}&trip_status=eq.completed&updated_at=gte.{$today}T00:00:00");

        return [
            'upcoming'       => $scheduled,
            'scheduled'      => $scheduled,
            'in_transit'     => $inTransit,
            'completed_today' => $completed,
            'active'         => 0
        ];
    }

    private function getQuickVehicleCounts() {
        // Vehicles are company-wide assets; outlet managers see the full fleet
        $company = $this->companyId;

        $available      = $this->quickCount('vehicle', "company_id=eq.$company&status=eq.available");
        $unavailable    = $this->quickCount('vehicle', "company_id=eq.$company&status=eq.unavailable");
        $outForDelivery = $this->quickCount('vehicle', "company_id=eq.$company&status=eq.out_for_delivery");

        return [
            'available'        => $available,
            'unavailable'      => $unavailable,
            'assigned_to_trips' => $outForDelivery,
            'out_for_delivery' => $outForDelivery,
            'total'            => $available + $unavailable + $outForDelivery
        ];
    }
    
    private function getQuickRevenue() {
        $company = $this->companyId;
        $today = date('Y-m-d');
        $outlet = $_SESSION['outlet_id'] ?? null;
        $outletFilter = $outlet ? "&outlet_id=eq.$outlet" : '';

        // count successful payments from payment_transactions
        $paidFilter = "company_id=eq.$company" . $outletFilter . "&status=eq.successful&paid_at=gte.{$today}T00:00:00";
        $todayPayments = $this->simpleSum('payment_transactions', 'amount', $paidFilter);
        $transactionCount = $this->quickCount('payment_transactions', $paidFilter);

        // cod collections: amount where payment_method=eq.cod and same date
        $codFilter = $paidFilter . "&payment_method=eq.cod";
        $codCollected = $this->simpleSum('payment_transactions', 'amount', $codFilter);

        // week/month approximated by another query instead of multiplication
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekFilter = "company_id=eq.$company" . $outletFilter . "&status=eq.successful&paid_at=gte.{$weekStart}T00:00:00";
        $weekPayments = $this->simpleSum('payment_transactions', 'amount', $weekFilter);

        $monthStart = date('Y-m-01');
        $monthFilter = "company_id=eq.$company" . $outletFilter . "&status=eq.successful&paid_at=gte.{$monthStart}T00:00:00";
        $monthPayments = $this->simpleSum('payment_transactions', 'amount', $monthFilter);

        return [
            'today' => $todayPayments,
            'week' => $weekPayments,
            'month' => $monthPayments,
            'cod_collections' => $codCollected,
            'transactions_today' => $transactionCount,
            'pending_payments' => 0
        ];
    }
    
    private function quickCount($table, $filter) {
        $url = "{$this->url}/rest/v1/{$table}?{$filter}&select=id&limit=1000";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Prefer: count=exact'
                ],
                'timeout' => 3 
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if (!$response) {
            
            return 0;
        }
        
        $data = json_decode($response, true);
        return is_array($data) ? count($data) : 0;
    }
    
    private function simpleSum($table, $column, $filter) {
        $url = "{$this->url}/rest/v1/{$table}?{$filter}&select={$column}&limit=1000";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key
                ],
                'timeout' => 5
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if (!$response) {
            return 0;
        }
        
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return 0;
        }
        
        $sum = 0;
        foreach ($data as $row) {
            $sum += floatval($row[$column] ?? 0);
        }
        
        return $sum;
    }
    
    private function getCache($key) {
        if (!isset($_SESSION['quick_cache'])) {
            return null;
        }
        
        $cache = $_SESSION['quick_cache'][$key] ?? null;
        if (!$cache || (time() - $cache['time']) > $this->cacheTimeout) {
            return null;
        }
        
        return $cache['data'];
    }
    
    private function setCache($key, $data) {
        $_SESSION['quick_cache'][$key] = [
            'data' => $data,
            'time' => time()
        ];
    }
}

try {
    
    set_time_limit(30); 
    ini_set('display_errors', 0);
    
    $startTime = microtime(true);
    $api = new LightweightDashboardAPI();
    $stats = $api->getDashboardStats();
    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s'),
        'cached' => isset($_SESSION['quick_cache']),
        'debug' => [
            'api_version' => 'lightweight_optimized',
            'company_id' => $_SESSION['company_id'],
            'total_execution_time' => $executionTime . 'ms',
            'response_time' => 'optimized'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Dashboard temporarily unavailable: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'api_version' => 'lightweight_optimized',
            'error_type' => get_class($e),
            'execution_timeout' => 'possible'
        ]
    ]);
}
?>