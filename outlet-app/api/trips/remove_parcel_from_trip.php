<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Method not allowed. Use POST or DELETE."
    ]);
    exit;
}

require_once __DIR__ . '/../../includes/MultiTenantSupabaseHelper.php';

try {
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    $outletId = $_SESSION['outlet_id'] ?? null;
    $userRole = $_SESSION['role'] ?? 'outlet_manager';

    if (empty($companyId)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Company ID not found in session"
        ]);
        exit;
    }

    // Parse input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['parcel_list_id']) || empty($input['parcel_list_id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Parcel list ID is required"
        ]);
        exit;
    }

    $parcelListId = $input['parcel_list_id'];

    $supabase = new MultiTenantSupabaseHelper($companyId);

    //parcel_list entry with full details
    $parcelListFilter = "id=eq." . urlencode($parcelListId) . "&company_id=eq." . urlencode($companyId);
    $parcelListEntries = $supabase->get('parcel_list', $parcelListFilter);

    if (empty($parcelListEntries)) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Parcel assignment not found or access denied"
        ]);
        exit;
    }

    $parcelListEntry = $parcelListEntries[0];
    $parcelId = $parcelListEntry['parcel_id'];
    $tripId = $parcelListEntry['trip_id'];
    $currentStatus = $parcelListEntry['status'];

    // Checking if parcel is already delivered or in transit
    if (in_array($currentStatus, ['completed', 'in_transit'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Cannot remove parcel with status '$currentStatus'. Only parcels with 'pending' or 'assigned' status can be removed."
        ]);
        exit;
    }

    // Getting trip details to verify authorization
    $tripFilter = "id=eq." . urlencode($tripId) . "&company_id=eq." . urlencode($companyId);
    $trips = $supabase->get('trips', $tripFilter);

    if (empty($trips)) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Trip not found or access denied"
        ]);
        exit;
    }

    $trip = $trips[0];

    $isAuthorized = false;

    if (in_array($userRole, ['admin', 'super_admin'])) {
        $isAuthorized = true;
    } elseif (!empty($outletId)) {
        $isOriginOutlet = !empty($trip['origin_outlet_id']) && $trip['origin_outlet_id'] === $outletId;
        
        $tripStopsFilter = "trip_id=eq." . urlencode($tripId) . "&outlet_id=eq." . urlencode($outletId);
        $tripStops = $supabase->get('trip_stops', $tripStopsFilter);
        $isPartOfRoute = !empty($tripStops);
        
        $isAuthorized = $isOriginOutlet || $isPartOfRoute;
    }

    if (!$isAuthorized) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "error" => "You are not authorized to remove parcels from this trip"
        ]);
        exit;
    }

    // Getting parcel details
    $parcelFilter = "id=eq." . urlencode($parcelId) . "&company_id=eq." . urlencode($companyId);
    $parcels = $supabase->get('parcels', $parcelFilter);

    if (empty($parcels)) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Parcel not found"
        ]);
        exit;
    }

    $parcel = $parcels[0];

    // Deleting the parcel_list entry using direct HTTP DELETE
    $deleteUrl = $supabase->getUrl() . "/rest/v1/parcel_list?id=eq." . urlencode($parcelListId) . "&company_id=eq." . urlencode($companyId);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'DELETE',
            'header' => [
                'Authorization: Bearer ' . $supabase->getKey(),
                'apikey: ' . $supabase->getKey(),
                'Content-Type: application/json'
            ]
        ]
    ]);
    
    $deleteResponse = file_get_contents($deleteUrl, false, $context);
    $deleteResult = ($deleteResponse !== false);

    if ($deleteResult) {
        try {
            $parcelUpdateData = ['status' => 'pending'];
            $supabase->put("parcels?id=eq." . urlencode($parcelId), $parcelUpdateData);
            error_log("Parcel {$parcelId} status updated back to 'pending' after removal from trip {$tripId}");
        } catch (Exception $e) {
            error_log("ERROR: Failed to update parcel status after removal: " . $e->getMessage());
        }

        echo json_encode([
            "success" => true,
            "message" => "Parcel successfully removed from trip",
            "parcel_id" => $parcelId,
            "trip_id" => $tripId,
            "track_number" => $parcel['track_number']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Failed to remove parcel from trip"
        ]);
    }

} catch (Exception $e) {
    error_log("Error in remove_parcel_from_trip.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Failed to remove parcel from trip: " . $e->getMessage()
    ]);
}
?>
