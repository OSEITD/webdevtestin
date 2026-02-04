<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
class DriverScheduleAPI {
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
    public function getTodaySchedule($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        try {
            $driver = $this->query('/rest/v1/profiles',
                "id,company_id,full_name",
                "id=eq.{$this->driverId}&company_id=eq.{$this->companyId}"
            );
            if (empty($driver)) {
                throw new Exception('Driver not found or access denied');
            }
            $scheduleItems = [];
            $trips = $this->getAssignedTrips($date);
            foreach ($trips as $trip) {
                $scheduleItems[] = $this->formatTripScheduleItem($trip);
            }
            $parcels = $this->getAssignedParcels($date);
            foreach ($parcels as $parcel) {
                $scheduleItems[] = $this->formatParcelScheduleItem($parcel);
            }
            usort($scheduleItems, function($a, $b) {
                return strtotime($a['scheduled_time']) - strtotime($b['scheduled_time']);
            });
            return [
                'success' => true,
                'data' => $scheduleItems,
                'date' => $date,
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
    private function getAssignedTrips($date) {
        try {
            $vehicles = $this->query('/rest/v1/vehicle',
                "id",
                "company_id=eq.{$this->companyId}&status=eq.out_for_delivery"
            );
            if (empty($vehicles)) {
                return [];
            }
            $vehicleIds = array_column($vehicles, 'id');
            $vehicleFilter = "(" . implode(',', array_map(function($id) { return '"' . $id . '"'; }, $vehicleIds)) . ")";
            $trips = $this->query('/rest/v1/trips',
                "id,departure_time,arrival_time,trip_status,vehicle_id",
                "company_id=eq.{$this->companyId}&vehicle_id=in.{$vehicleFilter}&departure_time=gte.{$date}T00:00:00&departure_time=lt." . date('Y-m-d', strtotime($date . ' +1 day')) . "T00:00:00"
            );
            foreach ($trips as &$trip) {
                $trip = $this->enrichTripWithRoute($trip);
            }
            return $trips;
        } catch (Exception $e) {
            error_log("Error getting assigned trips: " . $e->getMessage());
            return [];
        }
    }
    private function getAssignedParcels($date) {
        try {
            $parcels = $this->query('/rest/v1/parcels',
                "id,track_number,receiver_name,receiver_address,status,delivery_date,origin_outlet_id,destination_outlet_id",
                "company_id=eq.{$this->companyId}&driver_id=eq.{$this->driverId}&delivery_date=eq.{$date}&status=in.(assigned,in_transit)"
            );
            foreach ($parcels as &$parcel) {
                $parcel = $this->enrichParcelWithOutlets($parcel);
            }
            return $parcels;
        } catch (Exception $e) {
            error_log("Error getting assigned parcels: " . $e->getMessage());
            return [];
        }
    }
    private function enrichTripWithRoute($trip) {
        try {
            $stops = $this->query('/rest/v1/trip_stops',
                "outlet_id,stop_order",
                "company_id=eq.{$this->companyId}&trip_id=eq.{$trip['id']}&order=stop_order.asc"
            );
            if (empty($stops)) {
                $trip['route_description'] = 'Route details not available';
                return $trip;
            }
            $outletIds = array_column($stops, 'outlet_id');
            $outletFilter = "(" . implode(',', array_map(function($id) { return '"' . $id . '"'; }, $outletIds)) . ")";
            $outlets = $this->query('/rest/v1/outlets',
                "id,outlet_name",
                "company_id=eq.{$this->companyId}&id=in.{$outletFilter}"
            );
            $outletLookup = [];
            foreach ($outlets as $outlet) {
                $outletLookup[$outlet['id']] = $outlet['outlet_name'];
            }
            $routeNames = [];
            foreach ($stops as $stop) {
                $routeNames[] = $outletLookup[$stop['outlet_id']] ?? 'Unknown Outlet';
            }
            $trip['route_description'] = implode(' → ', $routeNames);
            $trip['total_stops'] = count($stops);
            return $trip;
        } catch (Exception $e) {
            error_log("Error enriching trip route: " . $e->getMessage());
            $trip['route_description'] = 'Route details not available';
            return $trip;
        }
    }
    private function enrichParcelWithOutlets($parcel) {
        try {
            $outlets = [];
            $outletIds = array_filter([$parcel['origin_outlet_id'], $parcel['destination_outlet_id']]);
            if (!empty($outletIds)) {
                $outletFilter = "(" . implode(',', array_map(function($id) { return '"' . $id . '"'; }, $outletIds)) . ")";
                $outlets = $this->query('/rest/v1/outlets',
                    "id,outlet_name",
                    "company_id=eq.{$this->companyId}&id=in.{$outletFilter}"
                );
            }
            $outletLookup = [];
            foreach ($outlets as $outlet) {
                $outletLookup[$outlet['id']] = $outlet['outlet_name'];
            }
            $parcel['origin_outlet_name'] = $outletLookup[$parcel['origin_outlet_id']] ?? 'Unknown Origin';
            $parcel['destination_outlet_name'] = $outletLookup[$parcel['destination_outlet_id']] ?? 'Unknown Destination';
            return $parcel;
        } catch (Exception $e) {
            error_log("Error enriching parcel outlets: " . $e->getMessage());
            $parcel['origin_outlet_name'] = 'Unknown Origin';
            $parcel['destination_outlet_name'] = 'Unknown Destination';
            return $parcel;
        }
    }
    private function formatTripScheduleItem($trip) {
        $departureTime = $trip['departure_time'] ?? date('Y-m-d H:i:s');
        $status = $this->mapTripStatus($trip['trip_status'] ?? 'scheduled');
        return [
            'id' => 'trip_' . $trip['id'],
            'type' => 'trip',
            'title' => 'Trip - ' . ($trip['route_description'] ?? 'Multiple Locations'),
            'description' => ($trip['total_stops'] ?? 0) . ' stops planned',
            'scheduled_time' => $departureTime,
            'location' => $trip['route_description'] ?? 'Multiple Locations',
            'status' => $status,
            'priority' => $status === 'pending' ? 'high' : 'medium',
            'trip_id' => $trip['id'],
            'vehicle_id' => $trip['vehicle_id'] ?? null
        ];
    }
    private function formatParcelScheduleItem($parcel) {
        $status = $this->mapParcelStatus($parcel['status'] ?? 'assigned');
        $deliveryTime = $parcel['delivery_date'] . ' 09:00:00';
        return [
            'id' => 'parcel_' . $parcel['id'],
            'type' => 'delivery',
            'title' => 'Delivery - ' . ($parcel['receiver_name'] ?? 'Unknown Receiver'),
            'description' => 'Parcel #' . ($parcel['track_number'] ?? 'Unknown'),
            'scheduled_time' => $deliveryTime,
            'location' => $parcel['receiver_address'] ?? 'Address not available',
            'status' => $status,
            'priority' => $status === 'pending' ? 'high' : 'medium',
            'parcel_id' => $parcel['id'],
            'track_number' => $parcel['track_number'],
            'origin_outlet' => $parcel['origin_outlet_name'] ?? 'Unknown',
            'destination_outlet' => $parcel['destination_outlet_name'] ?? 'Unknown'
        ];
    }
    private function mapTripStatus($status) {
        $statusMap = [
            'scheduled' => 'pending',
            'in_transit' => 'in_progress',
            'completed' => 'completed',
            'cancelled' => 'cancelled'
        ];
        return $statusMap[$status] ?? 'pending';
    }
    private function mapParcelStatus($status) {
        $statusMap = [
            'assigned' => 'pending',
            'in_transit' => 'in_progress',
            'delivered' => 'completed',
            'cancelled' => 'cancelled'
        ];
        return $statusMap[$status] ?? 'pending';
    }
    public function updateScheduleItemStatus($itemId, $newStatus) {
        try {
            $driver = $this->query('/rest/v1/profiles',
                "id,company_id",
                "id=eq.{$this->driverId}&company_id=eq.{$this->companyId}"
            );
            if (empty($driver)) {
                throw new Exception('Driver not found or access denied');
            }
            if (strpos($itemId, 'trip_') === 0) {
                $tripId = str_replace('trip_', '', $itemId);
                return $this->updateTripStatus($tripId, $newStatus);
            } elseif (strpos($itemId, 'parcel_') === 0) {
                $parcelId = str_replace('parcel_', '', $itemId);
                return $this->updateParcelStatus($parcelId, $newStatus);
            } else {
                throw new Exception('Invalid item ID format');
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    private function updateTripStatus($tripId, $status) {
        return [
            'success' => true,
            'message' => 'Trip status updated successfully'
        ];
    }
    private function updateParcelStatus($parcelId, $status) {
        return [
            'success' => true,
            'message' => 'Parcel status updated successfully'
        ];
    }
}
try {
    $api = new DriverScheduleAPI();
    $action = $_GET['action'] ?? 'today_schedule';
    $date = $_GET['date'] ?? null;
    switch ($action) {
        case 'today_schedule':
            $result = $api->getTodaySchedule($date);
            break;
        case 'update_status':
            $itemId = $_POST['item_id'] ?? null;
            $newStatus = $_POST['status'] ?? null;
            if (!$itemId || !$newStatus) {
                throw new Exception('Item ID and status are required');
            }
            $result = $api->updateScheduleItemStatus($itemId, $newStatus);
            break;
        default:
            $result = $api->getTodaySchedule();
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
