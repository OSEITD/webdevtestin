<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/OutletAwareSupabaseHelper.php';

class AccurateDashboardAPI {
    private $supabase;
    private $companyId;
    private $outletId;
    private $currentDate;
    private $cacheTimeout = 60; 
    
    public function __construct() {
        $this->supabase = new OutletAwareSupabaseHelper();
        $this->companyId = $_SESSION['company_id'] ?? null;
        $this->outletId = $_SESSION['outlet_id'] ?? null;
        $this->currentDate = date('Y-m-d');
        
        if (!$this->companyId) {
            throw new Exception('Company ID not found in session');
        }
    }
    
    public function getDashboardStats() {
        try {
            
            $cacheKey = 'dashboard_stats_' . $this->companyId;
            $cachedData = $this->getCache($cacheKey);
            
            if ($cachedData !== null) {
                return $cachedData;
            }
            
            
            $stats = [
                'parcels' => $this->getParcelStats(),
                'trips' => $this->getTripStats(),
                'vehicles' => $this->getVehicleStats(),
                'revenue' => $this->getRevenueStats(),
                'drivers' => $this->getDriverStats()
            ];
            
            
            $this->setCache($cacheKey, $stats);
            
            return $stats;
        } catch (Exception $e) {
            error_log('Dashboard stats error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function getCache($key) {
        if (!isset($_SESSION['dashboard_cache'])) {
            return null;
        }
        
        $cache = $_SESSION['dashboard_cache'][$key] ?? null;
        if (!$cache) {
            return null;
        }
        
        
        if (time() - $cache['timestamp'] > $this->cacheTimeout) {
            unset($_SESSION['dashboard_cache'][$key]);
            return null;
        }
        
        return $cache['data'];
    }
    
    private function setCache($key, $data) {
        if (!isset($_SESSION['dashboard_cache'])) {
            $_SESSION['dashboard_cache'] = [];
        }
        
        $_SESSION['dashboard_cache'][$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
        
        
        if (count($_SESSION['dashboard_cache']) > 3) {
            $oldest = array_keys($_SESSION['dashboard_cache'])[0];
            unset($_SESSION['dashboard_cache'][$oldest]);
        }
    }
    
    private function getParcelStats() {
        $stats = [
            'pending_at_outlet' => 0,
            'in_transit' => 0,
            'completed' => 0,
            'delayed_urgent' => 0,
            'at_outlet' => 0,
            'total_today' => 0
        ];
        
        
        $companyFilter = 'company_id=eq.' . urlencode($this->companyId);
        $allParcels = $this->supabase->get('parcels', 
            $companyFilter,
            'id,status,created_at,delivered_at'
        );
        
        if (empty($allParcels)) {
            return $stats;
        }
        
        $cutoffDate = date('Y-m-d', strtotime('-3 days'));
        
        
        foreach ($allParcels as $parcel) {
            $status = $parcel['status'] ?? '';
            $createdAt = $parcel['created_at'] ?? '';
            $deliveredAt = $parcel['delivered_at'] ?? '';
            
            
            switch ($status) {
                case 'at_outlet':
                    $stats['at_outlet']++;
                    break;
                case 'pending':
                case 'scheduled':
                case 'assigned':
                    $stats['pending_at_outlet']++;
                    break;
                case 'in_transit':
                    $stats['in_transit']++;
                    break;
                case 'delivered':
                    
                    if ($deliveredAt && strpos($deliveredAt, $this->currentDate) === 0) {
                        $stats['completed']++;
                    }
                    break;
            }
            
            
            if ($status !== 'delivered' && $createdAt && $createdAt < $cutoffDate . 'T00:00:00') {
                $stats['delayed_urgent']++;
            }
            
            
            if ($createdAt && strpos($createdAt, $this->currentDate) === 0) {
                $stats['total_today']++;
            }
        }
        
        return $stats;
    }
    
    private function getTripStats() {
        $stats = [
            'upcoming' => 0,
            'in_transit' => 0,
            'completed_today' => 0,
            'scheduled' => 0,
            'active' => 0
        ];
        
        $companyFilter = 'company_id=eq.' . urlencode($this->companyId);
        
        
        $allTrips = $this->supabase->get('trips', 
            $companyFilter,
            'id,trip_status,updated_at'
        );
        
        if (empty($allTrips)) {
            return $stats;
        }
        
        
        foreach ($allTrips as $trip) {
            $status = $trip['trip_status'] ?? '';
            $updatedAt = $trip['updated_at'] ?? '';
            
            switch ($status) {
                case 'scheduled':
                    $stats['scheduled']++;
                    $stats['upcoming']++; 
                    break;
                case 'accepted':
                    $stats['active']++;
                    break;
                case 'in_transit':
                    $stats['in_transit']++;
                    break;
                case 'completed':
                    
                    if ($updatedAt && strpos($updatedAt, $this->currentDate) === 0) {
                        $stats['completed_today']++;
                    }
                    break;
            }
        }
        
        return $stats;
    }
    
    private function getVehicleStats() {
        $stats = [
            'available' => 0,
            'unavailable' => 0,
            'assigned_to_trips' => 0,
            'out_for_delivery' => 0,
            'total' => 0
        ];
        
        $companyFilter = 'company_id=eq.' . urlencode($this->companyId);
        
        
        $allVehicles = $this->supabase->get('vehicle', 
            $companyFilter,
            'id,status'
        );
        
        if (empty($allVehicles)) {
            return $stats;
        }
        
        
        foreach ($allVehicles as $vehicle) {
            $status = $vehicle['status'] ?? '';
            $stats['total']++;
            
            switch ($status) {
                case 'available':
                    $stats['available']++;
                    break;
                case 'unavailable':
                    $stats['unavailable']++;
                    break;
                case 'out_for_delivery':
                    $stats['out_for_delivery']++;
                    $stats['assigned_to_trips']++; 
                    break;
            }
        }
        
        return $stats;
    }
    
    private function getDriverStats() {
        $stats = [
            'available' => 0,
            'unavailable' => 0,
            'on_trip' => 0,
            'total' => 0
        ];
        
        $companyFilter = 'company_id=eq.' . urlencode($this->companyId);
        
        
        $allDrivers = $this->supabase->get('drivers', 
            $companyFilter,
            'id,status,current_trip_id'
        );
        
        if (empty($allDrivers)) {
            return $stats;
        }
        
        
        foreach ($allDrivers as $driver) {
            $status = $driver['status'] ?? '';
            $currentTripId = $driver['current_trip_id'] ?? null;
            $stats['total']++;
            
            if ($currentTripId) {
                $stats['on_trip']++;
            } else {
                switch ($status) {
                    case 'available':
                        $stats['available']++;
                        break;
                    case 'unavailable':
                        $stats['unavailable']++;
                        break;
                }
            }
        }
        
        return $stats;
    }
    
    private function getRevenueStats() {
        $stats = [
            'today' => 0,
            'week' => 0,
            'month' => 0,
            'cod_collections' => 0,
            'transactions_today' => 0,
            'pending_payments' => 0
        ];
        
        $companyFilter = 'company_id=eq.' . urlencode($this->companyId);
        
        
        $allPayments = $this->supabase->get('payments', 
            $companyFilter,
            'amount,status,paid_at,method'
        );
        
        if (empty($allPayments)) {
            return $stats;
        }
        
        
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');
        
        
        foreach ($allPayments as $payment) {
            $amount = floatval($payment['amount'] ?? 0);
            $status = $payment['status'] ?? '';
            $paidAt = $payment['paid_at'] ?? '';
            $method = $payment['method'] ?? '';
            
            
            if ($status === 'pending') {
                $stats['pending_payments'] += $amount;
                continue;
            }
            
            
            if ($status !== 'paid' || !$paidAt) {
                continue;
            }
            
            $paidDate = substr($paidAt, 0, 10); 
            
            
            if ($paidDate === $this->currentDate) {
                $stats['today'] += $amount;
                $stats['transactions_today']++;
                
                
                if ($method === 'cod') {
                    $stats['cod_collections'] += $amount;
                }
            }
            
            
            if ($paidDate >= $weekStart && $paidDate <= $this->currentDate) {
                $stats['week'] += $amount;
            }
            
            
            if ($paidDate >= $monthStart && $paidDate <= $this->currentDate) {
                $stats['month'] += $amount;
            }
        }
        
        return $stats;
    }
}

try {
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
        throw new Exception('Authentication required');
    }
    
    $api = new AccurateDashboardAPI();
    $stats = $api->getDashboardStats();
    
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s'),
        'company_id' => $_SESSION['company_id'],
        'outlet_id' => $_SESSION['outlet_id'] ?? null,
        'debug' => [
            'api_version' => 'v2.0_accurate_schema',
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? 'unknown'
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Dashboard API error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'session_user_id' => $_SESSION['user_id'] ?? 'not_set',
            'session_company_id' => $_SESSION['company_id'] ?? 'not_set',
            'session_outlet_id' => $_SESSION['outlet_id'] ?? 'not_set'
        ]
    ]);
}
?>