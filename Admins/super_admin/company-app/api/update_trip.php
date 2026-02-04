<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

// Ensure clean JSON responses on fatal errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

function send_json_error_and_exit($message, $code = 500) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

set_exception_handler(function($e) {
    error_log("Uncaught Exception in update_trip.php: " . $e->getMessage());
    send_json_error_and_exit('Server exception: ' . $e->getMessage(), 500);
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_end_clean();
        send_json_error_and_exit('Fatal error: ' . ($err['message'] ?? 'unknown'), 500);
    }
});

$response = ['success' => false, 'message' => '', 'error' => null];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    error_log('Update Trip - Incoming payload: ' . print_r($input, true));

    if (session_status() === PHP_SESSION_NONE) session_start();

    $tripId = $input['id'] ?? $input['trip_id'] ?? null;
    if (empty($tripId)) {
        send_json_error_and_exit('Missing trip id', 400);
    }

    // Reuse Supabase client
    $supabase = new SupabaseClient();

    // Normalize stops and parcels coming from client
    $stopsInput = $input['stops'] ?? $input['route_stops'] ?? [];
    $parcelListInput = $input['parcel_list'] ?? $input['selected_parcels'] ?? [];

    // Build the sanitized trip payload for PATCH (only allowed top-level fields)
    $tripPayload = [
        'trip_date' => $input['trip_date'] ?? null,
        'vehicle_id' => $input['vehicle_id'] ?? null,
        'driver_id' => $input['driver_id'] ?? null,
        'trip_status' => $input['trip_status'] ?? null,
        'origin_outlet_id' => $input['origin_outlet_id'] ?? $input['origin_outlet'] ?? null,
        'destination_outlet_id' => $input['destination_outlet_id'] ?? $input['destination_outlet'] ?? null,
        'departure_time' => $input['departure_time'] ?? null,
        'arrival_time' => $input['arrival_time'] ?? null,
        // sanitize company_id: client sometimes sends [] or empty shapes — coerce to null if invalid
        'company_id' => (function($in) {
            if (!isset($in)) return null;
            // if it's an array but empty, treat as null
            if (is_array($in)) {
                return count($in) ? (is_string($in[0]) ? $in[0] : null) : null;
            }
            // if it's a string, trim and reject empty/bracket shapes
            if (is_string($in)) {
                $t = trim($in);
                if ($t === '' || $t === '[]' || $t === '"[]"') return null;
                return trim($t, ' "\'');
            }
            return null;
        })($input['company_id'] ?? null),
    ];

    // Remove null/empty fields
    $tripPayload = array_filter($tripPayload, function($v){ return $v !== null && $v !== ''; });

    // Update trip record using PATCH via put() helper. Path expects table and query string.
    $patchPath = "trips?id=eq.{$tripId}";
    // Use service-role key when available inside put()
    $updateResult = $supabase->put($patchPath, $tripPayload);

    // If put returns false or null, consider it an error
    if ($updateResult === false || $updateResult === null) {
        error_log('Update Trip - Warning: trip update returned false/null');
    }

    // Determine authoritative company_id for this trip so inserted rows align
    $tripCompanyId = null;
    try {
        // Use service role to bypass RLS when reading the trip record
        $tripRec = $supabase->getRecord("trips?id=eq.{$tripId}", true);
        if (is_array($tripRec) && count($tripRec) > 0) {
            $tripCompanyId = $tripRec[0]['company_id'] ?? null;
        }
    } catch (Exception $e) {
        error_log('Update Trip - Warning: failed fetching trip record for company_id: ' . $e->getMessage());
    }

    // Final company id used for created stops and parcel_list rows (fallback order)
    // prefer sanitized company_id from tripPayload, then session, then trip record
    $companyIdForInserts = $tripPayload['company_id'] ?? $_SESSION['company_id'] ?? $tripCompanyId ?? null;

    // Remove existing trip_stops for this trip (to replace them)
    try {
        // Use service role key directly via a delete REST call by constructing URL
        $deleteStopsPath = "trip_stops?trip_id=eq.{$tripId}";
        // Use put() with empty data but method patch is used; instead call makeRequest via client by sending DELETE
        // The client does not expose a delete helper; we can call the REST endpoint by using makeRequest indirectly through getRecord with a specially constructed URL is not ideal.
        // Instead, use the Supabase REST DELETE via a manual CURL inside this file to ensure deletion using service role key.

        $serviceRole = $supabase->getKey();
        // Prefer SUPABASE_SERVICE_ROLE if set
        $serviceRoleEnv = getenv('SUPABASE_SERVICE_ROLE');
        if (!empty($serviceRoleEnv)) $serviceRole = $serviceRoleEnv;

        $deleteUrl = rtrim($supabase->getUrl(), '/') . "/rest/v1/trip_stops?trip_id=eq.{$tripId}";
        $ch = curl_init($deleteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabase->getKey(),
            'Authorization: Bearer ' . $serviceRole,
            'Prefer: return=minimal'
        ]);
        $delResp = curl_exec($ch);
        $delErr = curl_error($ch);
        $delCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        error_log("Delete trip_stops response code: {$delCode}, err: {$delErr}, body: {$delResp}");
        if ($delCode >= 400) {
            error_log('Update Trip - Warning: failed deleting existing trip_stops');
        }
    } catch (Exception $e) {
        error_log('Update Trip - Exception while deleting trip_stops: ' . $e->getMessage());
    }

    // Remove existing parcel assignments for this trip
    try {
        $serviceRole = $supabase->getKey();
        $serviceRoleEnv = getenv('SUPABASE_SERVICE_ROLE');
        if (!empty($serviceRoleEnv)) $serviceRole = $serviceRoleEnv;
    $deleteUrl = rtrim($supabase->getUrl(), '/') . "/rest/v1/parcel_list?trip_id=eq.{$tripId}";
        $ch = curl_init($deleteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabase->getKey(),
            'Authorization: Bearer ' . $serviceRole,
            'Prefer: return=minimal'
        ]);
        $delResp = curl_exec($ch);
        $delErr = curl_error($ch);
        $delCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    error_log("Delete parcel_list response code: {$delCode}, err: {$delErr}, body: {$delResp}");
        if ($delCode >= 400) {
            error_log('Update Trip - Warning: failed deleting existing parcel assignments');
        }
    } catch (Exception $e) {
    error_log('Update Trip - Exception while deleting parcel_list: ' . $e->getMessage());
    }

    // Insert new stops
    $createdStops = [];
    if (!empty($stopsInput) && is_array($stopsInput)) {
        foreach ($stopsInput as $idx => $stop) {
            $stopData = [];
            $stopData['trip_id'] = $tripId;
            $stopData['outlet_id'] = $stop['outlet_id'] ?? $stop['id'] ?? null;
            $stopData['stop_order'] = $stop['stop_order'] ?? $stop['outlet_stop_order'] ?? $stop['order'] ?? ($idx + 1);
            // company_id if available in session or input
            $stopData['company_id'] = $companyIdForInserts;

            if (empty($stopData['outlet_id'])) {
                error_log('Update Trip - skipping stop because outlet_id missing: ' . json_encode($stop));
                continue;
            }

            $res = $supabase->createTripStop($stopData);
            $createdStops[] = $res;
        }
    }

    // Insert parcel assignments
    $createdParcels = 0;
    if (!empty($parcelListInput) && is_array($parcelListInput)) {
        error_log('Update Trip - companyIdForInserts: ' . ($companyIdForInserts ?? 'null'));
        foreach ($parcelListInput as $parcel) {
            // Normalize parcel id: support shapes like {parcel_id: 'uuid'} or {id: 'uuid'}
            $rawPid = $parcel['parcel_id'] ?? $parcel['id'] ?? null;

            // If client sent an array or JSON-encoded empty array, normalize it
            if (is_array($rawPid)) {
                $rawPid = count($rawPid) ? $rawPid[0] : null;
            }
            if (is_string($rawPid)) {
                $trimmed = trim($rawPid);
                // handle values like '[]' or '"[]"'
                if ($trimmed === '[]' || $trimmed === '"[]"' || $trimmed === "''") {
                    $rawPid = null;
                } else {
                    $rawPid = trim($trimmed, '"' . "' ");
                }
            }

            // Basic UUID v4-ish validation (hex and dashes)
            $pid = null;
            if (!empty($rawPid) && is_string($rawPid)) {
                if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $rawPid)) {
                    $pid = $rawPid;
                } else {
                    error_log('Update Trip - parcel_id failed UUID validation, skipping: ' . var_export($rawPid, true));
                    $pid = null;
                }
            }

            if (empty($pid)) {
                error_log('Update Trip - skipping parcel assignment missing or invalid parcel_id: ' . json_encode($parcel));
                continue;
            }

            $parcelData = [
                'trip_id' => $tripId,
                'parcel_id' => $pid,
                'company_id' => $companyIdForInserts
            ];

            $assign = $supabase->createParcelListAssignment($parcelData);
            if ($assign) $createdParcels++;
        }
    }

    // Update vehicle/driver statuses based on the new trip status.
    // If trip_status === 'completed' we set assigned driver and vehicle to 'available'.
    // If trip_status is other active state, we mark them as unavailable/out_for_delivery.
    try {
        $newStatus = $tripPayload['trip_status'] ?? null;

        // Determine assigned vehicle_id and driver_id. Prefer payload, then fetched trip record,
        // then attempt a fresh read of the trip if necessary.
        $vehicleId = $tripPayload['vehicle_id'] ?? null;
        $driverId = $tripPayload['driver_id'] ?? null;

        if (empty($vehicleId) || empty($driverId)) {
            if (!isset($tripRec) || !is_array($tripRec) || count($tripRec) === 0) {
                try {
                    $tripRec = $supabase->getRecord("trips?id=eq.{$tripId}", true);
                } catch (Exception $e) {
                    error_log('Update Trip - unable to fetch trip record for driver/vehicle ids: ' . $e->getMessage());
                }
            }
            if (is_array($tripRec) && count($tripRec) > 0) {
                $existing = $tripRec[0];
                $vehicleId = $vehicleId ?: ($existing['vehicle_id'] ?? $existing['vehicle'] ?? null);
                $driverId = $driverId ?: ($existing['driver_id'] ?? $existing['driver'] ?? null);
                // If nested driver/vehicle objects were returned, extract id
                if (is_array($driverId) && isset($driverId[0]['id'])) $driverId = $driverId[0]['id'];
                if (is_array($vehicleId) && isset($vehicleId[0]['id'])) $vehicleId = $vehicleId[0]['id'];
            }
        }

        // Helper to update status safely
        $updateIfPresent = function($table, $id, $statusVal) use ($supabase) {
            if (empty($id)) return;
            $path = "{$table}?id=eq.{$id}";
            if (!empty($_SESSION['company_id'])) $path .= "&company_id=eq.{$_SESSION['company_id']}";
            try {
                $supabase->put($path, ['status' => $statusVal]);
            } catch (Exception $e) {
                error_log("Update Trip - failed updating {$table} status for id {$id}: " . $e->getMessage());
            }
        };

        if ($newStatus === 'completed') {
            $updateIfPresent('vehicle', $vehicleId, 'available');
            $updateIfPresent('drivers', $driverId, 'available');
        } else {
            // For any active/assigned trip statuses mark them as unavailable/out_for_delivery
            // This preserves existing behavior for non-completed updates.
            $updateIfPresent('vehicle', $vehicleId, 'out_for_delivery');
            $updateIfPresent('drivers', $driverId, 'unavailable');
        }
    } catch (Exception $e) {
        error_log('Update Trip - error updating driver/vehicle status: ' . $e->getMessage());
    }

    $response['success'] = true;
    $response['message'] = 'Trip updated successfully';
    $response['trip_id'] = $tripId;
    $response['stops_created'] = count($createdStops);
    $response['parcel_assignments_created'] = $createdParcels;

    http_response_code(200);
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'update_trip.php');
}

?>
