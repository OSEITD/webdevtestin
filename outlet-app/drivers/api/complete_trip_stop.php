<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/OutletAwareSupabaseHelper.php';
require_once '../../includes/push_notification_service.php';
require_once __DIR__ . '/../../includes/session_manager.php';
initSession();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}
$stopId = $input['stop_id'] ?? null;
$completionTime = $input['completion_time'] ?? gmdate('c'); 
if (!$stopId) {
    echo json_encode(['success' => false, 'error' => 'Stop ID is required']);
    exit;
}
try {
    $supabase = new OutletAwareSupabaseHelper();
    
    
    $stop = $supabase->get('trip_stops', 
        'id=eq.' . urlencode($stopId),
        'id,trip_id,outlet_id,stop_order,outlets!outlet_id(outlet_name)'
    );
    
    if (empty($stop)) {
        echo json_encode(['success' => false, 'error' => 'Stop not found']);
        exit;
    }
    
    $stopData = $stop[0];
    $outletName = $stopData['outlets']['outlet_name'] ?? 'Unknown Outlet';
    $tripId = $stopData['trip_id'];
    
    
    $result = $supabase->update('trip_stops', [
        'arrival_time' => $completionTime,
        'departure_time' => $completionTime
    ], 'id=eq.' . urlencode($stopId));
    
    if ($result === false) {
        throw new Exception('Failed to update stop in database');
    }
  
    
    $allTripStops = $supabase->get('trip_stops', 'trip_id=eq.' . urlencode($tripId), 'departure_time');
    $completedStops = 0;
    $totalStops = count($allTripStops);
    
    foreach ($allTripStops as $tripStop) {
        if ($tripStop['departure_time'] !== null) {
            $completedStops++;
        }
    }
    
    $isLastStop = ($completedStops === $totalStops);
    
    
    $response = [
        'success' => true,
        'message' => 'Stop completed successfully',
        'outlet_name' => $outletName,
        'trip_completed' => $isLastStop,
        'completed_stops' => $completedStops,
        'total_stops' => $totalStops
    ];
    
    
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode($response);
    
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) ob_end_flush();
        flush();
    }
    
    
    // Background processing - ensure no output
    ob_start(); // Start new output buffer for background processing
    
    $bgCompanyId = $companyId;
    $bgUserId = $userId;
    $bgSupabase = new OutletAwareSupabaseHelper();
    
    try {
        
        if ($isLastStop) {
            // Mark driver's part done — await manager verification
            $bgSupabase->update('trips', [
                'driver_completed'    => true,
                'driver_completed_at' => $completionTime,
                'arrival_time'        => $completionTime,
                'updated_at'          => $completionTime
            ], 'id=eq.' . urlencode($tripId));
        }
        
        // Update parcel statuses — origin/destination-aware
        // Complete stop = arrive + depart, so:
        //  - Delivery parcels (destination = this outlet) → at_outlet
        //  - Pickup parcels (origin = this outlet) → in_transit
        $allTripParcels = $bgSupabase->get('parcel_list', 'trip_id=eq.' . urlencode($tripId), 'id,parcel_id,status');
        if (!empty($allTripParcels)) {
            $pIds = array_values(array_unique(array_filter(array_column($allTripParcels, 'parcel_id'))));
            if (!empty($pIds)) {
                $pIdsStr = implode(',', array_map('urlencode', $pIds));
                $pData = $bgSupabase->get('parcels', 'id=in.(' . $pIdsStr . ')&select=id,origin_outlet_id,destination_outlet_id');
                $pLookup = [];
                foreach ($pData as $pd) { $pLookup[$pd['id']] = $pd; }

                $stopOutlet = $stopData['outlet_id'];
                foreach ($allTripParcels as $pl) {
                    if (empty($pl['parcel_id']) || !isset($pLookup[$pl['parcel_id']])) continue;
                    $pc = $pLookup[$pl['parcel_id']];

                    if ($pc['destination_outlet_id'] === $stopOutlet) {
                        // Delivery parcel arriving at destination
                        $bgSupabase->update('parcel_list', ['status' => 'at_outlet', 'updated_at' => $completionTime], 'id=eq.' . urlencode($pl['id']));
                        $bgSupabase->update('parcels', ['status' => 'at_outlet', 'updated_at' => $completionTime], 'id=eq.' . urlencode($pl['parcel_id']));
                    } elseif ($pc['origin_outlet_id'] === $stopOutlet) {
                        // Pickup parcel departing from origin
                        $bgSupabase->update('parcel_list', ['status' => 'in_transit', 'updated_at' => $completionTime], 'id=eq.' . urlencode($pl['id']));
                        $bgSupabase->update('parcels', ['status' => 'in_transit', 'updated_at' => $completionTime], 'id=eq.' . urlencode($pl['parcel_id']));
                    }
                }
            }
        }
        
        // Log delivery events for parcels at this stop
        $stopParcels = $bgSupabase->get('parcel_list', 'trip_stop_id=eq.' . urlencode($stopId), 'parcel_id');
        $parcelIds = array_column($stopParcels, 'parcel_id');
        
        if (!empty($parcelIds)) {
            $timestamp = gmdate('c');
            $eventStatus = $isLastStop ? 'trip_completed' : 'stop_completed';
            
            foreach ($parcelIds as $parcelId) {
                $bgSupabase->insert('delivery_events', [
                    'shipment_id' => $parcelId,
                    'status' => $eventStatus,
                    'event_timestamp' => $timestamp,
                    'updated_by' => $bgUserId,
                    'company_id' => $bgCompanyId
                ]);
            }
        }
        
        
        if (class_exists('PushNotificationService')) {
            $pushService = new PushNotificationService($bgSupabase);
            $trip = $bgSupabase->get('trips', 'id=eq.' . urlencode($tripId), 'id,outlet_manager_id');
            if (!empty($trip)) {
                $pushService->sendTripArrivedAtOutletNotification($tripId, $stopData['outlet_id'], $trip[0]);
            }
        }
    } catch (Exception $bgException) {
        error_log('Background processing error: ' . $bgException->getMessage());
    }
    
    // Clean up any background output
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    exit;
    
} catch (Exception $e) {
    error_log('Complete trip stop error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred: ' . $e->getMessage()
    ]);
}
?>
