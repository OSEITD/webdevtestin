<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/OutletAwareSupabaseHelper.php';
session_start();
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
        
        $supabase->update('trips', [
            'trip_status' => 'completed',
            'arrival_time' => $completionTime,
            'updated_at' => $completionTime
        ], 'id=eq.' . urlencode($tripId));
        
    
        $supabase->update('drivers', [
            'status' => 'available',
            'current_trip_id' => null
        ], 'id=eq.' . urlencode($userId));
        
       
        $parcelList = $supabase->get('parcel_list', 'trip_id=eq.' . urlencode($tripId));
        foreach ($parcelList as $parcel) {
            $supabase->update('parcel_list', [
                'status' => 'completed'
            ], 'id=eq.' . urlencode($parcel['id']));
        }
        error_log('Trip completed successfully - delivery_events logging skipped');
    }
    
    try {
     
        $stopParcels = $supabase->get('parcel_list', 'trip_stop_id=eq.' . urlencode($stopId));
        
        foreach ($stopParcels as $parcel) {
            if (!empty($parcel['parcel_id'])) {
                $supabase->update('parcels', [
                    'status' => 'at_outlet'
                ], 'id=eq.' . urlencode($parcel['parcel_id']));
            }
        }
    } catch (Exception $e) {
        error_log('Failed to update parcel status to at_outlet: ' . $e->getMessage());
    
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
