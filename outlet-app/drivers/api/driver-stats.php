<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
class DriverStatsAPI {
    private $url = "https://xerpchdsykqafrsxbqef.supabase.co";
    private $key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
    private $companyId;
    private $driverId;
    public function __construct() {
        if (isset($_SESSION['company_id']) && !empty($_SESSION['company_id'])) {
            $this->companyId = $_SESSION['company_id'];
        } else {
            $this->companyId = "7501f684-a827-46bd-9389-3cf850463eff";
        }
        $this->driverId = $_GET['driver_id'] ?? $_SESSION['user_id'] ?? null;
        if (!$this->driverId) {
            throw new Exception('Driver ID is required');
        }
    }
    private function query($endpoint, $select = "*", $filters = "") {
        $url = $this->url . $endpoint;
        if ($select !== "*") {
            $url .= "?select=" . urlencode($select);
            if ($filters) {
                $url .= "&" . $filters;
            }
        } elseif ($filters) {
            $url .= "?" . $filters;
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    "apikey: {$this->key}",
                    "Authorization: Bearer {$this->key}",
                    "Content-Type: application/json"
                ]
            ]
        ]);
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            error_log("Supabase query failed: $url");
            return [];
        }
        $data = json_decode($response, true);
        return $data !== null ? $data : [];
    }
    public function getTodayStats($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        try {
            $driver = $this->query('/rest/v1/profiles',
                "id,company_id",
                "id=eq.{$this->driverId}&company_id=eq.{$this->companyId}"
            );
            if (empty($driver)) {
                throw new Exception('Driver not found or access denied');
            }
            $todayDeliveries = $this->getTodayDeliveries($date);
            $pendingPickups = $this->getPendingPickups();
            $todayEarnings = $this->getTodayEarnings($date);
            return [
                'success' => true,
                'data' => [
                    'todayDeliveries' => $todayDeliveries,
                    'pendingPickups' => $pendingPickups,
                    'todayEarnings' => $todayEarnings,
                    'date' => $date,
                    'driver_id' => $this->driverId,
                    'company_scoped' => true
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [
                    'todayDeliveries' => 0,
                    'pendingPickups' => 0,
                    'todayEarnings' => 0
                ]
            ];
        }
    }
    private function getTodayDeliveries($date) {
        try {
            $deliveries = $this->query('/rest/v1/parcels',
                "id",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&status=eq.delivered&delivery_date=eq.{$date}"
            );
            return count($deliveries);
        } catch (Exception $e) {
            error_log("Error getting today's deliveries: " . $e->getMessage());
            return 0;
        }
    }
    private function getPendingPickups() {
        try {
            $pickups = $this->query('/rest/v1/parcels',
                "id",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&status=in.(assigned,pending)"
            );
            return count($pickups);
        } catch (Exception $e) {
            error_log("Error getting pending pickups: " . $e->getMessage());
            return 0;
        }
    }
    private function getTodayEarnings($date) {
        try {
            $deliveries = $this->query('/rest/v1/parcels',
                "delivery_fee,cod_amount",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&status=eq.delivered&delivery_date=eq.{$date}"
            );
            $totalEarnings = 0;
            foreach ($deliveries as $delivery) {
                $deliveryFee = floatval($delivery['delivery_fee'] ?? 0);
                $codAmount = floatval($delivery['cod_amount'] ?? 0);
                $earnings = ($deliveryFee * 0.20) + ($codAmount * 0.02);
                $totalEarnings += $earnings;
            }
            return round($totalEarnings, 2);
        } catch (Exception $e) {
            error_log("Error calculating today's earnings: " . $e->getMessage());
            return 0;
        }
    }
    public function getDriverPerformance($period = '7days') {
        try {
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$period}"));
            $deliveries = $this->query('/rest/v1/parcels',
                "id,delivery_date,status,delivery_fee",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&delivery_date=gte.{$startDate}&delivery_date=lte.{$endDate}"
            );
            $completedCount = 0;
            $totalRevenue = 0;
            $onTimeDeliveries = 0;
            foreach ($deliveries as $delivery) {
                if ($delivery['status'] === 'delivered') {
                    $completedCount++;
                    $totalRevenue += floatval($delivery['delivery_fee'] ?? 0);
                    if ($delivery['delivery_date'] <= date('Y-m-d')) {
                        $onTimeDeliveries++;
                    }
                }
            }
            $onTimeRate = $completedCount > 0 ? ($onTimeDeliveries / $completedCount) * 100 : 0;
            return [
                'success' => true,
                'data' => [
                    'period' => $period,
                    'total_deliveries' => count($deliveries),
                    'completed_deliveries' => $completedCount,
                    'total_revenue' => $totalRevenue,
                    'on_time_rate' => round($onTimeRate, 2),
                    'average_daily_deliveries' => round($completedCount / 7, 1)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function getEarningsBreakdown($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        try {
            $deliveries = $this->query('/rest/v1/parcels',
                "id,track_number,delivery_fee,cod_amount,receiver_name,delivery_date",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&status=eq.delivered&delivery_date=eq.{$date}"
            );
            $breakdown = [];
            $totalEarnings = 0;
            foreach ($deliveries as $delivery) {
                $deliveryFee = floatval($delivery['delivery_fee'] ?? 0);
                $codAmount = floatval($delivery['cod_amount'] ?? 0);
                $driverEarnings = ($deliveryFee * 0.20) + ($codAmount * 0.02);
                $totalEarnings += $driverEarnings;
                $breakdown[] = [
                    'parcel_id' => $delivery['id'],
                    'track_number' => $delivery['track_number'],
                    'receiver_name' => $delivery['receiver_name'],
                    'delivery_fee' => $deliveryFee,
                    'cod_amount' => $codAmount,
                    'driver_earnings' => round($driverEarnings, 2)
                ];
            }
            return [
                'success' => true,
                'data' => [
                    'date' => $date,
                    'total_earnings' => round($totalEarnings, 2),
                    'delivery_count' => count($deliveries),
                    'breakdown' => $breakdown
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
try {
    $api = new DriverStatsAPI();
    $action = $_GET['action'] ?? 'today_stats';
    $date = $_GET['date'] ?? null;
    $period = $_GET['period'] ?? '7days';
    switch ($action) {
        case 'today_stats':
            $result = $api->getTodayStats($date);
            break;
        case 'performance':
            $result = $api->getDriverPerformance($period);
            break;
        case 'earnings_breakdown':
            $result = $api->getEarningsBreakdown($date);
            break;
        default:
            $result = $api->getTodayStats();
    }
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'multitenancy' => [
            'company_scoped' => true,
            'data_protection' => 'company_level'
        ]
    ]);
}
?>
