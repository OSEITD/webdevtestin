<?php

ob_start();
require_once __DIR__ . '/../../includes/session_manager.php';
initSession();

try {
    require_once '../../includes/OutletAwareSupabaseHelper.php';
    require_once '../../includes/push_notification_service.php';
} catch (Exception $e) {
    ob_end_clean();
    error_log("Failed to load required files: " . $e->getMessage());
    header("Content-Type: application/json; charset=utf-8");
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Server configuration error"]);
    exit;
}

ob_end_clean();

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

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
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['trip_id'])) {
        throw new Exception("Missing trip_id");
    }

    $tripId    = $input['trip_id'];
    $vehicleId = !empty($input['vehicle_id']) ? $input['vehicle_id'] : null;
    $driverId  = !empty($input['driver_id'])  ? $input['driver_id']  : null;

    if (!$vehicleId && !$driverId) {
        throw new Exception("Provide at least a vehicle_id or driver_id to assign");
    }

    $supabase = new OutletAwareSupabaseHelper();

    $trips = $supabase->get('trips', 'id=eq.' . urlencode($tripId), 'id,trip_status,departure_time,origin_outlet_id,destination_outlet_id');
    if (!$trips || count($trips) === 0) {
        throw new Exception("Trip not found");
    }
    $trip = $trips[0];

    if ($trip['trip_status'] !== 'scheduled') {
        throw new Exception("Vehicle/Driver can only be assigned to trips with status 'scheduled'");
    }

    $now = date('c');
    $updateData = ['updated_at' => $now];
    if ($vehicleId !== null) {
        $updateData['vehicle_id'] = $vehicleId;
    }
    if ($driverId !== null) {
        $updateData['driver_id'] = $driverId;
    }

    $result = $supabase->update('trips', $updateData, 'id=eq.' . urlencode($tripId));
    if ($result === false || (is_array($result) && isset($result['error']))) {
        throw new Exception("Failed to update trip: " . json_encode($result));
    }


    if ($driverId) {
        try {
            $supabase->update('drivers', [
                'current_trip_id' => $tripId,
                'status'          => 'unavailable',
                'updated_at'      => $now,
            ], 'id=eq.' . urlencode($driverId));
        } catch (Exception $e) {
            error_log("assign_trip: driver status update failed - " . $e->getMessage());
        }
    }

    // ── Push notification to driver ───────────────────────────────────────
    if ($driverId) {
        try {
            $originName = 'Unknown';
            $destName   = 'Unknown';

            if (!empty($trip['origin_outlet_id'])) {
                $rows = $supabase->get('outlets', 'id=eq.' . urlencode($trip['origin_outlet_id']), 'outlet_name');
                $originName = $rows[0]['outlet_name'] ?? 'Unknown';
            }
            if (!empty($trip['destination_outlet_id'])) {
                $rows = $supabase->get('outlets', 'id=eq.' . urlencode($trip['destination_outlet_id']), 'outlet_name');
                $destName = $rows[0]['outlet_name'] ?? 'Unknown';
            }

            $push = new PushNotificationService($supabase);
            $push->sendTripAssignmentNotification($driverId, [
                'trip_id'                 => $tripId,
                'origin_outlet_name'      => $originName,
                'destination_outlet_name' => $destName,
                'departure_time'          => $trip['departure_time'] ?? null,
            ]);
        } catch (Exception $e) {
            error_log("assign_trip: push notification failed - " . $e->getMessage());
        }
    }

    echo json_encode([
        "success"    => true,
        "message"    => "Vehicle/Driver assigned successfully",
        "trip_id"    => $tripId,
        "vehicle_id" => $vehicleId,
        "driver_id"  => $driverId,
    ]);

} catch (Exception $e) {
    error_log("assign_trip.php error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
