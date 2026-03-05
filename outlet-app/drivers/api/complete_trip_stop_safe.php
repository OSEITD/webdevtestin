<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/OutletAwareSupabaseHelper.php';
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
    
    $updateData = [
        'arrival_time' => $completionTime,
        'departure_time' => $completionTime
    ];
    
    $result = $supabase->update('trip_stops', 
        $updateData,
        'id=eq.' . urlencode($stopId)
    );
    
    if ($result === false) {
        throw new Exception('Failed to update stop in database');
    }
    
    $tripId = $stopData['trip_id'];
    $allTripStops = $supabase->get('trip_stops', 'trip_id=eq.' . urlencode($tripId));
  
    $completedStops = 0;
    $totalStops = count($allTripStops);
    
    foreach ($allTripStops as $tripStop) {
        if ($tripStop['departure_time'] !== null) {
            $completedStops++;
        }
    }
    
    $isLastStop = ($completedStops === $totalStops);
    
    if ($isLastStop) {
        // Mark driver's part done — await manager verification
        $supabase->update('trips', [
            'driver_completed'    => true,
            'driver_completed_at' => $completionTime,
            'arrival_time'        => $completionTime,
            'updated_at'          => $completionTime
        ], 'id=eq.' . urlencode($tripId));
        error_log('Trip marked as driver_completed - awaiting manager verification');
    }
    
    // Update parcel statuses — origin/destination-aware
    // Complete stop = arrive + depart, so:
    //  - Delivery parcels (destination = this outlet) → at_outlet
    //  - Pickup parcels (origin = this outlet) → in_transit
    try {
        $allTripParcels = $supabase->get('parcel_list', 'trip_id=eq.' . urlencode($tripId), 'id,parcel_id,status');
        if (!empty($allTripParcels)) {
            $pIds = array_values(array_unique(array_filter(array_column($allTripParcels, 'parcel_id'))));
            if (!empty($pIds)) {
                $pIdsStr = implode(',', array_map('urlencode', $pIds));
                $pData = $supabase->get('parcels', 'id=in.(' . $pIdsStr . ')&select=id,origin_outlet_id,destination_outlet_id');
                $pLookup = [];
                foreach ($pData as $pd) { $pLookup[$pd['id']] = $pd; }

                $stopOutlet = $stopData['outlet_id'];
                foreach ($allTripParcels as $pl) {
                    if (empty($pl['parcel_id']) || !isset($pLookup[$pl['parcel_id']])) continue;
                    $pc = $pLookup[$pl['parcel_id']];

                    if ($pc['destination_outlet_id'] === $stopOutlet) {
                        $supabase->update('parcel_list', ['status' => 'at_outlet', 'updated_at' => $completionTime], 'id=eq.' . urlencode($pl['id']));
                        $supabase->update('parcels', ['status' => 'at_outlet', 'updated_at' => $completionTime], 'id=eq.' . urlencode($pl['parcel_id']));
                    } elseif ($pc['origin_outlet_id'] === $stopOutlet) {
                        $supabase->update('parcel_list', ['status' => 'in_transit', 'updated_at' => $completionTime], 'id=eq.' . urlencode($pl['id']));
                        $supabase->update('parcels', ['status' => 'in_transit', 'updated_at' => $completionTime], 'id=eq.' . urlencode($pl['parcel_id']));
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Failed to update parcel statuses: ' . $e->getMessage());
    }
    
    error_log('Stop completed successfully - delivery_events logging skipped');
    
    echo json_encode([
        'success' => true,
        'message' => 'Stop completed successfully',
        'outlet_name' => $outletName,
        'stop_id' => $stopId,
        'completion_time' => $completionTime,
        'trip_completed' => $isLastStop ?? false,
        'total_stops' => $totalStops ?? 0,
        'completed_stops' => $completedStops ?? 0
    ]);
    
} catch (Exception $e) {
    error_log('Complete trip stop error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred: ' . $e->getMessage()
    ]);
}
?>
