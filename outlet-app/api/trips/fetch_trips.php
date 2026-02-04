<?php
require_once __DIR__ . '/../../includes/security_headers.php';
SecurityHeaders::apply();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    ob_clean();
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - missing company context'
    ]);
    exit();
}

ob_clean();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

function tripIncludesOutlet($tripId, $companyId, $outletId) {
    if (!$outletId) return true; 

    $supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

    try {
        
        $stopsQuery = [
            'trip_id' => 'eq.' . $tripId,
            'company_id' => 'eq.' . $companyId,
            'select' => 'outlet_id'
        ];

        $stopsUrl = $supabaseUrl . "/rest/v1/trip_stops?" . http_build_query($stopsQuery);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $supabaseKey,
                    'apikey: ' . $supabaseKey,
                    'Content-Type: application/json'
                ],
                'ignore_errors' => true,
                'timeout' => 5
            ]
        ]);

        $stopsResponse = @file_get_contents($stopsUrl, false, $context);

        if ($stopsResponse === false || empty($stopsResponse)) {
            return false;
        }

        $stops = @json_decode($stopsResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($stops)) {
            return false;
        }

        
        foreach ($stops as $stop) {
            if (isset($stop['outlet_id']) && $stop['outlet_id'] == $outletId) {
                return true;
            }
        }

        return false;

    } catch (Exception $e) {
        error_log("Error checking if trip {$tripId} includes outlet {$outletId}: " . $e->getMessage());
        return false;
    }
}

function getTripRouteDetails($tripId, $companyId) {
    $supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
    
    $defaultResponse = [
        'route_description' => "Trip " . substr($tripId, 0, 8) . " - No route info",
        'origin' => 'Unknown',
        'destination' => 'Unknown', 
        'intermediate_stops' => [],
        'total_stops' => 0
    ];
    
    try {
        
        $stopsQuery = [
            'trip_id' => 'eq.' . $tripId,
            'company_id' => 'eq.' . $companyId,
            'order' => 'stop_order.asc',
            'select' => 'outlet_id,stop_order'
        ];
        
        $stopsUrl = $supabaseUrl . "/rest/v1/trip_stops?" . http_build_query($stopsQuery);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $supabaseKey,
                    'apikey: ' . $supabaseKey,
                    'Content-Type: application/json'
                ],
                'ignore_errors' => true,
                'timeout' => 5
            ]
        ]);
        
        $stopsResponse = @file_get_contents($stopsUrl, false, $context);
        
        if ($stopsResponse === false || empty($stopsResponse)) {
            return $defaultResponse;
        }
        
        $stops = @json_decode($stopsResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($stops) || empty($stops)) {
            return $defaultResponse;
        }
        
        $outletIds = array_unique(array_column($stops, 'outlet_id'));
        if (empty($outletIds)) {
            return $defaultResponse;
        }
        
        $outletsQuery = [
            'id' => 'in.(' . implode(',', $outletIds) . ')',
            'company_id' => 'eq.' . $companyId,
            'select' => 'id,outlet_name'
        ];
        
        $outletsUrl = $supabaseUrl . "/rest/v1/outlets?" . http_build_query($outletsQuery);
        
        $outletsResponse = @file_get_contents($outletsUrl, false, $context);
        
        if ($outletsResponse === false || empty($outletsResponse)) {
            return $defaultResponse;
        }
        
        $outlets = @json_decode($outletsResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($outlets)) {
            return $defaultResponse;
        }
        
     
        $outletMap = [];
        foreach ($outlets as $outlet) {
            $outletMap[$outlet['id']] = $outlet['outlet_name'];
        }
        
        
        $routeStops = [];
        $intermediateStops = [];
        
        foreach ($stops as $stop) {
            $outletId = $stop['outlet_id'];
            $outletName = isset($outletMap[$outletId]) ? $outletMap[$outletId] : 'Unknown Outlet';
            $routeStops[] = $outletName;
        }
        
        if (empty($routeStops)) {
            return $defaultResponse;
        }
        
        
        $origin = $routeStops[0];
        $destination = end($routeStops);
        
        if (count($routeStops) > 2) {
            $intermediateStops = array_slice($routeStops, 1, -1);
        }
        
        
        $routeDescription = implode(' → ', $routeStops);
        
        return [
            'route_description' => $routeDescription,
            'origin' => $origin,
            'destination' => $destination,
            'intermediate_stops' => $intermediateStops,
            'total_stops' => count($routeStops)
        ];
        
    } catch (Exception $e) {
        error_log("Error fetching trip route details for trip {$tripId}: " . $e->getMessage());
        return $defaultResponse;
    }
}

try {
    $companyId = $_SESSION['company_id'];
    $outletId = $_SESSION['outlet_id'] ?? null;
    $destinationOutlet = $_GET['destination_outlet'] ?? null;
    
    
    
    $disableFilter = isset($_GET['debug_no_filter']) || empty($destinationOutlet);
    
    
    $includeNullDestinations = false; 
    
    if ($disableFilter) {
        error_log("⚠️ FILTER DISABLED - Will show ALL trips for debugging");
        if (empty($destinationOutlet)) {
            error_log("WARNING: No destination_outlet parameter provided!");
        }
    }
    
    if ($includeNullDestinations) {
        error_log("⚠️ TEMPORARY MODE: Including trips with NULL destinations");
    } else {
        error_log("✅ STRICT MODE: Only trips with matching destinations will show");
    }
    
    $trips = [];

    
    if (file_exists('../../includes/MultiTenantSupabaseHelper.php')) {
        require_once '../../includes/MultiTenantSupabaseHelper.php';
        
        if (class_exists('MultiTenantSupabaseHelper')) {
            try {
                $supabase = new MultiTenantSupabaseHelper($companyId);
                $trips = $supabase->get('trips', 'trip_status=eq.scheduled&order=created_at.desc', 'id,vehicle_id,trip_status,departure_time,created_at,outlet_manager_id,driver_id,origin_outlet_id,destination_outlet_id');
                
                if (!is_array($trips)) {
                    $trips = [];
                }
            } catch (Exception $helperError) {
                error_log("MultiTenantSupabaseHelper error: " . $helperError->getMessage());
                
                $trips = [];
            }
        }
    }
    
    
    if (empty($trips)) {
        $supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
        $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
        
        
        $queryParams = [
            'company_id' => 'eq.' . $companyId,
            'trip_status' => 'eq.scheduled', 
            'order' => 'created_at.desc',
            'select' => 'id,vehicle_id,trip_status,departure_time,created_at,outlet_manager_id,driver_id,origin_outlet_id,destination_outlet_id,destination_outlet:destination_outlet_id(id,outlet_name)'
        ];
        
        $url = $supabaseUrl . "/rest/v1/trips?" . http_build_query($queryParams);
        
        error_log("Fetching trips from: $url");
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $supabaseKey,
                    'apikey: ' . $supabaseKey,
                    'Content-Type: application/json',
                    'Cache-Control: no-cache, no-store, must-revalidate',
                    'Pragma: no-cache'
                ],
                'ignore_errors' => true,
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        error_log("API Response status: " . ($response !== false ? "Success" : "Failed"));
        if ($response !== false) {
            error_log("API Response (first 500 chars): " . substr($response, 0, 500));
        }
        
        if ($response !== false && !empty($response)) {
            $decoded = @json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $trips = $decoded;
                error_log("Decoded " . count($trips) . " trips from API");
            } else {
                error_log("JSON decode error: " . json_last_error_msg());
            }
        }
    }

    
    $formattedTrips = [];
    
    error_log("Total trips fetched from DB: " . count($trips));
    
    if (!empty($trips)) {
        
        foreach ($trips as $idx => $trip) {
            $destId = $trip['destination_outlet_id'] ?? 'NULL';
            $destName = isset($trip['destination_outlet']['outlet_name']) ? $trip['destination_outlet']['outlet_name'] : 'N/A';
            error_log("Trip #$idx: ID=" . substr($trip['id'], 0, 8) . ", destination_outlet_id=$destId, destination_name=$destName");
        }
        
        
        $tripIds = [];
        $vehicleIds = [];
        $managerIds = [];
        $tripIdToIndex = [];
        $index = 0;
        
        foreach ($trips as $trip) {
            $tripId = $trip['id'] ?? '';
            
            
            $shouldInclude = false;
            $filterReason = '';
            
            if ($destinationOutlet && !$disableFilter) {
                
                error_log("--- Checking Trip: " . substr($tripId, 0, 8));
                
                
                $tripDestId = $trip['destination_outlet_id'] ?? null;
                error_log("  Comparing IDs:");
                error_log("    - Requested: '$destinationOutlet' (type: " . gettype($destinationOutlet) . ", length: " . strlen($destinationOutlet) . ")");
                error_log("    - Trip has: '" . ($tripDestId ?? 'NULL') . "' (type: " . gettype($tripDestId) . ", length: " . ($tripDestId ? strlen($tripDestId) : 0) . ")");
                error_log("    - Exact match (==): " . ($tripDestId == $destinationOutlet ? 'YES' : 'NO'));
                error_log("    - Strict match (===): " . ($tripDestId === $destinationOutlet ? 'YES' : 'NO'));
                
                if (!empty($tripDestId) && $tripDestId == $destinationOutlet) {
                    $shouldInclude = true;
                    $filterReason = "destination_outlet_id matches (exact: $tripDestId == $destinationOutlet)";
                    error_log("  ✅ MATCH via destination_outlet_id");
                }
                
                
                if (!$shouldInclude && empty($tripDestId) && $includeNullDestinations) {
                    $shouldInclude = true;
                    $filterReason = "TEMPORARY: NULL destination included (needs fixing)";
                    error_log("  ⚠️ INCLUDED despite NULL destination (TEMPORARY MODE)");
                }
                
                
                if (!$shouldInclude) {
                    error_log("  Checking trip_stops table...");
                    if (tripIncludesOutlet($tripId, $companyId, $destinationOutlet)) {
                        $shouldInclude = true;
                        $filterReason = "trip_stops includes outlet $destinationOutlet";
                        error_log("  ✅ MATCH via trip_stops");
                    } else {
                        error_log("  ❌ No match in trip_stops");
                    }
                }
                
                if (!$shouldInclude) {
                    $tripDestName = isset($trip['destination_outlet']['outlet_name']) ? $trip['destination_outlet']['outlet_name'] : 'unknown';
                    error_log("❌ Trip $tripId FILTERED OUT:");
                    error_log("   - Requested destination: $destinationOutlet");
                    error_log("   - Trip destination_outlet_id: " . ($tripDestId ?? 'NULL'));
                    error_log("   - Trip destination name: $tripDestName");
                    error_log("   - Checked trip_stops: No match");
                    continue;
                }
                
                error_log("✅ Trip $tripId INCLUDED: $filterReason");
            } else {
                
                $shouldInclude = true;
                error_log("✅ Trip $tripId INCLUDED: Filtering disabled");
            }
            
            $tripIds[] = $tripId;
            $tripIdToIndex[$tripId] = $index;
            
            
            if (!empty($trip['vehicle_id'])) {
                $vehicleIds[$trip['vehicle_id']] = true;
            }
            if (!empty($trip['outlet_manager_id'])) {
                $managerIds[$trip['outlet_manager_id']] = true;
            }
            
            $status = isset($trip['trip_status']) ? ucfirst(str_replace('_', ' ', $trip['trip_status'])) : 'Unknown Status';
            
            $departureTime = 'Not scheduled';
            if (!empty($trip['departure_time'])) {
                try {
                    $departureTime = date('M j, Y g:i A', strtotime($trip['departure_time']));
                } catch (Exception $e) {
                    $departureTime = 'Invalid date';
                }
            }
            
            
            $formattedTrips[$index] = [
                'id' => $tripId,
                'display_name' => "Trip " . substr($tripId, 0, 8) . " - No route info",
                'vehicle_name' => 'Not assigned',
                'plate_number' => 'N/A',
                'driver_name' => 'Not assigned',
                'manager_name' => 'Not assigned',
                'manager_phone' => 'N/A',
                'status' => $status,
                'departure_time' => $departureTime,
                'trip_status' => $trip['trip_status'] ?? 'unknown',
                'route_full' => "Trip " . substr($tripId, 0, 8) . " - No route info",
                'origin' => 'Unknown',
                'destination' => 'Unknown',
                'intermediate_stops' => [],
                'total_stops' => 0,
                'vehicle_id' => $trip['vehicle_id'] ?? null,
                'manager_id' => $trip['outlet_manager_id'] ?? null
            ];
            
            $index++;
        }
        
        
        $vehicleMap = [];
        if (!empty($vehicleIds)) {
            $vehicleIdsStr = implode(',', array_keys($vehicleIds));
            $vehiclesUrl = $supabaseUrl . "/rest/v1/vehicle?id=in.($vehicleIdsStr)&company_id=eq.$companyId&select=id,name,plate_number";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: Bearer $supabaseKey\r\napikey: $supabaseKey\r\nContent-Type: application/json\r\n",
                    'timeout' => 3
                ]
            ]);
            
            $vehiclesResponse = @file_get_contents($vehiclesUrl, false, $context);
            
            if ($vehiclesResponse && ($vehicles = @json_decode($vehiclesResponse, true))) {
                foreach ($vehicles as $vehicle) {
                    $vehicleMap[$vehicle['id']] = [
                        'name' => $vehicle['name'] ?? 'Unknown Vehicle',
                        'plate_number' => $vehicle['plate_number'] ?? 'N/A'
                    ];
                }
            }
        }
        
        
        $managerMap = [];
        if (!empty($managerIds)) {
            $managerIdsStr = implode(',', array_keys($managerIds));
            $managersUrl = $supabaseUrl . "/rest/v1/profiles?id=in.($managerIdsStr)&company_id=eq.$companyId&select=id,full_name,phone";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: Bearer $supabaseKey\r\napikey: $supabaseKey\r\nContent-Type: application/json\r\n",
                    'timeout' => 3
                ]
            ]);
            
            $managersResponse = @file_get_contents($managersUrl, false, $context);
            
            if ($managersResponse && ($managers = @json_decode($managersResponse, true))) {
                foreach ($managers as $manager) {
                    $managerMap[$manager['id']] = [
                        'full_name' => $manager['full_name'] ?? 'Unknown Manager',
                        'phone' => $manager['phone'] ?? 'N/A'
                    ];
                }
            }
        }
        
        
        foreach ($formattedTrips as $idx => $trip) {
            if (!empty($trip['vehicle_id']) && isset($vehicleMap[$trip['vehicle_id']])) {
                $formattedTrips[$idx]['vehicle_name'] = $vehicleMap[$trip['vehicle_id']]['name'];
                $formattedTrips[$idx]['plate_number'] = $vehicleMap[$trip['vehicle_id']]['plate_number'];
            }
            
            if (!empty($trip['manager_id']) && isset($managerMap[$trip['manager_id']])) {
                $formattedTrips[$idx]['manager_name'] = $managerMap[$trip['manager_id']]['full_name'];
                $formattedTrips[$idx]['manager_phone'] = $managerMap[$trip['manager_id']]['phone'];
            }
        }
        
        
        if (!empty($tripIds)) {
            $supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
            $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
            
            $tripIdsStr = implode(',', $tripIds);
            $stopsUrl = $supabaseUrl . "/rest/v1/trip_stops?trip_id=in.($tripIdsStr)&company_id=eq.$companyId&order=stop_order.asc&select=trip_id,outlet_id,stop_order";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: Bearer $supabaseKey\r\napikey: $supabaseKey\r\nContent-Type: application/json\r\n",
                    'timeout' => 3
                ]
            ]);
            
            $stopsResponse = @file_get_contents($stopsUrl, false, $context);
            
            if ($stopsResponse && ($allStops = @json_decode($stopsResponse, true))) {
                
                $stopsByTrip = [];
                $allOutletIds = [];
                
                foreach ($allStops as $stop) {
                    $stopsByTrip[$stop['trip_id']][] = $stop;
                    $allOutletIds[$stop['outlet_id']] = true;
                }
                
                
                if (!empty($allOutletIds)) {
                    $outletIdsStr = implode(',', array_keys($allOutletIds));
                    $outletsUrl = $supabaseUrl . "/rest/v1/outlets?id=in.($outletIdsStr)&company_id=eq.$companyId&select=id,outlet_name";
                    
                    $outletsResponse = @file_get_contents($outletsUrl, false, $context);
                    
                    if ($outletsResponse && ($outlets = @json_decode($outletsResponse, true))) {
                        
                        $outletMap = [];
                        foreach ($outlets as $outlet) {
                            $outletMap[$outlet['id']] = $outlet['outlet_name'];
                        }
                        
                        
                        foreach ($stopsByTrip as $tripId => $stops) {
                            if (!isset($tripIdToIndex[$tripId])) continue;
                            
                            $routeStops = [];
                            foreach ($stops as $stop) {
                                $outletName = $outletMap[$stop['outlet_id']] ?? 'Unknown';
                                $routeStops[] = $outletName;
                            }
                            
                            if (!empty($routeStops)) {
                                $origin = $routeStops[0];
                                $destination = end($routeStops);
                                $intermediateStops = count($routeStops) > 2 ? array_slice($routeStops, 1, -1) : [];
                                $routeDescription = implode(' → ', $routeStops);
                                
                                $idx = $tripIdToIndex[$tripId];
                                $formattedTrips[$idx]['display_name'] = $routeDescription;
                                $formattedTrips[$idx]['route_full'] = $routeDescription;
                                $formattedTrips[$idx]['origin'] = $origin;
                                $formattedTrips[$idx]['destination'] = $destination;
                                $formattedTrips[$idx]['intermediate_stops'] = $intermediateStops;
                                $formattedTrips[$idx]['total_stops'] = count($routeStops);
                            }
                        }
                    }
                }
            }
        }
    }

    error_log("Trips after filtering: " . count($formattedTrips));
    error_log("=== END FETCH TRIPS DEBUG ===");
    
    
    $message = null;
    if (empty($formattedTrips)) {
        if (!empty($trips) && $destinationOutlet && !$disableFilter) {
            $message = "No trips found going to the selected destination. The existing trip(s) may have NULL destination_outlet_id or don't include this outlet in their route.";
        } else {
            $message = "No scheduled trips found for assignment";
        }
    } elseif ($includeNullDestinations) {
        
        $hasNullDest = false;
        foreach ($trips as $trip) {
            if (empty($trip['destination_outlet_id'])) {
                $hasNullDest = true;
                break;
            }
        }
        if ($hasNullDest) {
            $message = "⚠️ TEMPORARY MODE: Showing trips without destinations. Please set destination_outlet_id for all trips!";
        }
    }

    echo json_encode([
        'success' => true,
        'trips' => $formattedTrips,
        'message' => $message,
        'debug' => [
            'total_trips_in_db' => count($trips),
            'trips_after_filter' => count($formattedTrips),
            'filter_applied' => $destinationOutlet && !$disableFilter,
            'temp_mode_enabled' => $includeNullDestinations
        ]
    ]);

} catch (Exception $e) {
    error_log("Fetch trips fatal error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System error occurred while fetching trips',
        'trips' => [],
        'message' => 'Unable to load trips at the moment'
    ]);
}
?>
