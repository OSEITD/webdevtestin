<?php
session_start();
require_once '../includes/MultiTenantSupabaseHelper.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['trip_id']) || empty($input['vehicle_id'])) {
        throw new Exception("Missing required fields: trip_id and vehicle_id");
    }
    
    $tripId = $input['trip_id'];
    $vehicleId = $input['vehicle_id'];
    
    error_log("Trip assignment: Trip=$tripId, Vehicle=$vehicleId");
    
    $trip = $supabase->get("trips", "id=eq.$tripId", "trip_status,vehicle_id");
    if (!$trip || empty($trip)) {
        throw new Exception("Trip not found");
    }
    
    $tripData = $trip[0];
    if ($tripData['trip_status'] !== 'scheduled') {
        throw new Exception("Trip must be scheduled. Current: " . $tripData['trip_status']);
    }
    
    $vehicle = $supabase->get("vehicle", "id=eq.$vehicleId", "status");
    if (!$vehicle || empty($vehicle)) {
        throw new Exception("Vehicle not found");
    }
    
    $vehicleData = $vehicle[0];
    if (!in_array($vehicleData['status'], ['available', 'out_for_delivery'])) {
        throw new Exception("Vehicle must be available or out_for_delivery. Current: " . $vehicleData['status']);
    }
    
    if (!empty($tripData['vehicle_id']) && $tripData['vehicle_id'] === $vehicleId && $tripData['trip_status'] === 'in_transit') {
        echo json_encode([
            "success" => true,
            "message" => "Trip already in transit with this vehicle",
            "trip_id" => $tripId,
            "vehicle_id" => $vehicleId,
            "trip_status" => $tripData['trip_status'],
            "vehicle_status" => $vehicleData['status']
        ]);
        exit;
    }
    
    error_log("Attempting to update trip $tripId with vehicle $vehicleId");
    $tripUpdate = $supabase->put("trips?id=eq.$tripId", [
        'vehicle_id' => $vehicleId,
        'trip_status' => 'in_transit',
        'updated_at' => date('c')
    ]);
    
    error_log("Trip update result: " . json_encode($tripUpdate));
    
    if ($tripUpdate === false) {
        throw new Exception("Failed to update trip");
    }
    
    error_log("Attempting to update vehicle $vehicleId to out_for_delivery status");
    $vehicleUpdate = $supabase->put("vehicle?id=eq.$vehicleId", [
        'status' => 'out_for_delivery'
    ]);
    
    error_log("Vehicle update result: " . json_encode($vehicleUpdate));
    
    if ($vehicleUpdate === false) {
        error_log("Vehicle update failed, rolling back trip assignment");
        $supabase->put("trips?id=eq.$tripId", [
            'vehicle_id' => null,
            'trip_status' => 'scheduled',
            'updated_at' => date('c')
        ]);
        throw new Exception("Failed to update vehicle - rolled back trip assignment");
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Trip assigned successfully",
        "trip_id" => $tripId,
        "vehicle_id" => $vehicleId,
        "trip_status" => "in_transit",
        "vehicle_status" => "out_for_delivery"
    ]);

    $updatedTrip = $supabase->get("trips", "id=eq.$tripId", "*");
    if ($updatedTrip && !empty($updatedTrip)) {
        echo json_encode([
            "success" => true,
            "message" => "Trip assigned and fetched successfully",
            "trip" => $updatedTrip[0]
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "message" => "Trip assigned successfully but failed to fetch updated trip",
            "trip_id" => $tripId
        ]);
    }
    
} catch (Exception $e) {
    error_log("Assignment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
