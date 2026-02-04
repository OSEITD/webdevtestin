
<?php

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}
require_once '../includes/supabase-helper.php';

class GPSTracker {
    private $supabase;
    public function __construct() {
        try {
            $this->supabase = new SupabaseHelper();
        } catch (Exception $e) {
            error_log("Failed to initialize Supabase helper: " . $e->getMessage());
            $this->supabase = null;
        }
    }

    private function callSupabase($table, $params = "") {
        if (!$this->supabase) {
            throw new Exception("Supabase helper not available");
        }
        return $this->supabase->get($table, $params);
    }

    public function getTripTracking($trip_id) {
        if (empty($trip_id)) {
            return [
                'success' => false,
                'error' => 'Trip ID is required'
            ];
        }

        try {
            
            $tripData = $this->callSupabase('trips', "id=eq.$trip_id&select=id,trip_status,departure_time,arrival_time,driver_id,vehicle_id,origin_outlet_id,destination_outlet_id,trip_date,created_at,updated_at,company_id");
            $trip = is_array($tripData) && count($tripData) ? $tripData[0] : null;
            
            if (!$trip) {
                return [
                    'success' => false,
                    'error' => 'Trip not found'
                ];
            }

            
            $driver = null;
            $driver_name = 'Unknown Driver';
            $driver_phone = null;
            $driver_status = null;
            if ($trip['driver_id']) {
                $driverData = $this->callSupabase('drivers', "id=eq.{$trip['driver_id']}&select=driver_name,driver_phone,status,license_number,current_location");
                if ($driverData && count($driverData)) {
                    $driver = $driverData[0];
                    $driver_name = $driver['driver_name'] ?? 'Unknown Driver';
                    $driver_phone = $driver['driver_phone'] ?? null;
                    $driver_status = $driver['status'] ?? null;
                }
            }

            
            $vehicle = null;
            $vehicle_name = 'No Vehicle';
            $plate_number = 'N/A';
            $vehicle_status = 'Unknown';
            if ($trip['vehicle_id']) {
                $vehicleData = $this->callSupabase('vehicle', "id=eq.{$trip['vehicle_id']}&select=name,plate_number,status,company_id");
                if ($vehicleData && count($vehicleData)) {
                    $vehicle = $vehicleData[0];
                    $vehicle_name = $vehicle['name'] ?? 'No Vehicle';
                    $plate_number = $vehicle['plate_number'] ?? 'N/A';
                    $vehicle_status = $vehicle['status'] ?? 'Unknown';
                }
            }

            
            $company = null;
            $company_name = 'Unknown Company';
            if ($trip['company_id']) {
                $companyData = $this->callSupabase('companies', "id=eq.{$trip['company_id']}&select=company_name,subdomain,contact_person,contact_phone");
                if ($companyData && count($companyData)) {
                    $company = $companyData[0];
                    $company_name = $company['company_name'] ?? 'Unknown Company';
                }
            }

            
            $stops = $this->callSupabase('trip_stops', "trip_id=eq.$trip_id&select=stop_order,outlet_id,arrival_time,departure_time&order=stop_order.asc");
            $stopsData = [];
            if (is_array($stops)) {
                foreach ($stops as $stop) {
                    $outlet = $this->callSupabase('outlets', "id=eq.{$stop['outlet_id']}&select=outlet_name,address,location,latitude,longitude,contact_person,contact_phone,manager_id");
                    if ($outlet && count($outlet)) {
                        $outletData = $outlet[0];
                        $stopsData[] = [
                            'stop_order' => $stop['stop_order'],
                            'outlet_id' => $stop['outlet_id'],
                            'outlet_name' => $outletData['outlet_name'] ?? 'Unknown Outlet',
                            'address' => $outletData['address'] ?? ($outletData['location'] ?? ''),
                            'location' => $outletData['location'] ?? '',
                            'latitude' => $outletData['latitude'] ?? null,
                            'longitude' => $outletData['longitude'] ?? null,
                            'contact_person' => $outletData['contact_person'] ?? null,
                            'contact_phone' => $outletData['contact_phone'] ?? null,
                            'arrival_time' => $stop['arrival_time'] ?? null,
                            'departure_time' => $stop['departure_time'] ?? null
                        ];
                    }
                }
            }

            
            $driverLoc = ($trip['driver_id'] && $trip_id) ? 
                $this->callSupabase('driver_locations', "driver_id=eq.{$trip['driver_id']}&trip_id=eq.$trip_id&order=created_at.desc&limit=1") : null;
            
            $current_location = null;
            if ($driverLoc && count($driverLoc) && isset($driverLoc[0]['latitude']) && isset($driverLoc[0]['longitude'])) {
                $loc = $driverLoc[0];
                $current_location = [
                    'latitude' => (float)$loc['latitude'],
                    'longitude' => (float)$loc['longitude'],
                    'speed' => (float)($loc['speed'] ?? 0),
                    'heading' => (float)($loc['heading'] ?? 0),
                    'accuracy' => (float)($loc['accuracy'] ?? 0),
                    'timestamp' => $loc['timestamp'] ?? $loc['created_at'],
                    'source' => $loc['source'] ?? 'gps',
                    'is_manual' => $loc['is_manual'] ?? false
                ];
            }

            
            $parcels = $this->callSupabase('parcel_list', "trip_id=eq.$trip_id&select=parcel_id,status,trip_stop_id");
            $total_parcels = is_array($parcels) ? count($parcels) : 0;
            
            
            $parcel_details = [];
            if (is_array($parcels)) {
                foreach ($parcels as $parcelListItem) {
                    if ($parcelListItem['parcel_id']) {
                        $parcelData = $this->callSupabase('parcels', "id=eq.{$parcelListItem['parcel_id']}&select=track_number,sender_name,receiver_name,receiver_phone,parcel_weight,delivery_fee,status,origin_outlet_id,destination_outlet_id");
                        if ($parcelData && count($parcelData)) {
                            $parcel = $parcelData[0];
                            $parcel_details[] = [
                                'track_number' => $parcel['track_number'] ?? '',
                                'sender_name' => $parcel['sender_name'] ?? '',
                                'receiver_name' => $parcel['receiver_name'] ?? '',
                                'receiver_phone' => $parcel['receiver_phone'] ?? '',
                                'parcel_weight' => $parcel['parcel_weight'] ?? 0,
                                'delivery_fee' => $parcel['delivery_fee'] ?? 0,
                                'status' => $parcel['status'] ?? '',
                                'parcel_list_status' => $parcelListItem['status'] ?? 'pending'
                            ];
                        }
                    }
                }
            }

            
            $route_progress = 0;
            $estimated_completion = null;
            switch ($trip['trip_status']) {
                case 'scheduled': 
                    $route_progress = 0; 
                    break;
                case 'accepted':
                    $route_progress = 10;
                    break;
                case 'in_transit': 
                    $route_progress = 50; 
                    
                    if (count($stopsData) > 1) {
                        $completedStops = 0;
                        foreach ($stopsData as $stop) {
                            if ($stop['departure_time']) $completedStops++;
                        }
                        $route_progress = max(10, min(90, ($completedStops / count($stopsData)) * 80 + 10));
                    }
                    break;
                case 'completed': 
                    $route_progress = 100; 
                    break;
                case 'at_outlet':
                    $route_progress = 75;
                    break;
                case 'cancelled':
                    $route_progress = 0;
                    break;
                default: 
                    $route_progress = 0;
            }

            return [
                'success' => true,
                'trip_id' => $trip['id'],
                'trip_status' => $trip['trip_status'],
                'trip_date' => $trip['trip_date'],
                'departure_time' => $trip['departure_time'],
                'arrival_time' => $trip['arrival_time'],
                'created_at' => $trip['created_at'],
                'updated_at' => $trip['updated_at'],
                'total_parcels' => $total_parcels,
                'route_progress' => $route_progress,
                'estimated_completion' => $estimated_completion,
                'last_update' => $current_location ? $current_location['timestamp'] : $trip['updated_at'],
                'company' => [
                    'id' => $trip['company_id'],
                    'name' => $company_name,
                    'contact_person' => $company['contact_person'] ?? null,
                    'contact_phone' => $company['contact_phone'] ?? null
                ],
                'driver' => [
                    'id' => $trip['driver_id'],
                    'driver_name' => $driver_name,
                    'phone' => $driver_phone,
                    'status' => $driver_status,
                    'license_number' => $driver['license_number'] ?? null,
                    'current_location_text' => $driver['current_location'] ?? null
                ],
                'vehicle' => [
                    'id' => $trip['vehicle_id'],
                    'name' => $vehicle_name,
                    'plate_number' => $plate_number,
                    'status' => $vehicle_status
                ],
                'stops' => $stopsData,
                'parcels' => $parcel_details,
                'current_location' => $current_location,
                'origin_outlet_id' => $trip['origin_outlet_id'],
                'destination_outlet_id' => $trip['destination_outlet_id']
            ];

        } catch (Exception $e) {
            error_log('Error tracking trip: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load trip tracking data',
                'details' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    public function getParcelTracking($parcel_id) {
        if (empty($parcel_id)) {
            return [
                'success' => false,
                'error' => 'Parcel ID is required'
            ];
        }
        try {
            $parcelData = $this->callSupabase('parcels', "id=eq.$parcel_id&select=track_number,sender_name,receiver_name,status,parcel_list_status,origin_outlet_id,destination_outlet_id,driver_id");
            $parcel = is_array($parcelData) && count($parcelData) ? $parcelData[0] : null;
            if (!$parcel) {
                return [
                    'success' => false,
                    'error' => 'Parcel not found'
                ];
            }
            $parcelList = $this->callSupabase('parcel_list', "parcel_id=eq.$parcel_id&select=trip_id");
            $trip_id = $parcelList && count($parcelList) ? $parcelList[0]['trip_id'] : null;
            $trip = $trip_id ? $this->callSupabase('trips', "id=eq.$trip_id&select=trip_status,driver_id,vehicle_id") : null;
            $driver = $parcel['driver_id'] ? $this->callSupabase('drivers', "id=eq.{$parcel['driver_id']}&select=driver_name,phone") : null;
            $driver_name = $driver && count($driver) ? $driver[0]['driver_name'] : 'Unknown Driver';
            $driver_phone = $driver && count($driver) ? $driver[0]['phone'] : null;
            $vehicleTable = 'vehicle';
            $vehicle = $trip && count($trip) && $trip[0]['vehicle_id'] ? $this->callSupabase($vehicleTable, "id=eq.{$trip[0]['vehicle_id']}&select=name,plate_number,vehicle_type") : null;
            $vehicle_name = ($vehicle && count($vehicle) && isset($vehicle[0]['name'])) ? $vehicle[0]['name'] : 'No Vehicle';
            $plate_number = ($vehicle && count($vehicle) && isset($vehicle[0]['plate_number'])) ? $vehicle[0]['plate_number'] : 'N/A';
            $vehicle_type = ($vehicle && count($vehicle) && isset($vehicle[0]['vehicle_type'])) ? $vehicle[0]['vehicle_type'] : 'Unknown';
            $origin_outlet = $parcel['origin_outlet_id'] ? $this->callSupabase('outlets', "id=eq.{$parcel['origin_outlet_id']}&select=outlet_name") : null;
            $destination_outlet = $parcel['destination_outlet_id'] ? $this->callSupabase('outlets', "id=eq.{$parcel['destination_outlet_id']}&select=outlet_name") : null;
            $route_data = [
                'origin' => [
                    'outlet_name' => ($origin_outlet && count($origin_outlet) && isset($origin_outlet[0]['outlet_name'])) ? $origin_outlet[0]['outlet_name'] : '',
                    'address' => ($origin_outlet && count($origin_outlet) && isset($origin_outlet[0]['outlet_name'])) ? $origin_outlet[0]['outlet_name'] : ''
                ],
                'destination' => [
                    'outlet_name' => ($destination_outlet && count($destination_outlet) && isset($destination_outlet[0]['outlet_name'])) ? $destination_outlet[0]['outlet_name'] : '',
                    'address' => ($destination_outlet && count($destination_outlet) && isset($destination_outlet[0]['outlet_name'])) ? $destination_outlet[0]['outlet_name'] : ''
                ]
            ];
            $driverLoc = ($parcel['driver_id'] && $trip_id) ? $this->callSupabase('driver_locations', "driver_id=eq.{$parcel['driver_id']}&trip_id=eq.$trip_id&order=created_at.desc&limit=1") : null;
            $current_location = null;
            if ($driverLoc && count($driverLoc) && isset($driverLoc[0]['latitude']) && isset($driverLoc[0]['longitude'])) {
                $loc = $driverLoc[0];
                $current_location = [
                    'latitude' => (float)$loc['latitude'],
                    'longitude' => (float)$loc['longitude'],
                    'speed' => (float)($loc['speed'] ?? 0),
                    'heading' => (float)($loc['heading'] ?? 0),
                    'accuracy' => (float)($loc['accuracy'] ?? 0),
                    'timestamp' => $loc['timestamp'] ?? $loc['created_at']
                ];
            }
            $progress_percentage = 0;
            switch ($parcel['parcel_list_status']) {
                case 'assigned': $progress_percentage = 25; break;
                case 'in_transit': $progress_percentage = 50; break;
                case 'at_outlet': $progress_percentage = 75; break;
                case 'out_for_delivery': $progress_percentage = 90; break;
                case 'delivered': $progress_percentage = 100; break;
                default: $progress_percentage = 0;
            }
            return [
                'success' => true,
                'parcel' => [
                    'track_number' => $parcel['track_number'],
                    'status' => $parcel['parcel_list_status'],
                    'driver_name' => $driver_name,
                    'driver_phone' => $driver_phone
                ],
                'route_data' => $route_data,
                'current_location' => $current_location,
                'tracking_available' => $current_location !== null,
                'progress' => [
                    'progress_percentage' => $progress_percentage,
                    'distance_remaining' => 0
                ]
            ];
        } catch (Exception $e) {
            error_log('Error tracking parcel: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load parcel tracking data',
                'details' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno] $errstr in $errfile on line $errline");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}");
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    $gpsTracker = new GPSTracker();
    switch ($action) {
        case 'track_trip':
            $tripId = $_GET['trip_id'] ?? '';
            echo json_encode($gpsTracker->getTripTracking($tripId));
            break;
        case 'track_parcel':
            $parcelId = $_GET['parcel_id'] ?? '';
            echo json_encode($gpsTracker->getParcelTracking($parcelId));
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action specified']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

