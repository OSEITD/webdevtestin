<?php
session_start();
require_once '../../includes/MultiTenantSupabaseHelper.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    
    
    $destinationOutlet = $_GET['destination_outlet'] ?? null;
    
    if (!$destinationOutlet) {
        echo json_encode([
            "success" => true,
            "trips" => [],
            "message" => "No destination outlet specified"
        ]);
        exit;
    }
    
    
    
    $tripStops = $supabase->get('trip_stops', "outlet_id=eq.$destinationOutlet", 'trip_id');
    
    if (empty($tripStops)) {
        echo json_encode([
            "success" => true,
            "trips" => [],
            "message" => "No trips found with this destination in route"
        ]);
        exit;
    }
    
    
    $tripIds = array_unique(array_column($tripStops, 'trip_id'));
    
    
    $trips = [];
    foreach ($tripIds as $tripId) {
        $trip = $supabase->get('trips', "id=eq.$tripId&trip_status=eq.scheduled", 
            'id,vehicle_id,departure_time,trip_status,created_at');
        
        if (!empty($trip)) {
            $tripData = $trip[0];
            
            
            $vehicle = $supabase->get('vehicle', "id=eq.{$tripData['vehicle_id']}", 'name,plate_number');
            $tripData['vehicle_name'] = !empty($vehicle) ? $vehicle[0]['name'] : 'Unknown';
            $tripData['vehicle_plate'] = !empty($vehicle) ? $vehicle[0]['plate_number'] : 'Unknown';
            
            
            $stops = $supabase->get('trip_stops', "trip_id=eq.$tripId", 'outlet_id,stop_order');
            if (!empty($stops)) {
                
                usort($stops, function($a, $b) { return $a['stop_order'] - $b['stop_order']; });
                
                
                $routeOutlets = [];
                foreach ($stops as $stop) {
                    $outlet = $supabase->get('outlets', "id=eq.{$stop['outlet_id']}", 'outlet_name');
                    $routeOutlets[] = !empty($outlet) ? $outlet[0]['outlet_name'] : 'Unknown';
                }
                $tripData['route'] = $routeOutlets;
                $tripData['total_stops'] = count($stops);
            }
            
            $trips[] = $tripData;
        }
    }
    
    echo json_encode([
        "success" => true,
        "trips" => $trips,
        "count" => count($trips)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
