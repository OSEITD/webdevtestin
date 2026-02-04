<?php
header('Content-Type: application/json');

class EnhancedDashboardDetailsAPI {
    private $url = "https://xerpchdsykqafrsxbqef.supabase.co";
    private $key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
    private $companyId = "7501f684-a827-46bd-9389-3cf850463eff";
    
    public function handleRequest() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        
        if ($requestMethod === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $type = $input['type'] ?? '';
            $limit = intval($input['limit'] ?? 50);
            $offset = intval($input['offset'] ?? 0);
        } else {
            
            $type = $_GET['type'] ?? '';
            $limit = intval($_GET['limit'] ?? 50);
            $offset = intval($_GET['offset'] ?? 0);
        }
        
        switch ($type) {
            case 'parcels':
                return $this->getParcels($limit, $offset);
            case 'urgent_parcels':
                return $this->getUrgentParcels($limit, $offset);
            case 'in_transit_parcels':
                return $this->getInTransitParcels($limit, $offset);
            case 'pending_parcels':
                return $this->getPendingParcels($limit, $offset);
            case 'completed_parcels':
                return $this->getCompletedParcels($limit, $offset);
            case 'trips':
                return $this->getTrips($limit, $offset);
            case 'trips_scheduled':
                return $this->getTripsScheduled($limit, $offset);
            case 'trips_in_transit':
                return $this->getTripsInTransit($limit, $offset);
            case 'vehicles':
                return $this->getVehicles($limit, $offset);
            case 'assigned_vehicles':
                return $this->getAssignedVehicles($limit, $offset);
            case 'available_vehicles':
                return $this->getAvailableVehicles($limit, $offset);
            default:
                return ['error' => 'Invalid type specified'];
        }
    }
    
    private function query($endpoint, $select = '*', $filters = '') {
        $url = $this->url . $endpoint;
        $queryParams = [];
        
        
        $queryParams[] = "select=" . urlencode($select);
        
        
        if ($filters) {
            $queryParams[] = $filters;
        }
        
        $url .= '?' . implode('&', $queryParams);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json'
                ]
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        $data = $response ? json_decode($response, true) : [];
        
        
        if (is_array($data)) {
            foreach ($data as &$item) {
                $item = $this->formatParcelData($item);
            }
        }
        
        return $data;
    }
    
    private function formatParcelData($parcel) {
        
        if (isset($parcel['origin_outlets']) && is_array($parcel['origin_outlets'])) {
            $parcel['origin_outlet_name'] = $parcel['origin_outlets']['outlet_name'] ?? null;
        }
        
        if (isset($parcel['destination_outlets']) && is_array($parcel['destination_outlets'])) {
            $parcel['destination_outlet_name'] = $parcel['destination_outlets']['outlet_name'] ?? null;
        }
        
        if (isset($parcel['sender_customers']) && is_array($parcel['sender_customers'])) {
            $parcel['sender_full_name'] = $parcel['sender_customers']['full_name'] ?? null;
        }
        
        if (isset($parcel['receiver_customers']) && is_array($parcel['receiver_customers'])) {
            $parcel['receiver_full_name'] = $parcel['receiver_customers']['full_name'] ?? null;
        }
        
        
        if (!$parcel['sender_full_name'] && isset($parcel['sender_name'])) {
            $parcel['sender_full_name'] = $parcel['sender_name'];
        }
        
        if (!$parcel['receiver_full_name'] && isset($parcel['receiver_name'])) {
            $parcel['receiver_full_name'] = $parcel['receiver_name'];
        }
        
        return $parcel;
    }
    
    private function getParcels($limit, $offset) {
        $select = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlets:origin_outlet_id(outlet_name),destination_outlets:destination_outlet_id(outlet_name),sender_customers:sender_id(full_name),receiver_customers:receiver_id(full_name)";
        $filters = "company_id=eq.{$this->companyId}&limit={$limit}&offset={$offset}&order=created_at.desc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);
        
        return [
            'success' => true,
            'data' => $parcels,
            'count' => count($parcels)
        ];
    }
    
    private function getUrgentParcels($limit, $offset) {
        $cutoffDate = date('Y-m-d', strtotime('-3 days'));
        $select = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlets:origin_outlet_id(outlet_name),destination_outlets:destination_outlet_id(outlet_name),sender_customers:sender_id(full_name),receiver_customers:receiver_id(full_name)";
        $filters = "company_id=eq.{$this->companyId}&status=in.(pending,assigned)&created_at=lt.{$cutoffDate}T00:00:00&limit={$limit}&offset={$offset}&order=created_at.asc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);
        
        return [
            'success' => true,
            'data' => $parcels,
            'count' => count($parcels)
        ];
    }
    
    private function getInTransitParcels($limit, $offset) {
        $select = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlets:origin_outlet_id(outlet_name),destination_outlets:destination_outlet_id(outlet_name),sender_customers:sender_id(full_name),receiver_customers:receiver_id(full_name)";
        $filters = "company_id=eq.{$this->companyId}&status=eq.assigned&limit={$limit}&offset={$offset}&order=created_at.desc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);
        
        return [
            'success' => true,
            'data' => $parcels,
            'count' => count($parcels)
        ];
    }
    
    private function getPendingParcels($limit, $offset) {
        $select = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlets:origin_outlet_id(outlet_name),destination_outlets:destination_outlet_id(outlet_name),sender_customers:sender_id(full_name),receiver_customers:receiver_id(full_name)";
        $filters = "company_id=eq.{$this->companyId}&status=eq.pending&limit={$limit}&offset={$offset}&order=created_at.desc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);
        
        return [
            'success' => true,
            'data' => $parcels,
            'count' => count($parcels)
        ];
    }
    
    private function getCompletedParcels($limit, $offset) {
        $currentDate = date('Y-m-d');
        $select = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlets:origin_outlet_id(outlet_name),destination_outlets:destination_outlet_id(outlet_name),sender_customers:sender_id(full_name),receiver_customers:receiver_id(full_name)";
        $filters = "company_id=eq.{$this->companyId}&status=eq.delivered&delivery_date=eq.{$currentDate}&limit={$limit}&offset={$offset}&order=delivery_date.desc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);
        
        return [
            'success' => true,
            'data' => $parcels,
            'count' => count($parcels)
        ];
    }
    
    private function getTrips($limit, $offset) {
        $select = "id,trip_status,departure_time,arrival_time,created_at,vehicle:vehicle_id(name,plate_number,status),outlet_manager:outlet_manager_id(full_name)";
        $filters = "company_id=eq.{$this->companyId}&limit={$limit}&offset={$offset}&order=created_at.desc";
        $trips = $this->query('/rest/v1/trips', $select, $filters);
        
        return [
            'success' => true,
            'data' => $trips,
            'count' => count($trips)
        ];
    }
    
    private function getTripsScheduled($limit, $offset) {
        $select = "id,trip_status,departure_time,arrival_time,created_at,vehicle:vehicle_id(name,plate_number,status),outlet_manager:outlet_manager_id(full_name)";
        $filters = "company_id=eq.{$this->companyId}&trip_status=eq.scheduled&limit={$limit}&offset={$offset}&order=created_at.desc";
        $trips = $this->query('/rest/v1/trips', $select, $filters);
        
        return [
            'success' => true,
            'data' => $trips,
            'count' => count($trips)
        ];
    }
    
    private function getTripsInTransit($limit, $offset) {
        $select = "id,trip_status,departure_time,arrival_time,created_at,vehicle:vehicle_id(name,plate_number,status),outlet_manager:outlet_manager_id(full_name)";
        $filters = "company_id=eq.{$this->companyId}&trip_status=eq.in_transit&limit={$limit}&offset={$offset}&order=created_at.desc";
        $trips = $this->query('/rest/v1/trips', $select, $filters);
        
        return [
            'success' => true,
            'data' => $trips,
            'count' => count($trips)
        ];
    }
    
    private function getVehicles($limit, $offset) {
        $select = "id,name,plate_number,status,created_at";
        $filters = "company_id=eq.{$this->companyId}&limit={$limit}&offset={$offset}&order=created_at.desc";
        $vehicles = $this->query('/rest/v1/vehicle', $select, $filters);
        
        return [
            'success' => true,
            'data' => $vehicles,
            'count' => count($vehicles)
        ];
    }
    
    private function getAssignedVehicles($limit, $offset) {
        $select = "id,name,plate_number,status,created_at";
        $filters = "company_id=eq.{$this->companyId}&status=eq.out_for_delivery&limit={$limit}&offset={$offset}&order=created_at.desc";
        $vehicles = $this->query('/rest/v1/vehicle', $select, $filters);
        
        return [
            'success' => true,
            'data' => $vehicles,
            'count' => count($vehicles)
        ];
    }
    
    private function getAvailableVehicles($limit, $offset) {
        $select = "id,name,plate_number,status,created_at";
        $filters = "company_id=eq.{$this->companyId}&status=eq.available&limit={$limit}&offset={$offset}&order=created_at.desc";
        $vehicles = $this->query('/rest/v1/vehicle', $select, $filters);
        
        return [
            'success' => true,
            'data' => $vehicles,
            'count' => count($vehicles)
        ];
    }
}

try {
    $api = new EnhancedDashboardDetailsAPI();
    $result = $api->handleRequest();
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>