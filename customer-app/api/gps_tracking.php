
<?php

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/supabase.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

class GPSTracker {
    private $supabase;
    
    public function __construct() {
        try {
            $this->supabase = getSupabaseClient();
        } catch (Exception $e) {
            error_log("Failed to initialize Supabase client: " . $e->getMessage());
            $this->supabase = null;
        }
    }
    
    
    private function callSupabase($table, $method = 'GET', $params = []) {
        if (!$this->supabase) {
            throw new Exception("Supabase client not available");
        }
        
        try {
            $query = $this->supabase->from($table);
            
            if ($method === 'GET') {
                if (isset($params['select'])) {
                    $query = $query->select($params['select']);
                }
                
                foreach ($params as $key => $value) {
                    if ($key === 'select' || $key === 'order' || $key === 'limit') {
                        continue; // Skip special parameters
                    }
                    
                    if (strpos($value, 'eq.') === 0) {
                        $query = $query->eq($key, substr($value, 3));
                    }
                }
                
                if (isset($params['order'])) {
                    $orderParam = $params['order'];
                    if (strpos($orderParam, '.desc') !== false) {
                        $column = str_replace('.desc', '', $orderParam);
                        $query = $query->order($column, ['ascending' => false]);
                    } elseif (strpos($orderParam, '.asc') !== false) {
                        $column = str_replace('.asc', '', $orderParam);
                        $query = $query->order($column, ['ascending' => true]);
                    } else {
                        $query = $query->order($orderParam);
                    }
                }
                
                // Handle limit
                if (isset($params['limit'])) {
                    $query = $query->limit(intval($params['limit']));
                }
                
                $result = $query->execute();
                return isset($result->data) ? $result->data : [];
            }
            
        } catch (Exception $e) {
            error_log("Supabase API error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getParcelGPSTracking($identifier) {
        try {
            // Checking if identifier is a UUID (parcel_id) or track_number
            $isUUID = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier);

            if ($isUUID) {
                $parcelParams = [
                    'id' => 'eq.' . $identifier,
                    'select' => 'id,track_number,status,driver_id,origin_outlet_id,destination_outlet_id,receiver_address,sender_name,receiver_name'
                ];
            } else {
                $parcelParams = [
                    'track_number' => 'eq.' . $identifier,
                    'select' => 'id,track_number,status,driver_id,origin_outlet_id,destination_outlet_id,receiver_address,sender_name,receiver_name'
                ];
            }

            $parcelData = $this->callSupabase('parcels', 'GET', $parcelParams);
            if (empty($parcelData)) {
                return [
                    'success' => false,
                    'error' => 'Parcel not found'
                ];
            }
            $parcel = $parcelData[0];

            $currentTripId = null;
            $driverLocation = null;
            $tripStatus = null;
            
            if (!empty($parcel['driver_id'])) {
                // Get driver info including current trip
                $driverParams = [
                    'id' => 'eq.' . $parcel['driver_id'],
                    'select' => 'current_trip_id'
                ];
                $driverData = $this->callSupabase('drivers', 'GET', $driverParams);
                if (!empty($driverData) && !empty($driverData[0]['current_trip_id'])) {
                    $currentTripId = $driverData[0]['current_trip_id'];
                    
                    // Get trip status
                    $tripParams = [
                        'id' => 'eq.' . $currentTripId,
                        'select' => 'trip_status'
                    ];
                    $tripData = $this->callSupabase('trips', 'GET', $tripParams);
                    if (!empty($tripData)) {
                        $tripStatus = $tripData[0]['trip_status'];
                    }
                }
                
                // Try to get driver location using intelligent fallback system
                $driverLocation = $this->getDriverLocationWithFallback($parcel['driver_id'], $currentTripId);
            }

            // Fetching trip stops and outlet coordinates
            $stops = [];
            $origin = null;
            $destination = null;
            try {
                if (!empty($parcel['origin_outlet_id']) && !empty($parcel['destination_outlet_id'])) {
                    $tripId = $currentTripId;
                    if ($tripId) {
                        $tripStopsParams = [
                            'trip_id' => 'eq.' . $tripId,
                            'order' => 'stop_order.asc',
                            'select' => 'outlet_id,stop_order'
                        ];
                        $tripStops = $this->callSupabase('trip_stops', 'GET', $tripStopsParams);
                        foreach ($tripStops as $stop) {
                            $outletParams = [
                                'id' => 'eq.' . $stop['outlet_id'],
                                'select' => 'id,outlet_name,address,latitude,longitude'
                            ];
                            $outletData = $this->callSupabase('outlets', 'GET', $outletParams);
                            if (!empty($outletData)) {
                                $stops[] = [
                                    'latitude' => $outletData[0]['latitude'],
                                    'longitude' => $outletData[0]['longitude'],
                                    'outlet_name' => $outletData[0]['outlet_name'],
                                    'address' => $outletData[0]['address']
                                ];
                            }
                        }
                    } else {
                        error_log('No current_trip_id for parcel ' . $parcel['id']);
                    }
                    // Getting origin outlet
                    $originData = $this->callSupabase('outlets', 'GET', [
                        'id' => 'eq.' . $parcel['origin_outlet_id'],
                        'select' => 'id,outlet_name,address,latitude,longitude'
                    ]);
                    if (!empty($originData)) {
                        $origin = [
                            'latitude' => $originData[0]['latitude'],
                            'longitude' => $originData[0]['longitude'],
                            'outlet_name' => $originData[0]['outlet_name'],
                            'address' => $originData[0]['address']
                        ];
                    }
                    // Getting destination outlet
                    $destData = $this->callSupabase('outlets', 'GET', [
                        'id' => 'eq.' . $parcel['destination_outlet_id'],
                        'select' => 'id,outlet_name,address,latitude,longitude'
                    ]);
                    if (!empty($destData)) {
                        $destination = [
                            'latitude' => $destData[0]['latitude'],
                            'longitude' => $destData[0]['longitude'],
                            'outlet_name' => $destData[0]['outlet_name'],
                            'address' => $destData[0]['address']
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log('Error fetching stops/outlets: ' . $e->getMessage());
            }

            return [
                'success' => true,
                'tracking_available' => true,
                'parcel' => $parcel,
                'driver_location' => $driverLocation,
                'route_data' => [
                    'origin' => $origin,
                    'destination' => $destination,
                    'stops' => $stops,
                    'trip_status' => $tripStatus
                ]
            ];

        } catch (Exception $e) {
            error_log("GPS tracking error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve tracking information'
            ];
        }
    }
    
    /**
     * Get driver location with intelligent fallback system
     * Priority: Current trip location -> Recent location -> Last known location
     */
    private function getDriverLocationWithFallback($driverId, $currentTripId = null) {
        try {
            // Priority 1: Try current trip location (most recent)
            if ($currentTripId) {
                $tripLocationParams = [
                    'driver_id' => 'eq.' . $driverId,
                    'trip_id' => 'eq.' . $currentTripId,
                    'order' => 'timestamp.desc',
                    'limit' => 1,
                    'select' => 'latitude,longitude,accuracy,speed,heading,timestamp,created_at'
                ];
                $tripLocationData = $this->callSupabase('driver_locations', 'GET', $tripLocationParams);
                if (!empty($tripLocationData)) {
                    $location = $tripLocationData[0];
                    $location['source'] = 'current_trip';
                    $location['age_minutes'] = $this->calculateAgeMinutes($location['timestamp']);
                    
                    // If location is recent (< 30 minutes), use it
                    if ($location['age_minutes'] < 30) {
                        return $location;
                    }
                }
            }
            
            // Priority 2: Try recent location from any trip (last 2 hours)
            $recentLocationParams = [
                'driver_id' => 'eq.' . $driverId,
                'timestamp' => 'gte.' . date('c', strtotime('-2 hours')),
                'order' => 'timestamp.desc',
                'limit' => 1,
                'select' => 'latitude,longitude,accuracy,speed,heading,timestamp,created_at,trip_id'
            ];
            $recentLocationData = $this->callSupabase('driver_locations', 'GET', $recentLocationParams);
            if (!empty($recentLocationData)) {
                $location = $recentLocationData[0];
                $location['source'] = 'recent_location';
                $location['age_minutes'] = $this->calculateAgeMinutes($location['timestamp']);
                
                // Validate coordinates are within Zambia
                if ($this->isWithinZambia($location['latitude'], $location['longitude'])) {
                    return $location;
                }
            }
            
            // Priority 3: Try last known location (last 24 hours)
            $lastKnownParams = [
                'driver_id' => 'eq.' . $driverId,
                'timestamp' => 'gte.' . date('c', strtotime('-24 hours')),
                'order' => 'timestamp.desc',
                'limit' => 1,
                'select' => 'latitude,longitude,accuracy,speed,heading,timestamp,created_at,trip_id'
            ];
            $lastKnownData = $this->callSupabase('driver_locations', 'GET', $lastKnownParams);
            if (!empty($lastKnownData)) {
                $location = $lastKnownData[0];
                $location['source'] = 'last_known';
                $location['age_minutes'] = $this->calculateAgeMinutes($location['timestamp']);
                
                // Validate coordinates are within Zambia
                if ($this->isWithinZambia($location['latitude'], $location['longitude'])) {
                    return $location;
                }
            }
            
            // Priority 4: Default fallback to Lusaka, Zambia
            return [
                'latitude' => -15.3875,
                'longitude' => 28.3228,
                'accuracy' => null,
                'speed' => null,
                'heading' => null,
                'timestamp' => date('c'),
                'created_at' => date('c'),
                'source' => 'default_fallback',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'age_minutes' => 0,
                'fallback_reason' => 'No recent driver location available'
            ];
            
        } catch (Exception $e) {
            error_log("Error getting driver location with fallback: " . $e->getMessage());
            
            // Return default Zambian location on error
            return [
                'latitude' => -15.3875,
                'longitude' => 28.3228,
                'accuracy' => null,
                'speed' => null,
                'heading' => null,
                'timestamp' => date('c'),
                'created_at' => date('c'),
                'source' => 'error_fallback',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'age_minutes' => 0,
                'fallback_reason' => 'Error retrieving location: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate age of location in minutes
     */
    private function calculateAgeMinutes($timestamp) {
        try {
            $locationTime = new DateTime($timestamp);
            $now = new DateTime();
            $diff = $now->diff($locationTime);
            return ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Check if coordinates are within Zambian boundaries
     */
    private function isWithinZambia($latitude, $longitude) {
        // Zambian boundaries (approximate)
        $zambianBounds = [
            'north' => -8.2,    // Northern border
            'south' => -18.1,   // Southern border  
            'east' => 33.7,     // Eastern border
            'west' => 21.9      // Western border
        ];
        
        return (
            $latitude >= $zambianBounds['south'] && 
            $latitude <= $zambianBounds['north'] &&
            $longitude >= $zambianBounds['west'] && 
            $longitude <= $zambianBounds['east']
        );
    }
    
    
    private function getOutletInfo($originId, $destinationId) {
        $outlets = [];
        
        try {
            $outletIds = array_filter([$originId, $destinationId]);
            
            foreach ($outletIds as $outletId) {
                $outletParams = [
                    'id' => 'eq.' . $outletId,
                    'select' => 'id,outlet_name,address,latitude,longitude'
                ];
                
                $outletData = $this->callSupabase('outlets', 'GET', $outletParams);
                if (!empty($outletData)) {
                    $outlets[$outletId] = $outletData[0];
                }
            }
            
        } catch (Exception $e) {
            error_log("Failed to get outlet data: " . $e->getMessage());
        }
        
        return $outlets;
    }
    
   
    private function calculateRouteProgress($origin, $destination, $currentLocation) {
        if (!$origin || !$destination || !$currentLocation) {
            return null;
        }
        
        $totalDistance = $this->calculateDistance(
            $origin['latitude'], $origin['longitude'],
            $destination['latitude'], $destination['longitude']
        );
        
        $distanceFromOrigin = $this->calculateDistance(
            $origin['latitude'], $origin['longitude'],
            $currentLocation['latitude'], $currentLocation['longitude']
        );
        
        $distanceToDestination = $this->calculateDistance(
            $currentLocation['latitude'], $currentLocation['longitude'],
            $destination['latitude'], $destination['longitude']
        );
        
        $progressPercentage = $totalDistance > 0 ? min(100, ($distanceFromOrigin / $totalDistance) * 100) : 0;
        
        return [
            'origin' => $origin,
            'destination' => $destination,
            'current_position' => [
                'lat' => $currentLocation['latitude'],
                'lng' => $currentLocation['longitude'],
                'timestamp' => $currentLocation['timestamp'],
                'accuracy' => $currentLocation['accuracy'],
                'speed' => $currentLocation['speed'],
                'heading' => $currentLocation['heading']
            ],
            'progress_percentage' => round($progressPercentage, 2),
            'estimated_arrival' => null,
            'distance_remaining' => round($distanceToDestination, 2)
        ];
    }
    
  
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    
}

// Main API handling
try {
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
            case 'track_parcel':
                $parcelId = $_GET['parcel_id'] ?? '';
                if (empty($parcelId)) {
                    echo json_encode(['success' => false, 'error' => 'Parcel ID required']);
                    exit;
                }
                echo json_encode($gpsTracker->getParcelGPSTracking($parcelId));
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("GPS Tracker API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>