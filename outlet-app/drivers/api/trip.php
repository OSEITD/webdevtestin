<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
session_start();

try {
    require_once '../../includes/OutletAwareSupabaseHelper.php';
    require_once '../../includes/push_notification_service.php';
} catch (Exception $e) {
    ob_end_clean();
    error_log("Failed to load dependencies: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server configuration error']);
    exit;
}

ob_end_clean();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (!in_array($action, ['start', 'accept'])) {
	echo json_encode(['success' => false, 'error' => 'Invalid action. Use "start" or "accept"']);
	exit;
}
$trip_id = $_GET['trip_id'] ?? $_POST['trip_id'] ?? '';
$driver_id = $_GET['driver_id'] ?? $_POST['driver_id'] ?? ($_SESSION['user_id'] ?? '');
$company_id = $_SESSION['company_id'] ?? '';
if (!$trip_id || !$driver_id || !$company_id) {
	echo json_encode(['success' => false, 'error' => 'Missing trip_id, driver_id, or company_id']);
	exit;
}
$supabase = new OutletAwareSupabaseHelper();
try {
	if ($action === 'accept') {
		
		
		$supabase->update('trips', [
			'trip_status' => 'accepted',
			'driver_id' => $driver_id
		], 'id=eq.' . urlencode($trip_id));
		
		$supabase->update('drivers', [
			'current_trip_id' => $trip_id
		], 'id=eq.' . urlencode($driver_id));
		echo json_encode([
			'success' => true, 
			'message' => 'Trip accepted successfully',
			'action' => 'accepted'
		]);
		exit;
		
	} else if ($action === 'start') {
		
		$currentTimestamp = date('Y-m-d H:i:s');
		
		// Update trip status to in_transit with departure time
		$supabase->update('trips', [
			'trip_status' => 'in_transit',
			'departure_time' => $currentTimestamp,
			'driver_id' => $driver_id,
			'updated_at' => $currentTimestamp
		], 'id=eq.' . urlencode($trip_id));
		
		// Update driver status to unavailable
		$supabase->update('drivers', [
			'status' => 'unavailable',
			'current_trip_id' => $trip_id,
			'updated_at' => $currentTimestamp
		], 'id=eq.' . urlencode($driver_id));
		
		// AUTO-DEPART from origin outlet: Get the trip's origin_outlet_id and set departure_time on its trip_stop
		$tripDetails = $supabase->get('trips', 'id=eq.' . urlencode($trip_id), 'origin_outlet_id');
		if (!empty($tripDetails) && !empty($tripDetails[0]['origin_outlet_id'])) {
			$originOutletId = $tripDetails[0]['origin_outlet_id'];
			
			// Find the trip_stop for the origin outlet (should be stop_order=1 or the first stop)
			$originStops = $supabase->get('trip_stops', 
				'trip_id=eq.' . urlencode($trip_id) . 
				'&outlet_id=eq.' . urlencode($originOutletId) . 
				'&select=id,arrival_time,departure_time'
			);
			
			if (!empty($originStops)) {
				$originStop = $originStops[0];
				// Set both arrival_time (driver was at origin) and departure_time (now departing)
				$supabase->update('trip_stops', [
					'arrival_time' => $currentTimestamp,
					'departure_time' => $currentTimestamp
				], 'id=eq.' . urlencode($originStop['id']));
				
				error_log("Auto-departed from origin outlet: stop_id={$originStop['id']}, outlet_id=$originOutletId");
			} else {
				error_log("No trip_stop found for origin outlet: trip_id=$trip_id, outlet_id=$originOutletId");
			}
		}
		
		$parcelList = $supabase->get('parcel_list', 'trip_id=eq.' . urlencode($trip_id) . '&select=parcel_id');
		$parcelIds = array_column($parcelList, 'parcel_id');
		
		if ($parcelIds) {
			
			$supabase->update('parcel_list', [
				'status' => 'in_transit',
				'updated_at' => date('Y-m-d H:i:s')
			], 'trip_id=eq.' . urlencode($trip_id));
			
			$idsStr = implode(',', array_map('urlencode', $parcelIds));
			$supabase->update('parcels', [
				'status' => 'in_transit',
				'driver_id' => $driver_id,
				'updated_at' => date('Y-m-d H:i:s')
			], 'id=in.(' . $idsStr . ')');
		}
		
		$response = [
			'success' => true, 
			'message' => 'Trip started successfully',
			'action' => 'started',
			'parcels_updated' => count($parcelIds)
		];
		
		
		$bgCompanyId = $company_id;
		$bgDriverId = $driver_id;
		$bgTripId = $trip_id;
		
		ob_end_clean();
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
		ob_start();
		
		try {
			// OPTIMIZED: Only send essential notifications, skip individual delivery events for speed
			$bgSupabase = new OutletAwareSupabaseHelper();
			
			// Send single trip start notification instead of per-parcel events
			sendTripStartNotifications($bgTripId, $bgDriverId, $bgCompanyId, $parcelIds, $bgSupabase);
			
			
			if (class_exists('PushNotificationService')) {
				$pushService = new PushNotificationService($bgSupabase);
				
				
				$tripDetails = $bgSupabase->get('trips', 'id=eq.' . urlencode($bgTripId), 
					'id,outlet_manager_id,origin_outlet_id,destination_outlet_id,company_id');
				if (!empty($tripDetails)) {
					$tripData = $tripDetails[0];
					$tripData['parcel_ids'] = $parcelIds;
					
					
					if (!empty($tripData['origin_outlet_id'])) {
						$originOutlet = $bgSupabase->get('outlets', 'id=eq.' . urlencode($tripData['origin_outlet_id']), 'outlet_name');
						$tripData['origin_outlet_name'] = !empty($originOutlet) ? $originOutlet[0]['outlet_name'] : 'Origin';
					}
					if (!empty($tripData['destination_outlet_id'])) {
						$destOutlet = $bgSupabase->get('outlets', 'id=eq.' . urlencode($tripData['destination_outlet_id']), 'outlet_name');
						$tripData['destination_outlet_name'] = !empty($destOutlet) ? $destOutlet[0]['outlet_name'] : 'Destination';
					}
					
					
					$pushResults = $pushService->sendTripStartedNotification($bgTripId, $tripData);
					error_log("✅ Trip started push notifications sent - Manager: " . 
						count($pushResults['manager_notifications'] ?? []) . ", Customers: " . 
						count($pushResults['customer_notifications'] ?? []));
				}
			}
		} catch (Exception $bgException) {
			error_log("Background notification error: " . $bgException->getMessage());
		}
		
		// Clean up any background output
		if (ob_get_level() > 0) {
			ob_end_clean();
		}
		
		exit;
	}
} catch (Exception $e) {
	error_log("Trip API Error: " . $e->getMessage());
	error_log("Stack trace: " . $e->getTraceAsString());
	
	if (ob_get_level() > 0) {
		ob_end_clean();
	}
	http_response_code(500);
	echo json_encode(['success' => false, 'error' => $e->getMessage()]);
	exit;
}
function sendTripStartNotifications($tripId, $driverId, $companyId, $parcelIds, $supabase) {
	$notificationsSent = 0;
	
	try {
		if (empty($parcelIds)) {
			return ['notifications_sent' => 0];
		}
		
		
		$driver = $supabase->get('drivers', 'id=eq.' . urlencode($driverId));
		$driverName = $driver[0]['driver_name'] ?? 'Driver';
		
		
		$parcelIdsStr = implode(',', array_map('urlencode', $parcelIds));
		$parcels = $supabase->get('parcels', 
			'id=in.(' . $parcelIdsStr . ')&select=id,track_number,sender_name,receiver_name,global_sender_id,global_receiver_id'
		);
		
		foreach ($parcels as $parcel) {
			$trackNumber = $parcel['track_number'];
			$notificationData = json_encode([
				'parcel_id' => $parcel['id'],
				'track_number' => $trackNumber,
				'trip_id' => $tripId,
				'driver_name' => $driverName
			]);
			
			
			if ($parcel['global_sender_id']) {
			try {
				$supabase->insert('notifications', [
					'company_id' => $companyId,
					'recipient_id' => $parcel['global_sender_id'],
					'sender_id' => $driverId,
					'title' => '📦 Your Parcel is On the Way',
					'message' => "Parcel #{$trackNumber} is now in transit with {$driverName}",
					'notification_type' => 'parcel_status_change',
					'parcel_id' => $parcel['id'],
					'priority' => 'high',
					'status' => 'unread',
					'data' => $notificationData,
					'created_at' => date('c')
				]);
				$notificationsSent++;
			} catch (Exception $e) {
				error_log("Error inserting sender notification for parcel {$parcel['id']}: " . $e->getMessage());
			}
			}
			
			
			if ($parcel['global_receiver_id']) {
			try {
				$supabase->insert('notifications', [
					'company_id' => $companyId,
					'recipient_id' => $parcel['global_receiver_id'],
					'sender_id' => $driverId,
					'title' => '📦 Package Coming Your Way',
					'message' => "Parcel #{$trackNumber} is on the way. Track your delivery!",
					'notification_type' => 'parcel_status_change',
					'parcel_id' => $parcel['id'],
					'priority' => 'high',
					'status' => 'unread',
					'data' => $notificationData,
					'created_at' => date('c')
				]);
				$notificationsSent++;
			} catch (Exception $e) {
				error_log("Error inserting receiver notification for parcel {$parcel['id']}: " . $e->getMessage());
			}
			}
		}
		
		
		try {
			$trip = $supabase->get('trips', 'id=eq.' . urlencode($tripId) . '&select=outlet_manager_id,origin_outlet_id,destination_outlet_id');
			if (!empty($trip) && !empty($trip[0]['outlet_manager_id'])) {
				$outletManagerId = $trip[0]['outlet_manager_id'];
				$parcelCount = count($parcelIds);
				
				
				$originOutlet = $supabase->get('outlets', 'id=eq.' . urlencode($trip[0]['origin_outlet_id']) . '&select=outlet_name');
				$destOutlet = $supabase->get('outlets', 'id=eq.' . urlencode($trip[0]['destination_outlet_id']) . '&select=outlet_name');
				
				$originName = !empty($originOutlet) ? $originOutlet[0]['outlet_name'] : 'Origin';
				$destName = !empty($destOutlet) ? $destOutlet[0]['outlet_name'] : 'Destination';
				
				$managerNotificationData = json_encode([
					'trip_id' => $tripId,
					'driver_name' => $driverName,
					'parcel_count' => $parcelCount,
					'origin' => $originName,
					'destination' => $destName
				]);
				
				
				$shortTripId = substr($tripId, 0, 8);
				
				$supabase->insert('notifications', [
					'company_id' => $companyId,
					'recipient_id' => $outletManagerId,
					'sender_id' => $driverId,
					'title' => '🚚 Trip Started',
					'message' => "Trip {$shortTripId} has started moving",
					'notification_type' => 'trip_started',
					'priority' => 'high',
					'status' => 'unread',
					'data' => $managerNotificationData
				]);
				$notificationsSent++;
			}
		} catch (Exception $e) {
			error_log("Error sending outlet manager notification: " . $e->getMessage());
		}
		
		return ['notifications_sent' => $notificationsSent];
	} catch (Exception $e) {
		error_log("Error sending trip notifications: " . $e->getMessage());
		return ['notifications_sent' => 0];
	}
}
