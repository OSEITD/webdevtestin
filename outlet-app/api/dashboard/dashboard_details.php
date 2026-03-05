<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../includes/session_manager.php';
    initSession();
}

class EnhancedDashboardDetailsAPI {
    private $url;
    private $key;
    public $companyId;
    private $outletId;

    public function __construct() {
        if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../../includes/env.php'; }
        EnvLoader::load();
        $this->url = getenv('SUPABASE_URL');
        $this->key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');
        $this->companyId = $_SESSION['company_id'] ?? null;
        if (!$this->companyId) {
            throw new RuntimeException('Company ID is required');
        }
        $this->outletId = $_SESSION['outlet_id'] ?? null;
    }
    
    public function handleRequest() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        
        if ($requestMethod === 'POST') {
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
            $type = $input['type'] ?? '';
            $limit = intval($input['limit'] ?? 50);
            $offset = intval($input['offset'] ?? 0);
        } else {
            $rawInput = '';
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
            case 'trips_completed':
                return $this->getTripsCompleted($limit, $offset);
            case 'vehicles':
                return $this->getVehicles($limit, $offset);
            case 'assigned_vehicles':
                return $this->getAssignedVehicles($limit, $offset);
            case 'available_vehicles':
                return $this->getAvailableVehicles($limit, $offset);
            case 'unavailable_vehicles':
                return $this->getUnavailableVehicles($limit, $offset);
            case 'today_transactions':
                return $this->getTodayTransactions($limit, $offset);
            case 'week_payments':
                return $this->getWeekPayments($limit, $offset);
            case 'cod_collections_today':
                return $this->getCODCollectionsToday($limit, $offset);
            default:
                return ['error' => 'Invalid type specified', 'debug_type' => $type, 'debug_method' => $_SERVER['REQUEST_METHOD'], 'debug_raw' => substr($rawInput ?? '', 0, 300)];
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
        if ($response === false) {
            return [];
        }
        
        $data = json_decode($response, true);
        return $data !== null ? $data : [];
    }
    
    private function validateRouteMultitenancy($originOutletId, $destinationOutletId) {
        if (!$originOutletId || !$destinationOutletId) {
            return false;
        }
        
        $outletIds = [$originOutletId, $destinationOutletId];
        $outletValidation = $this->query(
            '/rest/v1/outlets', 
            "id,company_id", 
            "company_id=eq.{$this->companyId}&id=in.(" . implode(',', $outletIds) . ")"
        );
        
        $validOutlets = array_column($outletValidation, 'id');
        return count($outletValidation) === 2 && 
               in_array($originOutletId, $validOutlets) && 
               in_array($destinationOutletId, $validOutlets);
    }
    
    private function enrichWithOutletNames($parcels) {
        if (empty($parcels)) return $parcels;
        
        $outletIds = [];
        foreach ($parcels as $parcel) {
            if (!empty($parcel['origin_outlet_id'])) {
                $outletIds[] = $parcel['origin_outlet_id'];
            }
            if (!empty($parcel['destination_outlet_id'])) {
                $outletIds[] = $parcel['destination_outlet_id'];
            }
        }
        
        if (empty($outletIds)) return $parcels;
        
        $outletIds = array_unique($outletIds);
        $outletFilter = "company_id=eq.{$this->companyId}&id=in.(" . implode(',', $outletIds) . ")";
        $outlets = $this->query('/rest/v1/outlets', "id,outlet_name", $outletFilter);
        
        $outletLookup = [];
        foreach ($outlets as $outlet) {
            $outletLookup[$outlet['id']] = $outlet['outlet_name'];
        }
        
        foreach ($parcels as &$parcel) {
            $parcel['origin_outlet_name'] = $outletLookup[$parcel['origin_outlet_id']] ?? 'N/A';
            $parcel['destination_outlet_name'] = $outletLookup[$parcel['destination_outlet_id']] ?? 'N/A';
        }
        
        return $parcels;
    }
    
    private function enrichTripsData($trips) {
        if (empty($trips)) return $trips;

        $vehicleIds  = [];
        $managerIds  = [];
        $outletIds   = [];
        $driverIds   = [];

        foreach ($trips as $trip) {
            if (!empty($trip['vehicle_id']))         $vehicleIds[]  = $trip['vehicle_id'];
            if (!empty($trip['outlet_manager_id']))  $managerIds[]  = $trip['outlet_manager_id'];
            if (!empty($trip['origin_outlet_id']))   $outletIds[]   = $trip['origin_outlet_id'];
            if (!empty($trip['destination_outlet_id'])) $outletIds[] = $trip['destination_outlet_id'];
            if (!empty($trip['driver_id']))          $driverIds[]   = $trip['driver_id'];
        }

        // Vehicles
        $vehicleLookup = [];
        if (!empty($vehicleIds)) {
            $vehicleIds = array_unique($vehicleIds);
            $vehicles = $this->query('/rest/v1/vehicle', "id,name,plate_number,status",
                "id=in.(" . implode(',', $vehicleIds) . ")");
            foreach ($vehicles as $v) { $vehicleLookup[$v['id']] = $v; }
        }

        // Managers / outlet managers
        $managerLookup = [];
        if (!empty($managerIds)) {
            $managerIds = array_unique($managerIds);
            $managers = $this->query('/rest/v1/profiles', "id,full_name",
                "id=in.(" . implode(',', $managerIds) . ")");
            foreach ($managers as $m) { $managerLookup[$m['id']] = $m; }
        }

        // Drivers
        $driverLookup = [];
        if (!empty($driverIds)) {
            $driverIds = array_unique($driverIds);
            $drivers = $this->query('/rest/v1/drivers', "id,driver_name,driver_phone",
                "company_id=eq.{$this->companyId}&id=in.(" . implode(',', $driverIds) . ")");
            foreach ($drivers as $d) { $driverLookup[$d['id']] = $d; }
        }

        // Outlet names
        $outletLookup = [];
        if (!empty($outletIds)) {
            $outletIds = array_unique($outletIds);
            $outlets = $this->query('/rest/v1/outlets', "id,outlet_name",
                "company_id=eq.{$this->companyId}&id=in.(" . implode(',', $outletIds) . ")");
            foreach ($outlets as $o) { $outletLookup[$o['id']] = $o['outlet_name']; }
        }

        foreach ($trips as &$trip) {
            $vehicle = $vehicleLookup[$trip['vehicle_id'] ?? ''] ?? [];
            $manager = $managerLookup[$trip['outlet_manager_id'] ?? ''] ?? [];
            $driver  = $driverLookup[$trip['driver_id'] ?? ''] ?? [];

            $trip['vehicle'] = [
                'name'         => $vehicle['name']         ?? 'N/A',
                'plate_number' => $vehicle['plate_number'] ?? 'N/A',
                'status'       => $vehicle['status']       ?? 'N/A',
            ];

            $trip['manager_name']            = $manager['full_name']   ?? 'N/A';
            $trip['driver_name']             = $driver['driver_name']  ?? 'N/A';
            $trip['driver_phone']            = $driver['driver_phone'] ?? 'N/A';
            $trip['vehicle_name']            = $vehicle['name']         ?? 'N/A';
            $trip['vehicle_plate']           = $vehicle['plate_number'] ?? 'N/A';
            $trip['vehicle_status']          = $vehicle['status']       ?? 'N/A';
            $trip['origin_outlet_name']      = $outletLookup[$trip['origin_outlet_id']      ?? ''] ?? 'N/A';
            $trip['destination_outlet_name'] = $outletLookup[$trip['destination_outlet_id'] ?? ''] ?? 'N/A';
        }

        return $trips;
    }
    
    private function getParcels($limit, $offset) {
        $outletFilter = $this->outletId ? "&origin_outlet_id=eq.{$this->outletId}" : '';
        $select  = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlet_id,destination_outlet_id";
        $filters = "company_id=eq.{$this->companyId}{$outletFilter}&limit={$limit}&offset={$offset}&order=created_at.desc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);
        $parcels = $this->enrichWithOutletNames($parcels);
        return ['success' => true, 'data' => $parcels, 'count' => count($parcels)];
    }
    
    private function getUrgentParcels($limit, $offset) {
        $cutoffDate   = date('Y-m-d', strtotime('-3 days'));
        $outletFilter = $this->outletId ? "&origin_outlet_id=eq.{$this->outletId}" : '';
        $select  = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlet_id,destination_outlet_id";
        $filters = "company_id=eq.{$this->companyId}{$outletFilter}&status=in.(pending,assigned,at_outlet)&created_at=lt.{$cutoffDate}T00:00:00&limit={$limit}&offset={$offset}&order=created_at.asc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);
        // Calculate days overdue for each parcel
        foreach ($parcels as &$parcel) {
            $created  = isset($parcel['created_at']) ? new DateTime($parcel['created_at']) : null;
            $now      = new DateTime();
            $parcel['days_overdue'] = $created ? $created->diff($now)->days : 0;
        }
        $parcels = $this->enrichWithOutletNames($parcels);
        return ['success' => true, 'data' => $parcels, 'count' => count($parcels)];
    }
    
    private function getInTransitParcels($limit, $offset) {
        // Only trips that originate from this outlet (or all trips if no outlet set)
        $outletFilter = $this->outletId ? "&origin_outlet_id=eq.{$this->outletId}" : '';
        $tripSelect  = "id,trip_status,departure_time,arrival_time,vehicle_id,outlet_manager_id,driver_id,origin_outlet_id,destination_outlet_id";
        $tripFilters = "company_id=eq.{$this->companyId}{$outletFilter}&trip_status=eq.in_transit&order=departure_time.desc";
        $trips = $this->query('/rest/v1/trips', $tripSelect, $tripFilters);

        // Fallback: no in-transit trips found – show parcels that have in_transit status at the parcel level
        if (empty($trips)) {
            $select  = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlet_id,destination_outlet_id";
            $filters = "company_id=eq.{$this->companyId}{$outletFilter}&status=eq.in_transit&limit={$limit}&offset={$offset}&order=created_at.desc";
            $parcels = $this->query('/rest/v1/parcels', $select, $filters);
            $parcels = $this->enrichWithOutletNames($parcels);
            foreach ($parcels as &$parcel) {
                $parcel['trip_id']      = 'N/A';
                $parcel['trip_code']    = 'N/A';
                $parcel['trip_status']  = 'N/A';
                $parcel['departure_time'] = null;
                $parcel['arrival_time']   = null;
                $parcel['vehicle_name']   = 'N/A';
                $parcel['vehicle_plate']  = 'N/A';
                $parcel['vehicle_status'] = 'N/A';
                $parcel['driver_name']    = 'N/A';
                $parcel['manager_name']   = 'N/A';
            }
            return ['success' => true, 'data' => $parcels, 'count' => count($parcels)];
        }

        // Enrich trips with vehicle / driver / outlet names
        $trips     = $this->enrichTripsData($trips);
        $tripIds   = array_column($trips, 'id');
        $tripLookup = [];
        foreach ($trips as $trip) { $tripLookup[$trip['id']] = $trip; }

        // Get parcel_list records for these trips
        // (trips are already scoped to origin_outlet_id — no need to re-filter by parcel_list.outlet_id,
        //  which is the DESTINATION outlet and would produce wrong results)
        $parcelListSelect = "id,parcel_id,trip_id,outlet_id,status";
        $parcelListFilter = "company_id=eq.{$this->companyId}&trip_id=in.(" . implode(',', $tripIds) . ")&limit={$limit}&offset={$offset}&order=created_at.desc";
        $parcelList          = $this->query('/rest/v1/parcel_list', $parcelListSelect, $parcelListFilter);

        if (empty($parcelList)) {
            return ['success' => true, 'data' => [], 'count' => 0];
        }

        // Fetch parcel details (company-scoped only; outlet was already applied via parcel_list.outlet_id)
        $parcelIds    = array_filter(array_unique(array_column($parcelList, 'parcel_id')));
        $parcelSelect = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlet_id,destination_outlet_id";
        $rawParcels   = $this->query('/rest/v1/parcels', $parcelSelect,
            "company_id=eq.{$this->companyId}&id=in.(" . implode(',', $parcelIds) . ")");
        $rawParcels   = $this->enrichWithOutletNames($rawParcels);

        $parcelLookup = [];
        foreach ($rawParcels as $p) { $parcelLookup[$p['id']] = $p; }

        // Build result rows
        $result = [];
        foreach ($parcelList as $item) {
            $parcel = $parcelLookup[$item['parcel_id']] ?? null;
            if (!$parcel) continue;

            $trip = $tripLookup[$item['trip_id']] ?? [];
            $result[] = array_merge($parcel, [
                'trip_id'                => $item['trip_id'],
                'trip_code'              => strtoupper(substr($item['trip_id'], 0, 8)),
                'trip_status'            => $trip['trip_status']            ?? 'N/A',
                'departure_time'         => $trip['departure_time']         ?? null,
                'arrival_time'           => $trip['arrival_time']           ?? null,
                'vehicle_name'           => $trip['vehicle_name']           ?? 'N/A',
                'vehicle_plate'          => $trip['vehicle_plate']          ?? 'N/A',
                'vehicle_status'         => $trip['vehicle_status']         ?? 'N/A',
                'driver_name'            => $trip['driver_name']            ?? 'N/A',
                'manager_name'           => $trip['manager_name']           ?? 'N/A',
                'origin_outlet_name'     => $trip['origin_outlet_name']     ?? ($parcel['origin_outlet_name']      ?? 'N/A'),
                'destination_outlet_name' => $trip['destination_outlet_name'] ?? ($parcel['destination_outlet_name'] ?? 'N/A'),
            ]);
        }

        return ['success' => true, 'data' => $result, 'count' => count($result)];
    }
    
    private function getPendingParcels($limit, $offset) {
        $outletFilter = $this->outletId ? "&origin_outlet_id=eq.{$this->outletId}" : '';
        $select  = "id,track_number,status,delivery_date,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlet_id,destination_outlet_id";
        $filters = "company_id=eq.{$this->companyId}{$outletFilter}&status=in.(pending,at_outlet,assigned)&limit={$limit}&offset={$offset}&order=created_at.desc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);
        $parcels = $this->enrichWithOutletNames($parcels);
        return ['success' => true, 'data' => $parcels, 'count' => count($parcels)];
    }
    
    private function getCompletedParcels($limit, $offset) {
        $currentDate  = date('Y-m-d');
        $outletFilter = $this->outletId ? "&origin_outlet_id=eq.{$this->outletId}" : '';
        $select  = "id,track_number,status,delivery_date,delivered_at,created_at,sender_name,receiver_name,receiver_address,package_details,sender_phone,receiver_phone,origin_outlet_id,destination_outlet_id";
        $filters = "company_id=eq.{$this->companyId}{$outletFilter}&status=eq.delivered&delivery_date=eq.{$currentDate}&limit={$limit}&offset={$offset}&order=delivered_at.desc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);
        $parcels = $this->enrichWithOutletNames($parcels);
        return ['success' => true, 'data' => $parcels, 'count' => count($parcels)];
    }
    
    private function getTrips($limit, $offset) {
        $outletFilter = $this->outletId ? "&origin_outlet_id=eq.{$this->outletId}" : '';
        $select  = "id,trip_status,departure_time,arrival_time,created_at,vehicle_id,outlet_manager_id,driver_id,origin_outlet_id,destination_outlet_id";
        $filters = "company_id=eq.{$this->companyId}{$outletFilter}&limit={$limit}&offset={$offset}&order=created_at.desc";
        $trips   = $this->query('/rest/v1/trips', $select, $filters);
        $trips   = $this->enrichTripsData($trips);
        return ['success' => true, 'data' => $trips, 'count' => count($trips)];
    }
    
    private function getTripsScheduled($limit, $offset) {
        $outletFilter = $this->outletId ? "&origin_outlet_id=eq.{$this->outletId}" : '';
        $select  = "id,trip_status,departure_time,arrival_time,trip_date,created_at,vehicle_id,outlet_manager_id,driver_id,origin_outlet_id,destination_outlet_id";
        $filters = "company_id=eq.{$this->companyId}{$outletFilter}&trip_status=eq.scheduled&limit={$limit}&offset={$offset}&order=departure_time.asc";
        $trips   = $this->query('/rest/v1/trips', $select, $filters);
        $trips   = $this->enrichTripsData($trips);
        return [
            'success' => true,
            'data' => $trips,
            'count' => count($trips)
        ];
    }
    
    private function getTripsInTransit($limit, $offset) {
        $outletFilter = $this->outletId ? "&origin_outlet_id=eq.{$this->outletId}" : '';
        $select  = "id,trip_status,departure_time,arrival_time,trip_date,created_at,vehicle_id,outlet_manager_id,driver_id,origin_outlet_id,destination_outlet_id";
        $filters = "company_id=eq.{$this->companyId}{$outletFilter}&trip_status=eq.in_transit&limit={$limit}&offset={$offset}&order=departure_time.desc";
        $trips   = $this->query('/rest/v1/trips', $select, $filters);
        $trips   = $this->enrichTripsData($trips);
        return ['success' => true, 'data' => $trips, 'count' => count($trips)];
    }
    
    private function getTripsCompleted($limit, $offset) {
        $today        = date('Y-m-d');
        $outletFilter = $this->outletId ? "&origin_outlet_id=eq.{$this->outletId}" : '';
        $select  = "id,trip_status,departure_time,arrival_time,trip_date,created_at,updated_at,vehicle_id,outlet_manager_id,driver_id,origin_outlet_id,destination_outlet_id";
        $filters = "company_id=eq.{$this->companyId}{$outletFilter}&trip_status=eq.completed&updated_at=gte.{$today}T00:00:00&limit={$limit}&offset={$offset}&order=updated_at.desc";
        $trips   = $this->query('/rest/v1/trips', $select, $filters);
        $trips   = $this->enrichTripsData($trips);
        return ['success' => true, 'data' => $trips, 'count' => count($trips)];
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
        
        if (empty($vehicles)) {
            return [
                'success' => true,
                'data' => [],
                'count' => 0
            ];
        }
        
        
        $vehicleIds = array_column($vehicles, 'id');
        $vehicleIdsList = "(" . implode(',', array_map(function($id) { return "\"$id\""; }, $vehicleIds)) . ")";
        
        $tripSelect = "id,vehicle_id,trip_status,departure_time,arrival_time,created_at,outlet_manager_id";
        $tripFilters = "company_id=eq.{$this->companyId}&vehicle_id=in.{$vehicleIdsList}&trip_status=in.(scheduled,in_transit)&order=created_at.desc";
        $trips = $this->query('/rest/v1/trips', $tripSelect, $tripFilters);
        
        
        $tripLookup = [];
        foreach ($trips as $trip) {
            $tripLookup[$trip['vehicle_id']] = $trip;
        }
        
        
        $parcelsByTrip = []; 
        
        if (!empty($trips)) {
            
            $tripIds = array_column($trips, 'id');
            $tripIdsList = "(" . implode(',', array_map(function($id) { return "\"$id\""; }, $tripIds)) . ")";
            
            
            $parcelListSelect = "trip_id,parcel_id,outlet_id";
            $parcelListFilters = "company_id=eq.{$this->companyId}&trip_id=in.{$tripIdsList}";
            $parcelList = $this->query('/rest/v1/parcel_list', $parcelListSelect, $parcelListFilters);
            
            
            if (!empty($parcelList)) {
                $parcelIds = array_unique(array_column($parcelList, 'parcel_id'));
                $parcelIdsList = "(" . implode(',', array_map(function($id) { return "\"$id\""; }, $parcelIds)) . ")";
                
                $parcelSelect = "id,origin_outlet_id,destination_outlet_id,track_number";
                $parcelFilters = "company_id=eq.{$this->companyId}&id=in.{$parcelIdsList}";
                $parcels = $this->query('/rest/v1/parcels', $parcelSelect, $parcelFilters);
                
                
                $parcelLookup = [];
                foreach ($parcels as $parcel) {
                    $parcelLookup[$parcel['id']] = $parcel;
                }
                
                
                $parcelsByTrip = [];
                foreach ($parcelList as $item) {
                    $parcelsByTrip[$item['trip_id']][] = $item['parcel_id'];
                }
                
                
                $allOutletIds = [];
                foreach ($parcels as $parcel) {
                    if ($parcel['origin_outlet_id']) $allOutletIds[] = $parcel['origin_outlet_id'];
                    if ($parcel['destination_outlet_id']) $allOutletIds[] = $parcel['destination_outlet_id'];
                }
            } else {
                $parcelsByTrip = [];
                $parcelLookup = [];
                $allOutletIds = [];
            }
            
            
            $stopSelect = "trip_id,outlet_id,stop_order";
            $stopFilters = "company_id=eq.{$this->companyId}&trip_id=in.{$tripIdsList}&order=stop_order.asc";
            $tripStops = $this->query('/rest/v1/trip_stops', $stopSelect, $stopFilters);
            
            
            foreach ($tripStops as $stop) {
                $allOutletIds[] = $stop['outlet_id'];
            }
            
            
            $managerIds = array_unique(array_column($trips, 'outlet_manager_id'));
            $managerOutletLookup = [];
            if (!empty($managerIds)) {
                $managerIdsList = "(" . implode(',', array_map(function($id) { return "\"$id\""; }, $managerIds)) . ")";
                $managerSelect = "id,outlet_id";
                $managerFilters = "company_id=eq.{$this->companyId}&id=in.{$managerIdsList}";
                $managers = $this->query('/rest/v1/profiles', $managerSelect, $managerFilters);
                
                foreach ($managers as $manager) {
                    if ($manager['outlet_id']) {
                        $managerOutletLookup[$manager['id']] = $manager['outlet_id'];
                        $allOutletIds[] = $manager['outlet_id'];
                    }
                }
            }
            
            
            $outletLookup = [];
            if (!empty($allOutletIds)) {
                $uniqueOutletIds = array_unique($allOutletIds);
                $outletIdsList = "(" . implode(',', array_map(function($id) { return "\"$id\""; }, $uniqueOutletIds)) . ")";
                $outletSelect = "id,outlet_name";
                
                $outletFilters = "company_id=eq.{$this->companyId}&id=in.{$outletIdsList}";
                $outlets = $this->query('/rest/v1/outlets', $outletSelect, $outletFilters);
                
                foreach ($outlets as $outlet) {
                    $outletLookup[$outlet['id']] = $outlet['outlet_name'];
                }
            }
            
            
            $tripStopsLookup = [];
            foreach ($tripStops as $stop) {
                $tripStopsLookup[$stop['trip_id']][] = $stop;
            }
            
            
            foreach ($trips as &$trip) {
                $tripParcels = $parcelsByTrip[$trip['id']] ?? [];
                $stops = $tripStopsLookup[$trip['id']] ?? [];
                
                error_log("DEBUG: Processing trip {$trip['id']} with " . count($tripParcels) . " parcels");
                
                
                if (!empty($tripParcels)) {
                    $origins = [];
                    $destinations = [];
                    
                    foreach ($tripParcels as $parcelId) {
                        $parcel = $parcelLookup[$parcelId] ?? null;
                        if ($parcel) {
                            error_log("DEBUG: Parcel $parcelId origin: {$parcel['origin_outlet_id']}, destination: {$parcel['destination_outlet_id']}");
                            
                            
                            if ($parcel['origin_outlet_id'] && $parcel['destination_outlet_id'] && 
                                !$this->validateRouteMultitenancy($parcel['origin_outlet_id'], $parcel['destination_outlet_id'])) {
                                error_log("WARNING: Cross-company route detected and blocked for parcel $parcelId");
                                continue; 
                            }
                            
                            
                            if ($parcel['origin_outlet_id'] && isset($outletLookup[$parcel['origin_outlet_id']])) {
                                $origins[] = $outletLookup[$parcel['origin_outlet_id']];
                                error_log("DEBUG: Added origin: " . $outletLookup[$parcel['origin_outlet_id']]);
                            }
                            
                            
                            if ($parcel['destination_outlet_id'] && isset($outletLookup[$parcel['destination_outlet_id']])) {
                                $destinations[] = $outletLookup[$parcel['destination_outlet_id']];
                                error_log("DEBUG: Added destination from parcel: " . $outletLookup[$parcel['destination_outlet_id']]);
                            } else {
                                
                                foreach ($parcelList as $listItem) {
                                    if ($listItem['parcel_id'] === $parcelId && $listItem['trip_id'] === $trip['id']) {
                                        if ($listItem['outlet_id'] && isset($outletLookup[$listItem['outlet_id']])) {
                                            $destinations[] = $outletLookup[$listItem['outlet_id']];
                                            error_log("DEBUG: Added destination from parcel_list: " . $outletLookup[$listItem['outlet_id']]);
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    
                    error_log("DEBUG: Trip {$trip['id']} found " . count($origins) . " origins, " . count($destinations) . " destinations");
                    
                    
                    if (!empty($origins)) {
                        $originCounts = array_count_values($origins);
                        $trip['origin_outlet_name'] = array_keys($originCounts, max($originCounts))[0];
                        error_log("DEBUG: Trip {$trip['id']} origin set to: {$trip['origin_outlet_name']}");
                    }
                    
                    
                    if (!empty($destinations)) {
                        $uniqueDestinations = array_unique($destinations);
                        if (count($uniqueDestinations) == 1) {
                            $trip['destination_outlet_name'] = $uniqueDestinations[0];
                        } else {
                            $destinationCounts = array_count_values($destinations);
                            $primaryDestination = array_keys($destinationCounts, max($destinationCounts))[0];
                            $trip['destination_outlet_name'] = $primaryDestination . ' (+' . (count($uniqueDestinations) - 1) . ' more)';
                        }
                        error_log("DEBUG: Trip {$trip['id']} destination set to: {$trip['destination_outlet_name']}");
                    }
                }
                
                
                if (!isset($trip['origin_outlet_name']) && !empty($stops)) {
                    $firstStop = $stops[0];
                    $trip['origin_outlet_name'] = $outletLookup[$firstStop['outlet_id']] ?? 'Unknown Origin';
                }
                
                if (!isset($trip['destination_outlet_name']) && !empty($stops)) {
                    $lastStop = end($stops);
                    $trip['destination_outlet_name'] = $outletLookup[$lastStop['outlet_id']] ?? 'Unknown Destination';
                    
                    
                    if (count($stops) > 2) {
                        $trip['destination_outlet_name'] .= ' (+' . (count($stops) - 2) . ' stops)';
                    }
                }
                
                
                if (!isset($trip['origin_outlet_name']) && isset($trip['outlet_manager_id'])) {
                    $managerOutletId = $managerOutletLookup[$trip['outlet_manager_id']] ?? null;
                    if ($managerOutletId && isset($outletLookup[$managerOutletId])) {
                        $trip['origin_outlet_name'] = $outletLookup[$managerOutletId];
                    }
                }
                
                
                if (!isset($trip['origin_outlet_name'])) {
                    $trip['origin_outlet_name'] = 'Company Dispatch';
                }
                if (!isset($trip['destination_outlet_name'])) {
                    $trip['destination_outlet_name'] = count($tripParcels) > 0 ? 
                        'Company Route (' . count($tripParcels) . ' parcels)' : 
                        'Company Network';
                }
                
                
                $trip['company_scoped'] = true;
                $trip['route_type'] = count($tripParcels) > 0 ? 'parcel_delivery' : 'general_route';
            }
        } else {
            $parcelsByTrip = [];
        }
        
        
        foreach ($vehicles as &$vehicle) {
            $trip = $tripLookup[$vehicle['id']] ?? null;
            if ($trip) {
                $vehicle['current_trip_code'] = substr($trip['id'], 0, 8); 
                $vehicle['current_trip_status'] = ucfirst(str_replace('_', ' ', $trip['trip_status'] ?? 'unknown'));
                $vehicle['departure_time'] = $trip['departure_time'] ?? null;
                
                
                $enhancedTrip = null;
                foreach ($trips as $t) {
                    if ($t['id'] === $trip['id']) {
                        $enhancedTrip = $t;
                        break;
                    }
                }
                
                
                if ($enhancedTrip && isset($enhancedTrip['origin_outlet_name']) && isset($enhancedTrip['destination_outlet_name'])) {
                    $vehicle['origin_outlet_name'] = $enhancedTrip['origin_outlet_name'];
                    $vehicle['destination_outlet_name'] = $enhancedTrip['destination_outlet_name'];
                } else {
                    $vehicle['origin_outlet_name'] = 'Dispatch Center';
                    $vehicle['destination_outlet_name'] = 'Active Route';
                }
                
                
                $tripParcels = $parcelsByTrip[$trip['id']] ?? [];
                $vehicle['parcel_count'] = count($tripParcels);
            } else {
                $vehicle['current_trip_code'] = 'Not Assigned';
                $vehicle['current_trip_status'] = 'Available';
                $vehicle['departure_time'] = null;
                $vehicle['origin_outlet_name'] = 'Not Assigned';
                $vehicle['destination_outlet_name'] = 'Not Assigned';
                $vehicle['parcel_count'] = 0;
            }
        }
        
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

    private function getUnavailableVehicles($limit, $offset) {
        // status=unavailable matches the count shown on the dashboard card (from dashboard_stats_fast.php)
        $select  = "id,name,plate_number,status,created_at";
        $filters = "company_id=eq.{$this->companyId}&status=eq.unavailable&limit={$limit}&offset={$offset}&order=created_at.desc";
        $vehicles = $this->query('/rest/v1/vehicle', $select, $filters);

        return [
            'success' => true,
            'data'    => $vehicles,
            'count'   => count($vehicles)
        ];
    }

    private function getTodayTransactions($limit, $offset) {
        $today = date('Y-m-d');
        $outletId = $_SESSION['outlet_id'] ?? null;
        $outletFilter = $outletId ? "&outlet_id=eq.{$outletId}" : '';

        $select = "id,tx_ref,amount,payment_method,status,paid_at,customer_name,customer_phone,parcel_id,mobile_network";
        $filters = "company_id=eq.{$this->companyId}&status=eq.successful&paid_at=gte.{$today}T00:00:00{$outletFilter}&limit={$limit}&offset={$offset}&order=paid_at.desc";
        $transactions = $this->query('/rest/v1/payment_transactions', $select, $filters);

        return [
            'success' => true,
            'data' => $transactions,
            'count' => count($transactions)
        ];
    }

    private function getWeekPayments($limit, $offset) {
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $outletId = $_SESSION['outlet_id'] ?? null;
        $outletFilter = $outletId ? "&outlet_id=eq.{$outletId}" : '';

        $select = "id,tx_ref,amount,payment_method,status,paid_at,customer_name,customer_phone,parcel_id";
        $filters = "company_id=eq.{$this->companyId}&status=eq.successful&paid_at=gte.{$weekStart}T00:00:00{$outletFilter}&limit={$limit}&offset={$offset}&order=paid_at.desc";
        $payments = $this->query('/rest/v1/payment_transactions', $select, $filters);

        return [
            'success' => true,
            'data' => $payments,
            'count' => count($payments)
        ];
    }

    private function getCODCollectionsToday($limit, $offset) {
        $today = date('Y-m-d');
        $outletId = $_SESSION['outlet_id'] ?? null;
        $outletFilter = $outletId ? "&origin_outlet_id=eq.{$outletId}" : '';

        $select = "id,track_number,cod_amount,status,receiver_name,receiver_phone,delivery_date,delivered_at";
        $filters = "company_id=eq.{$this->companyId}&cod_amount=gt.0&status=eq.delivered&delivery_date=eq.{$today}{$outletFilter}&limit={$limit}&offset={$offset}&order=delivered_at.desc";
        $parcels = $this->query('/rest/v1/parcels', $select, $filters);

        return [
            'success' => true,
            'data' => $parcels,
            'count' => count($parcels)
        ];
    }
}

try {
    $api = new EnhancedDashboardDetailsAPI();
    $result = $api->handleRequest();
    
    
    if ($result && is_array($result)) {
        $result['multitenancy'] = [
            'company_scoped' => true,
            'company_id' => $api->companyId ?? null,
            'isolated_routes' => true,
            'data_protection' => 'company_level'
        ];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'multitenancy' => [
            'company_scoped' => true,
            'data_protection' => 'company_level'
        ]
    ]);
}
?>