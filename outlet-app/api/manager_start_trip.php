<?php
require_once __DIR__ . '/../includes/session_manager.php';
initSession();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/OutletAwareSupabaseHelper.php';
require_once __DIR__ . '/../includes/push_notification_service.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'outlet_manager') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    
    $input = json_decode(file_get_contents('php://input'), true);

    $tripId = $input['trip_id'] ?? $_POST['trip_id'] ?? $_GET['trip_id'] ?? null;
    if (empty($tripId)) {
        throw new Exception('trip_id is required');
    }
    $managerId = $_SESSION['user_id'];

    $supabase = new OutletAwareSupabaseHelper();

    
    $trip = $supabase->get('trips', 'id=eq.' . urlencode($tripId), '*');
    if (empty($trip)) {
        throw new Exception('Trip not found');
    }
    $tripData = $trip[0];

    
    
    
    
    $managerOutlet = $_SESSION['outlet_id'] ?? null;
    $userRole = $_SESSION['role'] ?? null;
    $authorized = false;

    
    $profile = $supabase->get('profiles', 'id=eq.' . urlencode($managerId) . '&select=id,role,outlet_id');
    if (!empty($profile) && isset($profile[0]['role']) && $profile[0]['role'] === 'outlet_manager' && isset($profile[0]['outlet_id']) && $profile[0]['outlet_id'] == $tripData['origin_outlet_id']) {
        $authorized = true;
    }

    
    if (!$authorized) {
        $originOutlet = $supabase->get('outlets', 'id=eq.' . urlencode($tripData['origin_outlet_id']) . '&select=id,manager_id');
        if (!empty($originOutlet) && isset($originOutlet[0]['manager_id']) && $originOutlet[0]['manager_id'] == $managerId) {
            $authorized = true;
        }
    }

    
    if (!$authorized && $userRole === 'super_admin') {
        $authorized = true;
    }

    if (!$authorized) {
        throw new Exception('You are not authorized to start this trip');
    }

    
    if (!in_array($tripData['trip_status'], ['scheduled','accepted'])) {
        throw new Exception('Trip cannot be started from current status: ' . $tripData['trip_status']);
    }

    $now = date('Y-m-d H:i:s');
    $update = [
        'trip_status' => 'in_transit',
        'departure_time' => $now,
        'updated_at' => $now
    ];

    $ok = $supabase->update('trips', $update, 'id=eq.' . urlencode($tripId));
    if (!$ok) {
        throw new Exception('Failed to update trip status. A database trigger may be blocking the update — check Supabase logs.');
    }

    // Verify the update actually took effect
    $verifyTrip = $supabase->get('trips', 'id=eq.' . urlencode($tripId), 'trip_status');
    if (empty($verifyTrip) || $verifyTrip[0]['trip_status'] !== 'in_transit') {
        throw new Exception('Trip status update was rejected by the database. Current status: ' . ($verifyTrip[0]['trip_status'] ?? 'unknown'));
    }

    $existingStops = $supabase->get('trip_stops', 'trip_id=eq.' . urlencode($tripId) . '&select=id');
    if (empty($existingStops)) {
        $originId = $tripData['origin_outlet_id'] ?? null;
        $destId = $tripData['destination_outlet_id'] ?? null;
        $compId = $tripData['company_id'] ?? null;
        if ($originId) {
            $supabase->insert('trip_stops', [
                'trip_id' => $tripId,
                'outlet_id' => $originId,
                'stop_order' => 1,
                'company_id' => $compId,
                'arrival_time' => $now,
                'departure_time' => $now
            ]);
        }
        if ($destId && $destId !== $originId) {
            $supabase->insert('trip_stops', [
                'trip_id' => $tripId,
                'outlet_id' => $destId,
                'stop_order' => 2,
                'company_id' => $compId
            ]);
        }
    }


  
    try {
        // Only set parcels to in_transit if they originate from the trip's origin outlet.
        // Mid-route pickup parcels stay assigned until driver departs from their pickup stop.
        $originOutletId = $tripData['origin_outlet_id'] ?? null;

        $parcelList = $supabase->get('parcel_list', 'trip_id=eq.' . urlencode($tripId), 'id,parcel_id');
        $parcelIds = array_values(array_filter(array_column($parcelList ?? [], 'parcel_id')));

        if (!empty($parcelIds) && $originOutletId) {
            $idsStr = implode(',', array_map('urlencode', $parcelIds));
            $parcelsData = $supabase->get('parcels',
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
            foreach ($originParcelIds as $opId) {
                if (isset($plLookup[$opId])) {
                    $supabase->update('parcel_list', ['status' => 'in_transit', 'updated_at' => $now], 'id=eq.' . urlencode($plLookup[$opId]));
                }
                $supabase->update('parcels', ['status' => 'in_transit', 'updated_at' => $now], 'id=eq.' . urlencode($opId));
            }
        }
    } catch (Exception $e) {
        error_log('Manager start trip parcel update failed: ' . $e->getMessage());
    }

    if (!empty($tripData['vehicle_id'])) {
        try {
            $supabase->update('vehicle', [
                'status'     => 'out_for_delivery',
                'updated_at' => $now
            ], 'id=eq.' . urlencode($tripData['vehicle_id']));
        } catch (Exception $e) {
            error_log('Failed to update vehicle status on trip start: ' . $e->getMessage());
        }
    }

    if (!empty($tripData['driver_id'])) {
        try {
            $supabase->update('drivers', [
                'status'          => 'unavailable',
                'current_trip_id' => $tripId,
                'updated_at'      => $now
            ], 'id=eq.' . urlencode($tripData['driver_id']));
        } catch (Exception $e) {
            error_log('Failed to update driver status on trip start: ' . $e->getMessage());
        }
    }

    try {
        $push = new PushNotificationService($supabase);

        $originOutletData = $supabase->get('outlets', 'id=eq.' . urlencode($tripData['origin_outlet_id']), 'outlet_name');
        $destOutletData   = $supabase->get('outlets', 'id=eq.' . urlencode($tripData['destination_outlet_id']), 'outlet_name');
        $tripData['origin_outlet_name']      = !empty($originOutletData) ? $originOutletData[0]['outlet_name'] : 'Origin';
        $tripData['destination_outlet_name'] = !empty($destOutletData)   ? $destOutletData[0]['outlet_name']   : 'Destination';

        $parcelListForNotif = $supabase->get('parcel_list', 'trip_id=eq.' . urlencode($tripId), 'parcel_id');
        $tripData['parcel_ids'] = array_values(array_filter(array_column($parcelListForNotif ?? [], 'parcel_id')));

        if (!empty($tripData['driver_id'])) {
            error_log("Sending trip started notification to driver: {$tripData['driver_id']}");
            $push->sendToDriver(
                $tripData['driver_id'],
                ' Trip Started',
                'Your trip from ' . $tripData['origin_outlet_name'] . ' to ' . $tripData['destination_outlet_name'] . ' has been started.',
                ['trip_id' => $tripId, 'action' => 'trip_started', 'url' => '/outlet-app/drivers/dashboard.php']
            );
        }

        error_log("Sending trip started notification (manager + customers) for trip: $tripId");
        $push->sendTripStartedNotification($tripId, $tripData);

        // 3. Notify all outlet staff along the route
        error_log("Sending trip started notifications to all outlets in route for trip: $tripId");
        $push->sendToAllOutletsInRoute($tripId, ' Trip Started', 'Trip has started and is now in transit', [
            'trip_id'  => $tripId,
            'action'   => 'trip_started',
            'url'      => '/outlet-app/pages/manager_trips.php',
            'type'     => 'parcel_status_change'
        ]);

    } catch (Exception $e) {
        error_log('Push notification failed: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'trip_id' => $tripId, 'status' => 'in_transit', 'departure_time' => $now]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
