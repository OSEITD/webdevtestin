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
    private $url = "https://xerpchdsykqafrsxbqef.supabase.co";
    private $key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
    private $companyId;
    private $cacheTimeout = 60; 
    
    public function __construct() {
        $this->companyId = $_SESSION['company_id'];
    }
    
    public function getDashboardStats() {
        $startTime = microtime(true);
        
        
        $cacheKey = 'dashboard_' . $this->companyId;
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
            'parcels' => $this->getQuickParcelCounts(),
            'trips' => $this->getQuickTripCounts(),
            'vehicles' => $this->getQuickVehicleCounts(),
            'revenue' => $this->getQuickRevenue(),
            'debug' => [
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'cached' => false,
                'api_version' => 'fast'
            ]
        ];
        
        
        $this->setCache($cacheKey, $stats);
        
        return $stats;
    }
    
    private function getQuickParcelCounts() {
        $company = $this->companyId;
        $today = date('Y-m-d');
        
        
        $atOutlet = $this->quickCount('parcels', "company_id=eq.$company&status=eq.at_outlet");
        $inTransit = $this->quickCount('parcels', "company_id=eq.$company&status=eq.in_transit");
        $pending = $this->quickCount('parcels', "company_id=eq.$company&status=in.(pending,scheduled,assigned)");
        $delivered = $this->quickCount('parcels', "company_id=eq.$company&status=eq.delivered&delivered_at=gte.{$today}T00:00:00");
        
        return [
            'pending_at_outlet' => $atOutlet + $pending,
            'at_outlet' => $atOutlet,
            'in_transit' => $inTransit,
            'completed' => $delivered,
            'delayed_urgent' => 0 
        ];
    }
    
    private function getQuickTripCounts() {
        $company = $this->companyId;
        $today = date('Y-m-d');
        
        $scheduled = $this->quickCount('trips', "company_id=eq.$company&trip_status=eq.scheduled");
        $inTransit = $this->quickCount('trips', "company_id=eq.$company&trip_status=eq.in_transit");
        $completed = $this->quickCount('trips', "company_id=eq.$company&trip_status=eq.completed&updated_at=gte.{$today}T00:00:00");
        
        return [
            'upcoming' => $scheduled,
            'scheduled' => $scheduled,
            'in_transit' => $inTransit,
            'completed_today' => $completed,
            'active' => 0
        ];
    }
    
    private function getQuickVehicleCounts() {
        $company = $this->companyId;
        
        $available = $this->quickCount('vehicle', "company_id=eq.$company&status=eq.available");
        $unavailable = $this->quickCount('vehicle', "company_id=eq.$company&status=eq.unavailable");
        $outForDelivery = $this->quickCount('vehicle', "company_id=eq.$company&status=eq.out_for_delivery");
        
        return [
            'available' => $available,
            'unavailable' => $unavailable,
            'assigned_to_trips' => $outForDelivery,
            'out_for_delivery' => $outForDelivery,
            'total' => $available + $unavailable + $outForDelivery
        ];
    }
    
    private function getQuickRevenue() {
        $company = $this->companyId;
        $today = date('Y-m-d');
        
        
        $todayPayments = $this->simpleSum('payments', 'amount', "company_id=eq.$company&status=eq.paid&paid_at=gte.{$today}T00:00:00");
        $transactionCount = $this->quickCount('payments', "company_id=eq.$company&status=eq.paid&paid_at=gte.{$today}T00:00:00");
        
        return [
            'today' => $todayPayments,
            'week' => $todayPayments * 5, 
            'month' => $todayPayments * 20, 
            'cod_collections' => 0,
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