<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once '../../includes/OutletAwareSupabaseHelper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (ob_get_level()) {
    ob_clean();
}
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$driver_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'] ?? null;
if (!$company_id) {
    echo json_encode(['success' => false, 'error' => 'Company ID not found in session']);
    exit;
}
$input_raw = file_get_contents('php://input');
$input = json_decode($input_raw, true);
if (!$input && $input_raw) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input', 'received' => substr($input_raw, 0, 100)]);
    exit;
}
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No input received']);
    exit;
}
$stop_id = $input['stop_id'] ?? null;
$action = $input['action'] ?? null;
$timestamp = $input['timestamp'] ?? null;
if (!$stop_id || !$action || !$timestamp) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields: stop_id, action, timestamp', 'received' => $input]);
    exit;
}
if (!in_array($action, ['arrive', 'depart'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action. Must be "arrive" or "depart"']);
    exit;
}
error_log("API received - Stop ID: $stop_id, Action: $action, Timestamp: $timestamp, Driver: $driver_id, Company: $company_id");
if (strpos($timestamp, 'T') !== false) {
    
    $timestamp = date('Y-m-d H:i:s', strtotime($timestamp));
    error_log("Converted timestamp to: $timestamp");
}
try {
    $supabase = new OutletAwareSupabaseHelper();
    if (!$supabase) {
        throw new Exception('Database connection failed');
    }
try {
    
    $stopQuery = $supabase->get('trip_stops', 
        'id=eq.' . urlencode($stop_id) . 
        '&company_id=eq.' . urlencode($company_id) . 
        '&select=id,trip_id,outlet_id'
    );
    if (empty($stopQuery)) {
        echo json_encode(['success' => false, 'error' => 'Trip stop not found']);
        exit;
    }
    $stop = $stopQuery[0];
    
    
    $tripQuery = $supabase->get('trips', 
        'id=eq.' . urlencode($stop['trip_id']) . 
        '&select=driver_id,trip_status'
    );
    
    if (empty($tripQuery)) {
        echo json_encode(['success' => false, 'error' => 'Trip not found']);
        exit;
    }
    
    $trip = $tripQuery[0];
    
    if ($trip['driver_id'] !== $driver_id) {
        echo json_encode(['success' => false, 'error' => 'This trip is not assigned to you']);
        exit;
    }
    
    $validStatuses = ['scheduled', 'in_transit', 'at_outlet'];
    if (!in_array($trip['trip_status'], $validStatuses)) {
        echo json_encode(['success' => false, 'error' => 'Trip status does not allow updates. Current status: ' . $trip['trip_status']]);
        exit;
    }
    
    $updateData = [];
    $logMessage = '';
    if ($action === 'arrive') {
        
        $currentStop = $supabase->get('trip_stops', 
            'id=eq.' . urlencode($stop_id) . '&select=arrival_time'
        );
        
        if (!empty($currentStop[0]['arrival_time'])) {
            echo json_encode(['success' => false, 'error' => 'Already marked as arrived at this stop']);
            exit;
        }
        $updateData['arrival_time'] = $timestamp;
        $logMessage = "Driver arrived at stop";
        
    } else if ($action === 'depart') {
        
        $currentStop = $supabase->get('trip_stops', 
            'id=eq.' . urlencode($stop_id) . '&select=arrival_time,departure_time'
        );
        
        if (empty($currentStop[0]['arrival_time'])) {
            echo json_encode(['success' => false, 'error' => 'Must mark arrival before departure']);
            exit;
        }
        
        if (!empty($currentStop[0]['departure_time'])) {
            echo json_encode(['success' => false, 'error' => 'Already marked as departed from this stop']);
            exit;
        }
        $updateData['departure_time'] = $timestamp;
        $logMessage = "Driver departed from stop";
    }
    
    $result = $supabase->update('trip_stops', 
        $updateData,
        'id=eq.' . urlencode($stop_id)
    );
    
    error_log("Trip stop update attempt - Stop ID: $stop_id, Action: $action, Result: " . ($result ? 'success' : 'failed'));
    error_log("Update data: " . json_encode($updateData));
    
    if (!$result) {
        error_log("Failed to update trip stop - Stop ID: $stop_id, Update data: " . json_encode($updateData));
        echo json_encode(['success' => false, 'error' => 'Failed to update trip stop', 'debug' => ['stop_id' => $stop_id, 'action' => $action, 'update_data' => $updateData]]);
        exit;
    }
    
    // CRITICAL PATH COMPLETE - Now get outlet name quickly for response
    $outletInfo = $supabase->get('outlets', 
        'id=eq.' . urlencode($stop['outlet_id']) . '&select=outlet_name'
    );
    $outletName = !empty($outletInfo) ? $outletInfo[0]['outlet_name'] : 'Unknown Outlet';
    
    // Quick check if all stops might be completed (for depart action)
    $allCompleted = false;
    if ($action === 'depart') {
        $allStops = $supabase->get('trip_stops', 
            'trip_id=eq.' . urlencode($stop['trip_id']) . '&select=departure_time'
        );
        $allCompleted = true;
        foreach ($allStops as $tripStop) {
            if (empty($tripStop['departure_time'])) {
                $allCompleted = false;
                break;
            }
        }
    }
    
    // IMMEDIATE RESPONSE - User sees instant feedback
    $response = [
        'success' => true,
        'message' => ucfirst($action) . ' time recorded successfully',
        'stop_id' => $stop_id,
        'outlet_name' => $outletName,
        'timestamp' => $timestamp,
        'trip_completed' => $allCompleted
    ];
    
    // Store for background processing
    $bgDriverId = $driver_id;
    $bgCompanyId = $company_id;
    $bgTripId = $stop['trip_id'];
    $bgOutletId = $stop['outlet_id'];
    $bgStopId = $stop_id;
    $bgAction = $action;
    $bgTimestamp = $timestamp;
    $bgAllCompleted = $allCompleted;
    
    // Send response NOW
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode($response);
    
    // Flush to client
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) ob_end_flush();
        flush();
    }
    
    // ==========================================
    // BACKGROUND PROCESSING - After response sent
    // ==========================================
    ob_start();
    
    try {
        $bgSupabase = new OutletAwareSupabaseHelper();
        
        error_log("BG Start: Driver {$bgDriverId} - {$bgAction} at stop {$bgStopId}");
        
        // Background Task 1: Update parcel statuses
        try {
            updateParcelStatuses($bgSupabase, $bgTripId, $bgOutletId, $bgAction, $bgTimestamp);
        } catch (Exception $e) {
            error_log("BG: Failed to update parcels: " . $e->getMessage());
        }
        
        // Background Task 2: Send notifications (arrive only)
        if ($bgAction === 'arrive') {
            try {
                sendOutletArrivalNotifications($bgSupabase, $bgTripId, $bgOutletId, $bgDriverId, $bgCompanyId);
            } catch (Exception $e) {
                error_log("BG: Failed to send notifications: " . $e->getMessage());
            }
        }
        
        // Background Task 3: Update trip status
        try {
            if ($bgAction === 'arrive') {
                $bgSupabase->update('trips', 
                    ['trip_status' => 'at_outlet'],
                    'id=eq.' . urlencode($bgTripId)
                );
            } elseif ($bgAllCompleted) {
                $bgSupabase->update('trips', 
                    ['trip_status' => 'completed', 'arrival_time' => $bgTimestamp],
                    'id=eq.' . urlencode($bgTripId)
                );
                // Also update driver status
                $bgSupabase->update('drivers', 
                    ['status' => 'available', 'current_trip_id' => null],
                    'id=eq.' . urlencode($bgDriverId)
                );
            } else {
                $bgSupabase->update('trips', 
                    ['trip_status' => 'in_transit'],
                    'id=eq.' . urlencode($bgTripId)
                );
            }
        } catch (Exception $e) {
            error_log("BG: Failed to update trip status: " . $e->getMessage());
        }
        
        error_log("BG Complete: Trip status updated for {$bgTripId}");
        
    } catch (Exception $bgException) {
        error_log("Background update_trip_stop error: " . $bgException->getMessage());
    }
    
    if (ob_get_level() > 0) ob_end_clean();
    exit;
} catch (Exception $e) {
    error_log("Update trip stop error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => 'Server error occurred', 'debug' => $e->getMessage()]);
}
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
}
function updateParcelStatuses($supabase, $trip_id, $outlet_id, $action, $timestamp) {
    try {
        
        $parcels = $supabase->get('parcel_list', 
            'trip_id=eq.' . urlencode($trip_id) . 
            '&outlet_id=eq.' . urlencode($outlet_id)
        );
        
        foreach ($parcels as $parcel) {
            $parcel_id = $parcel['parcel_id'];
            $parcel_list_id = $parcel['id'];
            
            if ($action === 'arrive') {
                
                $new_status = 'in_transit';
                
                
                $supabase->update('parcel_list', 
                    [
                        'status' => $new_status,
                        'updated_at' => $timestamp
                    ],
                    'id=eq.' . urlencode($parcel_list_id)
                );
                
                
                if ($parcel_id) {
                    $supabase->update('parcels', 
                        [
                            'status' => $new_status,
                            'updated_at' => $timestamp
                        ],
                        'id=eq.' . urlencode($parcel_id)
                    );
                }
                
            } elseif ($action === 'depart') {
                
                $new_status = 'completed';
                $delivery_date = date('Y-m-d', strtotime($timestamp));
                
                
                $supabase->update('parcel_list', 
                    [
                        'status' => $new_status,
                        'updated_at' => $timestamp
                    ],
                    'id=eq.' . urlencode($parcel_list_id)
                );
                
                
                if ($parcel_id) {
                    $supabase->update('parcels', 
                        [
                            'status' => $new_status,
                            'delivery_date' => $delivery_date,
                            'updated_at' => $timestamp
                        ],
                        'id=eq.' . urlencode($parcel_id)
                    );
                }
            }
        }
        
        error_log("Updated " . count($parcels) . " parcels to status: " . ($action === 'arrive' ? 'in_transit' : 'completed'));
        
    } catch (Exception $e) {
        error_log("Error updating parcel statuses: " . $e->getMessage());
    }
}
function sendOutletArrivalNotifications($supabase, $trip_id, $outlet_id, $driver_id, $company_id) {
    try {
        
        $parcelList = $supabase->get('parcel_list', 
            'trip_id=eq.' . urlencode($trip_id) . '&outlet_id=eq.' . urlencode($outlet_id) . '&select=parcel_id'
        );
        
        if (empty($parcelList)) {
            error_log("No parcels found for outlet arrival notification - Trip: {$trip_id}, Outlet: {$outlet_id}");
            return 0;
        }
        
        $parcelIds = array_column($parcelList, 'parcel_id');
        $parcelIdsStr = implode(',', array_map('urlencode', $parcelIds));
        
        
        $parcels = $supabase->get('parcels', 
            'id=in.(' . $parcelIdsStr . ')&select=id,track_number,receiver_name,receiver_phone,global_receiver_id'
        );
        
        
        $outlet = $supabase->get('outlets', 'id=eq.' . urlencode($outlet_id) . '&select=outlet_name');
        $outletName = $outlet[0]['outlet_name'] ?? 'outlet';
        
        
        $driver = $supabase->get('drivers', 'id=eq.' . urlencode($driver_id) . '&select=driver_name');
        $driverName = $driver[0]['driver_name'] ?? 'Driver';
        
        $notificationsSent = 0;
        $recipients = [];
        
        
        foreach ($parcels as $parcel) {
            
            if (empty($parcel['receiver_phone']) && empty($parcel['global_receiver_id'])) {
                continue;
            }
            
            $trackNumber = $parcel['track_number'];
            
            
            $notificationData = [
                'parcel_id' => $parcel['id'],
                'track_number' => $trackNumber,
                'trip_id' => $trip_id,
                'outlet_id' => $outlet_id,
                'outlet_name' => $outletName,
                'driver_name' => $driverName,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            
            $receiverNotification = [
                'company_id' => $company_id,
                'recipient_id' => $parcel['global_receiver_id'] ?? null,
                'sender_id' => $driver_id,
                'title' => '📍 Your Parcel Has Arrived at Outlet',
                'message' => "Good news! Your parcel (#{$trackNumber}) has arrived at {$outletName}. Ready for pickup or final delivery.",
                'notification_type' => 'parcel_arrived_at_outlet',
                'parcel_id' => $parcel['id'],
                'priority' => 'high',
                'status' => 'unread',
                'data' => json_encode($notificationData),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            
            $supabase->insert('notifications', $receiverNotification);
            
            
            if ($parcel['global_receiver_id']) {
                sendPushNotificationToReceiver(
                    $parcel['global_receiver_id'],
                    'Your Parcel Has Arrived',
                    "Parcel #{$trackNumber} has arrived at {$outletName}",
                    $notificationData,
                    $supabase
                );
            }
            
            $notificationsSent++;
            $recipients[] = [
                'name' => $parcel['receiver_name'],
                'track_number' => $trackNumber,
                'outlet' => $outletName
            ];
        }
        
        
        if ($notificationsSent > 0) {
            $supabase->insert('notification_logs', [
                'user_id' => $driver_id,
                'title' => 'Outlet Arrival Notifications',
                'body' => "Sent {$notificationsSent} notifications for outlet {$outletName}",
                'data' => json_encode([
                    'trip_id' => $trip_id,
                    'outlet_id' => $outlet_id,
                    'outlet_name' => $outletName,
                    'parcels_count' => count($parcels),
                    'notifications_sent' => $notificationsSent,
                    'recipients' => $recipients
                ]),
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            error_log("Sent {$notificationsSent} outlet arrival notifications for {$outletName} - Trip: {$trip_id}");
        }
        
        return $notificationsSent;
        
    } catch (Exception $e) {
        error_log("Error sending outlet arrival notifications: " . $e->getMessage());
        return 0;
    }
}
function sendPushNotificationToReceiver($userId, $title, $body, $data, $supabase) {
    try {
        
        $subscriptions = $supabase->get('push_subscriptions', 
            'user_id=eq.' . urlencode($userId) . '&is_active=eq.true'
        );
        
        if (empty($subscriptions)) {
            return false;
        }
        
        
        
        foreach ($subscriptions as $subscription) {
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'icon' => '/img/logo.png',
                'badge' => '/img/badge.png',
                'data' => $data,
                'timestamp' => time()
            ]);
            
            
            $supabase->insert('notification_logs', [
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error sending push notification to receiver: " . $e->getMessage());
        return false;
    }
}
?>
        
    } catch (Exception $e) {
        error_log("Error updating parcel statuses: " . $e->getMessage());
    }
}
?>
