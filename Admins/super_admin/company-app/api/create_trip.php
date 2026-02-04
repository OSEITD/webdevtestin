<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/supabase-client.php';// your Supabase client
require_once __DIR__ . '/error-handler.php';

// Global error/exception handlers to ensure JSON responses on fatal errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

function send_json_error_and_exit($message, $code = 500) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

set_exception_handler(function($e) {
    error_log("Uncaught Exception: " . $e->getMessage());
    send_json_error_and_exit('Server exception: ' . $e->getMessage(), 500);
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Fatal error occurred - return JSON instead of HTML
        $msg = isset($err['message']) ? $err['message'] : 'Fatal error';
        error_log("Shutdown fatal error: " . $msg);
        // Clear any previous output
        if (ob_get_length()) ob_end_clean();
        send_json_error_and_exit('Fatal error: ' . $msg, 500);
    }
});

$response = [
    'success' => false,
    'message' => '',
    'error' => null,
];

try {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        throw new Exception("Invalid JSON input");
    }

    // Debug incoming payload
    error_log('Create Trip - Incoming payload: ' . print_r($input, true));

    // Start session to read company/user context if available
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Normalize incoming field names from frontend (support different client names)
    $stopsInput = $input['stops'] ?? $input['route_stops'] ?? [];
    $parcelListInput = $input['parcel_list'] ?? $input['selected_parcels'] ?? [];

    // Normalize potentially malformed company/manager inputs (clients sometimes send [] or empty shapes)
    $rawCompany = $input['company_id'] ?? null;
    if (is_array($rawCompany)) {
        $companyInput = count($rawCompany) ? (is_string($rawCompany[0]) ? $rawCompany[0] : null) : null;
    } elseif (is_string($rawCompany)) {
        $t = trim($rawCompany);
        $companyInput = ($t === '' || $t === '[]' || $t === '"[]"') ? null : $t;
    } else {
        $companyInput = null;
    }

    // Extract origin outlet id from input
    $originOutletId = $input['origin_outlet_id'] ?? $input['origin_outlet'] ?? null;

    // Extract trip data, extracting company_id from session
    $companyId = $_SESSION['id'] ?? null;

    if (empty($originOutletId)) {
        throw new Exception("origin_outlet_id is required to create a trip");
    }

    if (empty($companyId)) {
        throw new Exception("company_id not found in session. Please log in.");
    }

    // Initialize Supabase client to fetch outlet record
    if (!isset($supabase) || $supabase === null) {
        $supabase = new SupabaseClient();
        error_log('Supabase client instantiated in create_trip.php');
    }

    // Fetch the outlet record from outlets table to get manager_id (field renamed)
    $outletPath = "outlets?id=eq.{$originOutletId}&company_id=eq.{$companyId}&select=manager_id";
    error_log("Fetching outlet record from path: {$outletPath}");
    
    try {
        $outletRecord = $supabase->getRecord($outletPath, true); // Use service role for reliable fetch
        error_log("Outlet record fetched: " . print_r($outletRecord, true));
        
        if (empty($outletRecord) || !is_array($outletRecord)) {
            throw new Exception("Outlet not found or invalid response format");
        }
        
        if (count($outletRecord) === 0) {
            throw new Exception("Outlet with ID {$originOutletId} not found for company {$companyId}");
        }
        
        // outlets table now uses `manager_id` for the assigned profile id
        $outletManagerId = $outletRecord[0]['manager_id'] ?? null;
        if (empty($outletManagerId)) {
            throw new Exception("manager_id not found in outlet record. Ensure the outlet has an assigned manager.");
        }
    } catch (Exception $e) {
        error_log("Failed to fetch outlet record: " . $e->getMessage());
        throw $e;
    }

    // Extract trip data
    $tripData = [
        'trip_date' => $input['trip_date'] ?? null,
        'vehicle_id' => $input['vehicle_id'] ?? null,
        'driver_id' => $input['driver_id'] ?? null,
        'company_id' => $companyId,
        'outlet_manager_id' => $outletManagerId,
        // We'll normalize trip_status below to match DB allowed values
        'trip_status' => $input['trip_status'] ?? null,
    ];

    // Normalize and validate trip_status to match DB CHECK constraint
    $allowedStatuses = ['scheduled','in_transit','completed','at_outlet','cancelled'];
    // Common mappings from various frontend forms
    $statusMap = [
        'in-transit' => 'in_transit',
        'in transit' => 'in_transit',
        'in_transit' => 'in_transit',
        'at-outlet' => 'at_outlet',
        'at outlet' => 'at_outlet',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        'scheduled' => 'scheduled',
        'pending' => 'scheduled',
        'in_progress' => 'in_transit'
    ];

    $incomingStatus = strtolower(trim((string)($input['trip_status'] ?? '')));
    if ($incomingStatus === '') {
        $normalized = 'scheduled';
    } elseif (isset($statusMap[$incomingStatus])) {
        $normalized = $statusMap[$incomingStatus];
    } else {
        $normalized = $incomingStatus;
    }

    if (!in_array($normalized, $allowedStatuses, true)) {
        error_log('Create Trip - Invalid trip_status received: ' . $incomingStatus . '. Defaulting to scheduled.');
        $normalized = 'scheduled';
    }

    $tripData['trip_status'] = $normalized;

    // --- CREATE TRIP ---
    error_log('Create Trip - Trip data prepared: ' . print_r($tripData, true));
    error_log('Create Trip - origin_outlet_id: ' . $originOutletId);
    error_log('Create Trip - outlet_manager_id (from outlet table): ' . $outletManagerId);

    // Build a sanitized payload for creating the trip record (do NOT forward nested stops/parcels)
    // Note: database columns are origin_outlet_id and destination_outlet_id
    $tripPayload = [
        'trip_date' => $tripData['trip_date'] ?? null,
        'vehicle_id' => $tripData['vehicle_id'] ?? null,
        'driver_id' => $tripData['driver_id'] ?? null,
        'company_id' => $tripData['company_id'],
        'outlet_manager_id' => $tripData['outlet_manager_id'],
        'trip_status' => $tripData['trip_status'],
        // include origin/destination/departure if present in input (map to DB column names)
        'origin_outlet_id' => $input['origin_outlet_id'] ?? $input['origin_outlet'] ?? null,
        'destination_outlet_id' => $input['destination_outlet_id'] ?? $input['destination_outlet'] ?? null,
        'departure_time' => $input['departure_time'] ?? null
    ];

    // Remove null values to keep payload compact
    $tripPayload = array_filter($tripPayload, function($v){ return $v !== null && $v !== ''; });

    $createdTrip = $supabase->createTrip($tripPayload);

    if (!is_array($createdTrip) || !isset($createdTrip[0]['id'])) {
        throw new Exception("Failed to create trip");
    }

    $tripId = $createdTrip[0]['id'];
    $response['trip_id'] = $tripId;
    $response['trip_created'] = true;

    // --- CREATE STOPS ---
    $createdStops = [];
    $response['trip_stops_created'] = 0;

    if (!empty($stopsInput) && is_array($stopsInput)) {
        foreach ($stopsInput as $idx => $stop) {
            // Normalize incoming stop structure to the trip_stops table schema
            $stopData = [];
            $stopData['trip_id'] = $tripId;
            // outlet_id may be provided as 'outlet_id' or 'id'
            $stopData['outlet_id'] = $stop['outlet_id'] ?? $stop['id'] ?? null;
            // order may be provided as 'outlet_stop_order' or 'order'
            $stopData['stop_order'] = $stop['stop_order'] ?? $stop['order'] ?? ($idx + 2);
            // attach company id for RLS
            $stopData['company_id'] = $tripData['company_id'];

            // Defensive check: ensure required fields exist
            if (empty($stopData['outlet_id'])) {
                error_log('Skipping trip stop - missing outlet_id: ' . json_encode($stop));
                continue;
            }

            $createdStop = $supabase->createTripStop($stopData);

            if (is_array($createdStop) && isset($createdStop[0])) {
                $createdStops[] = $createdStop[0];
            } else {
                // If response not array, still push normalized stop data for reporting
                $createdStops[] = $stopData;
                error_log("Trip stop insert returned non-array, assuming success: " . json_encode($stopData));
            }

            $response['trip_stops_created']++;
        }
    }

    // --- PARCEL ASSIGNMENT ---
    $response['parcel_assignments_created'] = 0;

    if (!empty($parcelListInput) && is_array($parcelListInput)) {
        foreach ($parcelListInput as $parcel) {
            // Build parcel assignment payload
            $parcelIdVal = $parcel['parcel_id'] ?? null;
            $parcelListData = [
                'trip_id' => $tripId,
                'parcel_id' => $parcelIdVal,
                'company_id' => $tripData['company_id']
            ];

            // Debug: log the payload we're about to insert
            error_log('Create Trip - Parcel assignment payload: ' . json_encode($parcelListData));

            // Attempt to fetch the parcel row using service role key to inspect its company_id
            try {
                $parcelRecordPath = "parcels?id=eq.{$parcelIdVal}";
                $fetchedParcel = $supabase->getRecord($parcelRecordPath, true); // use service role to bypass RLS
                error_log('Create Trip - Fetched parcel (service role): ' . print_r($fetchedParcel, true));
            } catch (Exception $e) {
                error_log('Create Trip - Failed to fetch parcel with service role: ' . $e->getMessage());
            }

            $assignedParcel = $supabase->createParcelListAssignment($parcelListData);

            if (!empty($assignedParcel)) {
                $response['parcel_assignments_created']++;
            } else {
                error_log("Parcel assignment failed or returned empty: " . json_encode($parcelListData));
            }
        }
    }

    // --- UPDATE VEHICLE STATUS ---
    if (!empty($tripData['vehicle_id'])) {
        // Log vehicle visibility before PATCH to diagnose 404s caused by RLS or missing rows
        try {
            $vehicleCheckPath = "vehicle?id=eq.{$tripData['vehicle_id']}";
            if (!empty($tripData['company_id'])) {
                $vehicleCheckPath .= "&company_id=eq.{$tripData['company_id']}";
            }
            error_log("Vehicle visibility - vehicle_id: {$tripData['vehicle_id']}, company_id: {$tripData['company_id']}");
            // Anonymous/regular key visibility
            $vehicleRowAnon = $supabase->getRecord($vehicleCheckPath, false);
            error_log("Vehicle visibility (anon key) result: " . print_r($vehicleRowAnon, true));
            // Service role visibility (if service role configured)
            $vehicleRowService = $supabase->getRecord($vehicleCheckPath, true);
            error_log("Vehicle visibility (service role) result: " . print_r($vehicleRowService, true));
        } catch (Exception $e) {
            error_log("Vehicle visibility check failed: " . $e->getMessage());
        }
        // Include company_id filter to satisfy RLS policies on vehicles table
    $vehiclePath = "vehicle?id=eq.{$tripData['vehicle_id']}";
        if (!empty($tripData['company_id'])) {
            $vehiclePath .= "&company_id=eq.{$tripData['company_id']}";
        }
        // Vehicle table enforces a status CHECK constraint. Map internal status to a DB-allowed value.
        // Allowed vehicle statuses (DB): available, unavailable, out_for_delivery
        $vehicleStatusToSet = 'out_for_delivery'; // when a trip is created and vehicle dispatched
        error_log("Updating vehicle status for vehicle_id {$tripData['vehicle_id']} -> {$vehicleStatusToSet}");
        $vehicleUpdate = $supabase->put($vehiclePath, ['status' => $vehicleStatusToSet]);
        if ($vehicleUpdate === false) {
            error_log("Failed to update vehicle status for vehicle_id {$tripData['vehicle_id']}");
        }
    }

    // --- UPDATE DRIVER STATUS ---
    if (!empty($tripData['driver_id'])) {
        // Log driver visibility before PATCH to diagnose 404s caused by RLS or missing rows
        try {
            $driverCheckPath = "drivers?id=eq.{$tripData['driver_id']}";
            if (!empty($tripData['company_id'])) {
                $driverCheckPath .= "&company_id=eq.{$tripData['company_id']}";
            }
            error_log("Driver visibility - driver_id: {$tripData['driver_id']}, company_id: {$tripData['company_id']}");
            // Anonymous/regular key visibility
            $driverRowAnon = $supabase->getRecord($driverCheckPath, false);
            error_log("Driver visibility (anon key) result: " . print_r($driverRowAnon, true));
            // Service role visibility (if service role configured)
            $driverRowService = $supabase->getRecord($driverCheckPath, true);
            error_log("Driver visibility (service role) result: " . print_r($driverRowService, true));
        } catch (Exception $e) {
            error_log("Driver visibility check failed: " . $e->getMessage());
        }
        // Include company_id filter to satisfy RLS policies on drivers table
    $driverPath = "drivers?id=eq.{$tripData['driver_id']}";
        if (!empty($tripData['company_id'])) {
            $driverPath .= "&company_id=eq.{$tripData['company_id']}";
        }
        // Driver table enforces a status CHECK constraint: available, unavailable
        $driverStatusToSet = 'unavailable'; // driver assigned to a trip -> not available
        error_log("Updating driver status for driver_id {$tripData['driver_id']} -> {$driverStatusToSet}");
        $driverUpdate = $supabase->put($driverPath, ['status' => $driverStatusToSet]);
        if ($driverUpdate === false) {
            error_log("Failed to update driver status for driver_id {$tripData['driver_id']}");
        }
    }

    // --- FINAL RESPONSE ---
    $response['success'] = true;
    $response['message'] = "Trip created successfully, with related records.";
    $response['created_trip'] = $createdTrip[0];
    $response['created_stops'] = $createdStops;

    http_response_code(201);
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Log full exception details to a dedicated file for quicker debugging during development
    $logDir = __DIR__ . '/../../super_admin/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $debugLog = $logDir . '/create_trip_debug.log';
    $entry = "[" . date('c') . "] Exception: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\nTrace:\n" . $e->getTraceAsString() . "\n\nPayload was: " . print_r($input, true) . "\n\n";
    // Append to file (suppress errors)
    @file_put_contents($debugLog, $entry, FILE_APPEND | LOCK_EX);

    ErrorHandler::handleException($e, 'create_trip.php');
}
?>
