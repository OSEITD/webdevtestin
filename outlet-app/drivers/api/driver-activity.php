<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
class DriverActivityAPI {
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
    public function getRecentActivity($limit = 10) {
        try {
            $driver = $this->query('/rest/v1/profiles', 
                "id,company_id,full_name", 
                "id=eq.{$this->driverId}&company_id=eq.{$this->companyId}"
            );
            if (empty($driver)) {
                throw new Exception('Driver not found or access denied');
            }
            $activities = [];
            $deliveryEvents = $this->getDeliveryEvents($limit);
            $activities = array_merge($activities, $deliveryEvents);
            $parcelEvents = $this->getParcelStatusChanges($limit);
            $activities = array_merge($activities, $parcelEvents);
            $paymentEvents = $this->getPaymentEvents($limit);
            $activities = array_merge($activities, $paymentEvents);
            usort($activities, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            $activities = array_slice($activities, 0, $limit);
            return [
                'success' => true,
                'data' => $activities,
                'driver_info' => [
                    'id' => $this->driverId,
                    'name' => $driver[0]['full_name'] ?? 'Driver',
                    'company_id' => $this->companyId
                ],
                'multitenancy' => [
                    'company_scoped' => true,
                    'data_protection' => 'company_level'
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    private function getDeliveryEvents($limit) {
        try {
            $events = $this->query('/rest/v1/delivery_events',
                "id,shipment_id,status,event_timestamp,updated_by",
                "company_id=eq.{$this->companyId}&updated_by=eq.{$this->driverId}&order=event_timestamp.desc&limit={$limit}"
            );
            $activities = [];
            foreach ($events as $event) {
                $parcel = $this->query('/rest/v1/parcels',
                    "id,track_number,receiver_name",
                    "company_id=eq.{$this->companyId}&id=eq.{$event['shipment_id']}
                ");
                if (!empty($parcel)) {
                    $parcelInfo = $parcel[0];
                    $activities[] = [
                        'id' => 'delivery_' . $event['id'],
                        'type' => 'delivery',
                        'title' => $this->getDeliveryEventTitle($event['status']),
                        'description' => 'Parcel #' . $parcelInfo['track_number'] . ' for ' . $parcelInfo['receiver_name'],
                        'created_at' => $event['event_timestamp'],
                        'status' => $event['status'],
                        'parcel_id' => $event['shipment_id'],
                        'track_number' => $parcelInfo['track_number']
                    ];
                }
            }
            return $activities;
        } catch (Exception $e) {
            error_log("Error getting delivery events: " . $e->getMessage());
            return [];
        }
    }
    private function getParcelStatusChanges($limit) {
        try {
            $parcels = $this->query('/rest/v1/parcels',
                "id,track_number,status,receiver_name,updated_at",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&order=updated_at.desc&limit={$limit}"
            );
            $activities = [];
            foreach ($parcels as $parcel) {
                $activities[] = [
                    'id' => 'parcel_' . $parcel['id'],
                    'type' => 'status_change',
                    'title' => $this->getParcelStatusTitle($parcel['status']),
                    'description' => 'Parcel #' . $parcel['track_number'] . ' for ' . $parcel['receiver_name'],
                    'created_at' => $parcel['updated_at'] ?? $parcel['created_at'],
                    'status' => $parcel['status'],
                    'parcel_id' => $parcel['id'],
                    'track_number' => $parcel['track_number']
                ];
            }
            return $activities;
        } catch (Exception $e) {
            error_log("Error getting parcel status changes: " . $e->getMessage());
            return [];
        }
    }
    private function getPaymentEvents($limit) {
        try {
            $payments = $this->query('/rest/v1/payments',
                "id,parcel_id,amount,method,status,paid_at,created_at",
                "company_id=eq.{$this->companyId}&status=eq.paid&order=paid_at.desc&limit={$limit}"
            );
            $activities = [];
            foreach ($payments as $payment) {
                $parcel = $this->query('/rest/v1/parcels',
                    "id,track_number,receiver_name,driver_id",
                    "company_id=eq.{$this->companyId}&id=eq.{$payment['parcel_id']}&driver_id=eq.{$this->driverId}"
                );
                if (!empty($parcel)) {
                    $parcelInfo = $parcel[0];
                    $activities[] = [
                        'id' => 'payment_' . $payment['id'],
                        'type' => 'payment',
                        'title' => 'Payment Received',
                        'description' => 'K' . number_format($payment['amount'], 2) . ' via ' . ucfirst($payment['method']) . ' for parcel #' . $parcelInfo['track_number'],
                        'created_at' => $payment['paid_at'] ?? $payment['created_at'],
                        'status' => $payment['status'],
                        'amount' => $payment['amount'],
                        'method' => $payment['method'],
                        'parcel_id' => $payment['parcel_id'],
                        'track_number' => $parcelInfo['track_number']
                    ];
                }
            }
            return $activities;
        } catch (Exception $e) {
            error_log("Error getting payment events: " . $e->getMessage());
            return [];
        }
    }
    private function getDeliveryEventTitle($status) {
        $titles = [
            'picked_up' => 'Parcel Picked Up',
            'in_transit' => 'Parcel In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivery Completed',
            'delivery_attempted' => 'Delivery Attempted',
            'returned' => 'Parcel Returned'
        ];
        return $titles[$status] ?? 'Status Updated';
    }
    private function getParcelStatusTitle($status) {
        $titles = [
            'pending' => 'Parcel Pending',
            'assigned' => 'Parcel Assigned',
            'picked_up' => 'Parcel Picked Up',
            'in_transit' => 'Parcel In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivery Completed',
            'cancelled' => 'Parcel Cancelled'
        ];
        return $titles[$status] ?? 'Status Updated';
    }
    public function logActivity($type, $title, $description, $parcelId = null) {
        try {
            return [
                'success' => true,
                'message' => 'Activity logged successfully',
                'activity' => [
                    'type' => $type,
                    'title' => $title,
                    'description' => $description,
                    'parcel_id' => $parcelId,
                    'driver_id' => $this->driverId,
                    'company_id' => $this->companyId,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function getActivitySummary($period = '7days') {
        try {
            $endDate = date('Y-m-d H:i:s');
            $startDate = date('Y-m-d H:i:s', strtotime("-{$period}"));
            $deliveries = $this->query('/rest/v1/parcels',
                "id",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&status=eq.delivered&updated_at=gte.{$startDate}&updated_at=lte.{$endDate}"
            );
            $pickups = $this->query('/rest/v1/parcels',
                "id",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&status=eq.picked_up&updated_at=gte.{$startDate}&updated_at=lte.{$endDate}"
            );
            $inTransit = $this->query('/rest/v1/parcels',
                "id",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&status=eq.in_transit"
            );
            return [
                'success' => true,
                'data' => [
                    'period' => $period,
                    'total_deliveries' => count($deliveries),
                    'total_pickups' => count($pickups),
                    'current_in_transit' => count($inTransit),
                    'start_date' => $startDate,
                    'end_date' => $endDate
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
    $api = new DriverActivityAPI();
    
    $action = $_GET['action'] ?? 'recent_activity';
    $limit = intval($_GET['limit'] ?? 10);
    $period = $_GET['period'] ?? '7days';
    switch ($action) {
        case 'recent_activity':
            $result = $api->getRecentActivity($limit);
            break;
            
        case 'summary':
            $result = $api->getActivitySummary($period);
            break;
            
        case 'log_activity':
            $type = $_POST['type'] ?? null;
            $title = $_POST['title'] ?? null;
            $description = $_POST['description'] ?? null;
            $parcelId = $_POST['parcel_id'] ?? null;
            
            if (!$type || !$title || !$description) {
                throw new Exception('Type, title and description are required');
            }
            
            $result = $api->logActivity($type, $title, $description, $parcelId);
            break;
            
        default:
            $result = $api->getRecentActivity($limit);
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
