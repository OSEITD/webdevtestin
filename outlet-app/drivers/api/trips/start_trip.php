<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../includes/session_manager.php';
initSession();

require_once '../../../includes/supabase-helper.php';
require_once '../../../includes/MultiTenantSupabaseHelper.php';
require_once '../../../includes/push_notification_service.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['trip_id'])) {
        throw new Exception('Trip ID is required');
    }
    
    $tripId = $input['trip_id'];
    
    
    $trip = $supabase->get('trips', "id=eq.$tripId", '*');
    
    if (empty($trip)) {
        throw new Exception('Trip not found');
    }
    
    $tripData = $trip[0];
    
    $putResult = $supabase->put("trips?id=eq." . urlencode($tripId), [
        'trip_status' => 'in_transit',
        'departure_time' => date('Y-m-d H:i:s')
    ]);
    
    if ($putResult === false) {
        throw new Exception('Failed to update trip status. A database trigger may be blocking the update.');
    }

    // Verify the update actually took effect
    $verifyTrip = $supabase->get('trips', "id=eq." . urlencode($tripId), 'trip_status');
    if (empty($verifyTrip) || $verifyTrip[0]['trip_status'] !== 'in_transit') {
        throw new Exception('Trip status update was rejected by the database. Current status: ' . ($verifyTrip[0]['trip_status'] ?? 'unknown'));
    }
    
    $bgCompanyId = $_SESSION['company_id'];
    $bgTripId = $tripId;
    $bgTripData = $tripData;
    
    
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'trip_id' => $tripId,
        'status' => 'in_transit',
        'message' => 'Trip started successfully'
    ]);
    
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        ob_end_flush();
        flush();
    }
    
    
    // Background processing - ensure no output
    ob_start(); // Start new output buffer for background processing
    
    try {
        
        require_once '../../../includes/MultiTenantSupabaseHelper.php';
        $bgSupabase = new MultiTenantSupabaseHelper($bgCompanyId);

        // --- Parcel status updates ---
        // Only set parcels to in_transit if they originate from the trip's origin outlet.
        // Mid-route pickup parcels stay assigned until driver departs from their pickup stop.
        $bgNow = date('Y-m-d H:i:s');
        $originOutletId = $bgTripData['origin_outlet_id'] ?? null;

        $parcelList = $bgSupabase->get('parcel_list', 'trip_id=eq.' . urlencode($bgTripId), 'id,parcel_id');
        $parcelIds  = array_values(array_filter(array_column($parcelList ?? [], 'parcel_id')));

        if (!empty($parcelIds) && $originOutletId) {
            $idsStr = implode(',', array_map('urlencode', $parcelIds));
            $parcelsData = $bgSupabase->get('parcels',
                'id=in.(' . $idsStr . ')&select=id,origin_outlet_id'
            );

            // Find parcels originating from the trip's origin outlet
            $originParcelIds = [];
            foreach ($parcelsData as $p) {
                if (($p['origin_outlet_id'] ?? null) === $originOutletId) {
                    $originParcelIds[] = $p['id'];
                }
            }

            // Build lookup of parcel_id → parcel_list.id
            $plLookup = [];
            foreach ($parcelList as $pl) {
                if (!empty($pl['parcel_id'])) {
                    $plLookup[$pl['parcel_id']] = $pl['id'];
                }
            }

            // Update only origin parcels to in_transit
            if (!empty($originParcelIds)) {
                foreach ($originParcelIds as $opId) {
                    if (isset($plLookup[$opId])) {
                        $bgSupabase->put('parcel_list?id=eq.' . urlencode($plLookup[$opId]), ['status' => 'in_transit', 'updated_at' => $bgNow]);
                    }
                    $bgSupabase->put('parcels?id=eq.' . urlencode($opId), ['status' => 'in_transit', 'updated_at' => $bgNow]);
                }
            }
        }
        // --- End parcel status updates ---

        // Update vehicle → out_for_delivery
        if (!empty($bgTripData['vehicle_id'])) {
            try {
                $bgSupabase->put('vehicle?id=eq.' . urlencode($bgTripData['vehicle_id']), [
                    'status'     => 'out_for_delivery',
                    'updated_at' => $bgNow
                ]);
            } catch (Exception $e) {
                error_log('BG: Failed to update vehicle: ' . $e->getMessage());
            }
        }

        // Update driver → unavailable
        $bgDriverUserId = $bgTripData['driver_id'] ?? null;
        if (!empty($bgDriverUserId)) {
            try {
                $bgSupabase->put('drivers?id=eq.' . urlencode($bgDriverUserId), [
                    'status'          => 'unavailable',
                    'current_trip_id' => $bgTripId,
                    'updated_at'      => $bgNow
                ]);
            } catch (Exception $e) {
                error_log('BG: Failed to update driver status: ' . $e->getMessage());
            }
        }

        $originOutlet = $bgSupabase->get('outlets', "id=eq.{$bgTripData['origin_outlet_id']}", 'outlet_name');
        $destOutlet = $bgSupabase->get('outlets', "id=eq.{$bgTripData['destination_outlet_id']}", 'outlet_name');
        
        $bgTripData['origin_outlet_name'] = !empty($originOutlet) ? $originOutlet[0]['outlet_name'] : 'Origin';
        $bgTripData['destination_outlet_name'] = !empty($destOutlet) ? $destOutlet[0]['outlet_name'] : 'Destination';
        
        
        $parcelList = $bgSupabase->get('parcel_list', "trip_id=eq.$bgTripId", 'parcel_id');
        $bgTripData['parcel_ids'] = array_column($parcelList, 'parcel_id');
        
        
        $pushService = new PushNotificationService($bgSupabase);
        $pushService->sendTripStartedNotification($bgTripId, $bgTripData);
    } catch (Exception $bgException) {
        error_log("Background notification error: " . $bgException->getMessage());
    }
    
    // Clean up any background output
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    exit;
    
} catch (Exception $e) {
    error_log("Start trip error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
