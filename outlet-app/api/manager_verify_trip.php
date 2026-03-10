<?php


ob_start();
require_once __DIR__ . '/../includes/session_manager.php';
initSession();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'outlet_manager') {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Access denied – outlet manager only']);
    exit;
}

try {
    require_once __DIR__ . '/../includes/MultiTenantSupabaseHelper.php';
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server configuration error']);
    exit;
}

ob_end_clean();

try {
    $managerId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    $managerOutletId = $_SESSION['outlet_id'] ?? null;

    if (!$companyId) {
        throw new Exception('Company ID missing from session');
    }

    if (!$managerOutletId) {
        throw new Exception('Outlet ID missing from session');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON body');
    }

    $tripId = trim($input['trip_id'] ?? '');
    $notes  = trim($input['notes']   ?? '');

    if (empty($tripId)) {
        throw new Exception('trip_id is required');
    }

    $supabase = new MultiTenantSupabaseHelper($companyId);

    // ── Fetch & validate the trip ────────────────────────────────────────────
    $trips = $supabase->get('trips', "id=eq.$tripId", '*');
    if (empty($trips)) {
        throw new Exception('Trip not found or access denied');
    }
    $trip = $trips[0];

    if ($trip['manager_verified']) {
        throw new Exception('This trip has already been verified');
    }

    // Only the outlet manager at the DESTINATION outlet can verify a trip
    $tripDestination = $trip['destination_outlet_id'] ?? null;
    if (!$tripDestination || $tripDestination !== $managerOutletId) {
        throw new Exception('Only the manager at the destination outlet can verify this trip');
    }

    if (!$trip['driver_completed']) {
        error_log("WARN: Manager $managerId verifying trip $tripId that driver has NOT marked as completed");
    }

    $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

    // ── Update trip ──────────────────────────────────────────────────────────
    $tripUpdate = [
        'trip_status'         => 'completed',
        'manager_verified'    => true,
        'manager_verified_at' => $now,
        'manager_verified_by' => $managerId,
        'updated_at'          => $now,
    ];

    if (empty($trip['arrival_time'])) {
        $tripUpdate['arrival_time'] = $now;
    }

    $updateResult = $supabase->put("trips?id=eq.$tripId", $tripUpdate);
    if ($updateResult === false) {
        throw new Exception('Failed to update trip record');
    }

    error_log("Trip $tripId verified as completed by manager $managerId");

    // ──  parcel_list → completed ────────────────────────────────────
    try {
        $supabase->put("parcel_list?trip_id=eq.$tripId", [
            'status'     => 'completed',
            'updated_at' => $now,
        ]);
    } catch (Exception $e) {
        error_log("Non-fatal: could not update parcel_list for trip $tripId: " . $e->getMessage());
    }

    // ──  parcels → at_outlet (awaiting customer pickup) ──────────────────
    try {
        $parcelListRows = $supabase->get('parcel_list', "trip_id=eq.$tripId", 'parcel_id');
        if (!empty($parcelListRows)) {
            $parcelIds = array_filter(array_unique(array_column($parcelListRows, 'parcel_id')));
            if (!empty($parcelIds)) {
                $idsList = implode(',', array_map('urlencode', $parcelIds));
                $supabase->put("parcels?id=in.($idsList)", [
                    'status'     => 'at_outlet',
                    'updated_at' => $now,
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Non-fatal: could not mark parcels as at_outlet for trip $tripId: " . $e->getMessage());
    }

    // ── driver → available ─────────────────────────────────────────
    $driverId = $trip['driver_id'] ?? null;
    if ($driverId) {
        try {
            $supabase->put("drivers?id=eq.$driverId", [
                'status'          => 'available',
                'current_trip_id' => null,
                'updated_at'      => $now,
            ]);
        } catch (Exception $e) {
            error_log("Non-fatal: could not reset driver $driverId: " . $e->getMessage());
        }
    }

    // ── vehicle → available ────────────────────────────────────────
    $vehicleId = $trip['vehicle_id'] ?? null;
    if ($vehicleId) {
        try {
            $supabase->put("vehicle?id=eq.$vehicleId", [
                'status'     => 'available',
                'updated_at' => $now,
            ]);
        } catch (Exception $e) {
            error_log("Non-fatal: could not reset vehicle $vehicleId: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success'          => true,
        'message'          => 'Trip verified and marked as completed',
        'trip_id'          => $tripId,
        'verified_at'      => $now,
        'verified_by'      => $managerId,
    ]);

} catch (Exception $e) {
    error_log("manager_verify_trip error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
?>
