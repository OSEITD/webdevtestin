<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/OutletAwareSupabaseHelper.php';
require_once '../../includes/push_notification_service.php';
session_start();

ob_end_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'driver') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access. Driver authentication required.'
    ]);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON input'
    ]);
    exit;
}
$stopId = $input['stop_id'] ?? null;
$tripId = $input['trip_id'] ?? null;
$outletId = $input['outlet_id'] ?? null;
$stopId = is_string($stopId) ? trim($stopId) : $stopId;
$tripId = is_string($tripId) ? trim($tripId) : $tripId;
$outletId = is_string($outletId) ? trim($outletId) : $outletId;
if (!$stopId || !$tripId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required trip or stop identifier.'
    ]);
    exit;
}
if ($outletId === '' || $outletId === 'null' || $outletId === 'undefined') {
    $outletId = null;
}
$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'] ?? null;
if (!$companyId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Company context not found in session.'
    ]);
    exit;
}
try {
  
    if (!isset($_SESSION['company_id'])) {
        throw new Exception('Company ID not found in session');
    }
    
    $supabase = new OutletAwareSupabaseHelper();
    $currentTime = gmdate('c'); 
    error_log("Driver {$driverId} arriving at stop {$stopId} (trip {$tripId})");
  
    // Handle fallback stop IDs like 'destination-{trip_id}' or 'origin-{trip_id}'
    if (preg_match('/^(origin|destination)-([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $stopId, $matches)) {
        $stopType = strtolower($matches[1]);
        $extractedTripId = $matches[2];
        error_log("Fallback stop ID detected: type={$stopType}, trip_id={$extractedTripId}");
        
        // Look up the actual trip_stop by trip_id and stop_order
        $stopOrder = ($stopType === 'origin') ? 1 : 2;
        $tripStop = $supabase->get('trip_stops', 
            'trip_id=eq.' . urlencode($extractedTripId) . '&stop_order=eq.' . $stopOrder . '&limit=1',
            'id,outlet_id'
        );
        
        if (empty($tripStop)) {
            // If no stop found by order, try to find by looking at all stops for the trip
            $allStops = $supabase->get('trip_stops', 
                'trip_id=eq.' . urlencode($extractedTripId) . '&order=stop_order.asc',
                'id,outlet_id,stop_order'
            );
            if (!empty($allStops)) {
                // Use first stop for origin, last for destination
                $tripStop = ($stopType === 'origin') ? [$allStops[0]] : [end($allStops)];
            }
        }
        
        if (empty($tripStop)) {
            // CREATE MISSING TRIP STOPS: Get trip details to create the missing stop
            error_log("No trip_stops found - creating from trip data for trip: {$extractedTripId}");
            $trip = $supabase->get('trips', 
                'id=eq.' . urlencode($extractedTripId), 
                'id,origin_outlet_id,destination_outlet_id,company_id'
            );
            
            if (empty($trip)) {
                throw new Exception('Trip not found: ' . $extractedTripId);
            }
            
            $tripData = $trip[0];
            $targetOutletId = ($stopType === 'origin') ? $tripData['origin_outlet_id'] : $tripData['destination_outlet_id'];
            
            if (!$targetOutletId) {
                throw new Exception("Trip {$extractedTripId} missing {$stopType}_outlet_id");
            }
            
            // Create the missing trip_stop
            $newStopData = [
                'trip_id' => $extractedTripId,
                'outlet_id' => $targetOutletId,
                'company_id' => $tripData['company_id'],
                'stop_order' => $stopOrder
            ];
            
            $createdStop = $supabase->insert('trip_stops', $newStopData);
            if (!$createdStop || empty($createdStop)) {
                throw new Exception("Failed to create missing trip_stop for {$stopType} of trip {$extractedTripId}");
            }
            
            // Handle array response from Supabase
            $stopResult = is_array($createdStop) && isset($createdStop[0]) ? $createdStop[0] : $createdStop;
            $stopId = $stopResult['id'];
            $outletId = $targetOutletId;
            error_log("Created missing trip_stop: {$stopId} for {$stopType} outlet: {$targetOutletId}");
        } else {
            $stopId = $tripStop[0]['id'];
            $outletId = $tripStop[0]['outlet_id'] ?? $outletId;
            error_log("Resolved fallback stop ID to: {$stopId}");
        }
    } elseif (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $stopId)) {
        throw new Exception('Invalid stop ID format: ' . $stopId);
    }
    $tripStopUpdated = $supabase->update(
        'trip_stops',
        [
            'arrival_time' => $currentTime
        ],
        'id=eq.' . urlencode($stopId)
    );
    if ($tripStopUpdated === false) {
        throw new Exception('Failed to update trip stop arrival time');
    }
    
    $parcelList = $supabase->get(
        'parcel_list',
        'trip_stop_id=eq.' . urlencode($stopId),
        'id,parcel_id,status'
    );
    $deliverableStatuses = ['pending', 'assigned', 'in_transit'];
    $parcelListItems = array_filter($parcelList ?? [], function ($item) use ($deliverableStatuses) {
        return isset($item['status']) && in_array($item['status'], $deliverableStatuses, true);
    });
    $updatedParcelListIds = [];
    $parcelIds = [];
    
    if (!empty($parcelListItems)) {
        $parcelListIds = array_column($parcelListItems, 'id');
        $parcelIds = array_filter(array_column($parcelListItems, 'parcel_id'));
        
        if (!empty($parcelListIds)) {
            $listIdsStr = implode(',', array_map('urlencode', $parcelListIds));
            $supabase->update('parcel_list', [
                'status' => 'at_outlet',
                'updated_at' => $currentTime
            ], 'id=in.(' . $listIdsStr . ')');
            $updatedParcelListIds = $parcelListIds;
        }
    }
    
    if (!empty($parcelIds)) {
        $parcelIdsStr = implode(',', array_map('urlencode', $parcelIds));
        $supabase->update('parcels', [
            'status' => 'at_outlet',
            'updated_at' => $currentTime
        ], 'id=in.(' . $parcelIdsStr . ')');
    }
    
    if (isset($input['latitude'], $input['longitude']) && $input['latitude'] !== '' && $input['longitude'] !== '') {
        $supabase->insert('driver_locations', [
            'driver_id' => $driverId,
            'trip_id' => $tripId,
            'company_id' => $companyId,
            'latitude' => (float) $input['latitude'],
            'longitude' => (float) $input['longitude'],
            'accuracy' => isset($input['accuracy']) ? (float) $input['accuracy'] : null,
            'speed' => isset($input['speed']) ? (float) $input['speed'] : null,
            'heading' => isset($input['heading']) ? (float) $input['heading'] : null,
            'timestamp' => $currentTime,
            'created_at' => $currentTime,
            'is_manual' => true,
            'source' => 'arrival_marker'
        ]);
    }
    
    $response = [
        'success' => true,
        'message' => 'Successfully arrived at stop',
        'data' => [
            'stop_id' => $stopId,
            'outlet_id' => $outletId,
            'arrival_time' => $currentTime,
            'parcels_updated' => count($updatedParcelListIds),
            'parcel_list_ids' => $updatedParcelListIds,
            'parcel_ids' => $parcelIds
        ]
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
    $bgDriverId = $driverId;
    $bgTripId = $tripId;
    $bgStopId = $stopId;
    $bgOutletId = $outletId;
    $bgParcelIds = $parcelIds;
    $bgCurrentTime = $currentTime;
    $bgSupabase = new OutletAwareSupabaseHelper();
    
    try {
        
        if ($bgOutletId) {
            $outletRecords = $bgSupabase->get(
                'outlets',
                'id=eq.' . urlencode($bgOutletId),
                'id,manager_id,outlet_name'
            );
            $outletData = $outletRecords[0] ?? null;
            $outletName = $outletData['outlet_name'] ?? 'Outlet';
            $managerId = $outletData['manager_id'] ?? null;
            if ($managerId) {
                $bgSupabase->insert('notifications', [
                    'company_id' => $bgCompanyId,
                    'outlet_id' => $bgOutletId,
                    'recipient_id' => $managerId,
                    'sender_id' => $bgDriverId,
                    'title' => 'Driver Arrived with Parcels',
                    'message' => count($bgParcelIds) . ' parcel(s) have arrived at ' . $outletName,
                    'notification_type' => 'delivery_assigned',
                    'priority' => 'high',
                    'status' => 'unread',
                    'data' => json_encode([
                        'trip_id' => $bgTripId,
                        'stop_id' => $bgStopId,
                        'outlet_id' => $bgOutletId,
                        'parcel_count' => count($bgParcelIds),
                        'arrival_time' => $bgCurrentTime
                    ]),
                    'created_at' => $bgCurrentTime
                ]);
            }
            
            if (!empty($bgParcelIds)) {
                $parcelIdsStr = implode(',', array_map('urlencode', $bgParcelIds));
                $parcelsData = $bgSupabase->get(
                    'parcels',
                    'id=in.(' . $parcelIdsStr . ')',
                    'id,track_number,global_receiver_id,receiver_name'
                );
                
                foreach ($parcelsData as $parcelInfo) {
                    $receiverId = $parcelInfo['global_receiver_id'] ?? null;
                    $trackNumber = $parcelInfo['track_number'] ?? null;
                    if ($receiverId && $trackNumber) {
                        $bgSupabase->insert('notifications', [
                            'company_id' => $bgCompanyId,
                            'outlet_id' => $bgOutletId,
                            'recipient_id' => $receiverId,
                            'sender_id' => $bgDriverId,
                            'title' => 'Your Parcel Has Arrived',
                            'message' => 'Your parcel (' . $trackNumber . ') has arrived at ' . $outletName . ' and is ready for pickup',
                            'notification_type' => 'parcel_status_change',
                            'parcel_id' => $parcelInfo['id'],
                            'priority' => 'high',
                            'status' => 'unread',
                            'data' => json_encode([
                                'track_number' => $trackNumber,
                                'status' => 'at_outlet',
                                'outlet_name' => $outletName,
                                'outlet_id' => $bgOutletId,
                                'arrival_time' => $bgCurrentTime
                            ]),
                            'created_at' => $bgCurrentTime
                        ]);
                    }
                }
            }
        }
        
        
        if ($bgOutletId && !empty($bgParcelIds)) {
            $pushService = new PushNotificationService($bgSupabase);
            $tripData = $bgSupabase->get('trips', 'id=eq.' . urlencode($bgTripId), 
                'id,outlet_manager_id,origin_outlet_id,destination_outlet_id,company_id');
            if (!empty($tripData)) {
                $pushService->sendTripArrivedAtOutletNotification($bgTripId, $bgOutletId, $tripData[0]);
            }
        }
    } catch (Exception $bgException) {
        error_log('Background notification error: ' . $bgException->getMessage());
    }
    
    // Clean up any background output
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    exit;
} catch (Exception $e) {
    error_log('Arrive at stop error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
