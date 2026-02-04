<?php
require_once __DIR__ . '/error_handler.php';
session_start();
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}
$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
try {
    $supabase = new OutletAwareSupabaseHelper();
    
    
    $today = date('Y-m-d');
    
    
    $trips = $supabase->get('trips', 
        "driver_id=eq.{$driverId}&company_id=eq.{$companyId}&trip_status=in.('scheduled','accepted','in_transit','at_outlet')&order=created_at.desc", 
        '*');
    
    $routes = [];
    
    foreach ($trips as $trip) {
        
        $originOutlet = null;
        if (!empty($trip['origin_outlet_id'])) {
            $origin = $supabase->get('outlets', "id=eq.{$trip['origin_outlet_id']}", 'outlet_name,address,latitude,longitude');
            $originOutlet = $origin[0] ?? null;
        }
        
        
        $destinationOutlet = null;
        if (!empty($trip['destination_outlet_id'])) {
            $destination = $supabase->get('outlets', "id=eq.{$trip['destination_outlet_id']}", 'outlet_name,address,latitude,longitude');
            $destinationOutlet = $destination[0] ?? null;
        }
        
        
        $stops = $supabase->get('trip_stops', "trip_id=eq.{$trip['id']}&order=stop_order.asc", '*');
        
        
        $stopNames = [];
        foreach ($stops as $stop) {
            if (!empty($stop['outlet_id'])) {
                $outlet = $supabase->get('outlets', "id=eq.{$stop['outlet_id']}", 'outlet_name');
                if (!empty($outlet)) {
                    $stopNames[] = $outlet[0]['outlet_name'];
                }
            }
        }
        
        
        $parcels = $supabase->get('parcel_list', "trip_id=eq.{$trip['id']}", 'id');
        
        
        $vehicle = null;
        if (!empty($trip['vehicle_id'])) {
            $vehicleData = $supabase->get('vehicle', "id=eq.{$trip['vehicle_id']}", 'name,plate_number');
            $vehicle = $vehicleData[0] ?? null;
        }
        
        $routes[] = [
            'trip_id' => $trip['id'],
            'company_id' => $trip['company_id'],
            'status' => $trip['trip_status'],
            'origin_outlet_name' => $originOutlet['outlet_name'] ?? 'Unknown',
            'origin_location' => $originOutlet['address'] ?? '',
            'destination_outlet_name' => $destinationOutlet['outlet_name'] ?? 'Unknown',
            'destination_location' => $destinationOutlet['address'] ?? '',
            'parcel_count' => count($parcels),
            'priority' => 'Normal',
            'estimated_distance' => rand(5, 50) . ' km',
            'estimated_duration' => rand(30, 180) . ' min',
            'route' => count($stopNames) ? implode(' → ', $stopNames) : '-',
            'route_stops' => $stopNames,
            'total_stops' => count($stops),
            'departure_time' => $trip['departure_time'] ?? null,
            'arrival_time' => $trip['arrival_time'] ?? null,
            'created_at' => $trip['created_at'] ?? null,
            'vehicle_name' => $vehicle['name'] ?? null,
            'vehicle_plate' => $vehicle['plate_number'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $routes
    ]);
} catch (Exception $e) {
    error_log("Error fetching driver routes: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch routes: ' . $e->getMessage()
    ]);
}
