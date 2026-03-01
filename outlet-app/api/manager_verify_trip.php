<?php
/**
 * POST /api/manager_verify_trip.php
 *
 * Outlet manager verifies that the driver has completed a trip.
 * Sets:
 *   trips.manager_verified        = true
 *   trips.manager_verified_at     = now()
 *   trips.manager_verified_by     = manager profile id
 *   trips.trip_status             = 'completed'
 *   trips.arrival_time            = now()  (if not already set)
 * Then cascades:
 *   parcel_list  status → 'completed'
 *   parcels      status → 'delivered', delivered_at = now()
 *   drivers      status → 'available', current_trip_id = null
 *   vehicle      status → 'available'
 */

ob_start();
session_start();

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

    if (!$companyId) {
        throw new Exception('Company ID missing from session');
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

    if (!$trip['driver_completed']) {
        // Still allow manual verification but log a warning
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
    // Only set arrival_time if it has never been recorded
    if (empty($trip['arrival_time'])) {
        $tripUpdate['arrival_time'] = $now;
    }

    $updateResult = $supabase->put("trips?id=eq.$tripId", $tripUpdate);
    if ($updateResult === false) {
        throw new Exception('Failed to update trip record');
    }

    error_log("Trip $tripId verified as completed by manager $managerId");

    // ── Cascade: parcel_list → completed ────────────────────────────────────
    try {
        $supabase->put("parcel_list?trip_id=eq.$tripId", [
            'status'     => 'completed',
            'updated_at' => $now,
        ]);
    } catch (Exception $e) {
        error_log("Non-fatal: could not update parcel_list for trip $tripId: " . $e->getMessage());
    }

    // ── Cascade: parcels → delivered ────────────────────────────────────────
    try {
        // Find parcel IDs linked to this trip
        $parcelListRows = $supabase->get('parcel_list', "trip_id=eq.$tripId", 'parcel_id');
        if (!empty($parcelListRows)) {
            $parcelIds = array_filter(array_unique(array_column($parcelListRows, 'parcel_id')));
            if (!empty($parcelIds)) {
                $idsList = implode(',', array_map('urlencode', $parcelIds));
                $supabase->put("parcels?id=in.($idsList)", [
                    'status'       => 'delivered',
                    'delivered_at' => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Non-fatal: could not mark parcels as delivered for trip $tripId: " . $e->getMessage());
    }

    // ── Cascade: driver → available ─────────────────────────────────────────
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

    // ── Cascade: vehicle → available ────────────────────────────────────────
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
