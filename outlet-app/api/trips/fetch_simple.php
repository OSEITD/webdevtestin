<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized. Please log in."
    ]);
    exit;
}

require_once __DIR__ . '/../../includes/MultiTenantSupabaseHelper.php';

try {
    error_log("Simple trips API called");
    
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    $outletId = $_SESSION['outlet_id'] ?? null;

    error_log("Session data: userId=$userId, companyId=$companyId, outletId=$outletId");

    if (empty($companyId)) {
        echo json_encode([
            "success" => false,
            "error" => "Company ID not found in session"
        ]);
        exit;
    }

    if (empty($outletId)) {
        echo json_encode([
            "success" => false,
            "error" => "Outlet ID not found in session"
        ]);
        exit;
    }

    $supabase = new MultiTenantSupabaseHelper($companyId);
    error_log("Supabase helper created successfully");

    
    $tripsFilter = "company_id=eq." . urlencode($companyId) . "&limit=10";
    $trips = $supabase->get('trips', $tripsFilter);
    
    error_log("Trips query result: " . json_encode($trips));

    if (empty($trips)) {
        echo json_encode([
            "success" => true,
            "trips" => [],
            "message" => "No trips found for this company",
            "debug" => [
                "companyId" => $companyId,
                "outletId" => $outletId,
                "filter" => $tripsFilter
            ]
        ]);
        exit;
    }

    
    $simpleTrips = [];
    foreach ($trips as $trip) {
        $simpleTrips[] = [
            'id' => $trip['id'],
            'trip_status' => $trip['trip_status'] ?? 'unknown',
            'departure_time' => $trip['departure_time'] ?? null,
            'driver_id' => $trip['driver_id'] ?? null,
            'vehicle_id' => $trip['vehicle_id'] ?? null,
            'stops' => [],
            'parcels' => [],
            'total_parcels' => 0,
            'is_origin_outlet' => false,
            'outlet_stop_order' => null,
            'driver' => $trip['driver_id'] ? ['id' => $trip['driver_id'], 'driver_name' => 'Test Driver'] : null,
            'vehicle' => $trip['vehicle_id'] ? ['id' => $trip['vehicle_id'], 'name' => 'Test Vehicle'] : null
        ];
    }

    echo json_encode([
        "success" => true,
        "trips" => $simpleTrips,
        "total_trips" => count($simpleTrips),
        "debug" => [
            "companyId" => $companyId,
            "outletId" => $outletId
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in simple trips API: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        "success" => false,
        "error" => "Failed to fetch trips: " . $e->getMessage(),
        "debug" => [
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ]
    ]);
}
?>