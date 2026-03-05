<?php

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/OutletAwareSupabaseHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/session_manager.php';
    initSession();
}

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $managerId = $_SESSION['user_id'];
    $managerOutlet = $_SESSION['outlet_id'] ?? null;
    $companyId = $_SESSION['company_id'] ?? null;

    
    $userRole = $_SESSION['role'] ?? null;
    if (!in_array($userRole, ['outlet_manager','super_admin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized role']);
        exit;
    }

    if (!$companyId) {
        throw new Exception('Company context missing');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    $stop_id = $input['stop_id'] ?? null;
    $outlet_id = $input['outlet_id'] ?? null; 
    $trip_id = $input['trip_id'] ?? null;
    $action = $input['action'] ?? null; 
    $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');

    error_log("manager_update_trip_stop.php DEBUG: stop_id=" . ($stop_id ?? 'NULL') . ", outlet_id=" . ($outlet_id ?? 'NULL') . ", trip_id=" . ($trip_id ?? 'NULL') . ", action=" . ($action ?? 'NULL'));

    if ((!$stop_id && !$outlet_id) || !$action || !in_array($action, ['arrive','depart'])) {
        echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters (stop_id or outlet_id, action)']);
        exit;
    }

   
    if ($stop_id) {
 
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $stop_id)) {
            echo json_encode(['success' => false, 'error' => 'Invalid stop ID format']);
            exit;
        }
    } else {
        
        if (!$outlet_id || !$trip_id) {
            echo json_encode(['success' => false, 'error' => 'outlet_id and trip_id required for origin/destination tracking']);
            exit;
        }
    }

    
    if (strpos($timestamp, 'T') !== false) {
        $timestamp = date('Y-m-d H:i:s', strtotime($timestamp));
    }

    $supabase = new OutletAwareSupabaseHelper();

   
    if ($stop_id) {
       
        $stopQuery = $supabase->get('trip_stops', 'id=eq.' . urlencode($stop_id) . '&select=id,trip_id,outlet_id,stop_order,arrival_time,departure_time');
        if (empty($stopQuery)) {
            echo json_encode(['success' => false, 'error' => 'Trip stop not found']);
            exit;
        }
        $stop = $stopQuery[0];
        $outlet_id = $stop['outlet_id'];
        $trip_id = $stop['trip_id'];
        $mode = 'stop';
    } else {
      
        $tripQuery = $supabase->get('trips', 'id=eq.' . urlencode($trip_id) . '&select=id,origin_outlet_id,destination_outlet_id,trip_status,driver_id,arrival_time,departure_time');
        if (empty($tripQuery)) {
            echo json_encode(['success' => false, 'error' => 'Trip not found']);
            exit;
        }
        $trip = $tripQuery[0];
        
        error_log("Outlet mode - trip data: " . json_encode($trip));
        
        // Verifying outlet is origin or destination
        if ($outlet_id !== $trip['origin_outlet_id'] && $outlet_id !== $trip['destination_outlet_id']) {
            echo json_encode(['success' => false, 'error' => 'Outlet is not origin or destination of this trip']);
            exit;
        }
        $mode = 'outlet';
    }

    

    $outletInfo = $supabase->get('outlets', 'id=eq.' . urlencode($outlet_id) . '&select=id,manager_id');
    if (!empty($outletInfo) && isset($outletInfo[0]['manager_id']) && $outletInfo[0]['manager_id'] == $managerId) {
        $allowed = true;
    }

    
    if (!$allowed) {
        $profile = $supabase->get('profiles', 'id=eq.' . urlencode($managerId) . '&select=id,role,outlet_id');
        if (!empty($profile) && isset($profile[0]['role']) && $profile[0]['role'] === 'outlet_manager' && isset($profile[0]['outlet_id']) && $profile[0]['outlet_id'] == $outlet_id) {
            $allowed = true;
        }
    }

    
    if (!$allowed) {
        if (!isset($trip)) {
            $tripCheck = $supabase->get('trips', 'id=eq.' . urlencode($trip_id) . '&select=id,outlet_manager_id');
            if (!empty($tripCheck) && isset($tripCheck[0]['outlet_manager_id']) && $tripCheck[0]['outlet_manager_id'] == $managerId) {
                $allowed = true;
            }
        } else {
            if (isset($trip['outlet_manager_id']) && $trip['outlet_manager_id'] == $managerId) {
                $allowed = true;
            }
        }
    }

    
    if (!$allowed && $userRole === 'super_admin') {
        $allowed = true;
    }

    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not authorized to update this stop']);
        exit;
    }

  
    if (!isset($trip)) {
        $tripQuery = $supabase->get('trips', 'id=eq.' . urlencode($trip_id) . '&select=id,trip_status,driver_id');
        if (empty($tripQuery)) {
            echo json_encode(['success' => false, 'error' => 'Trip not found']);
            exit;
        }
        $trip = $tripQuery[0];
    }

    if ($mode === 'stop') {
   
        $updateData = [];
        if ($action === 'arrive') {
            if (!empty($stop['arrival_time'])) {
                echo json_encode(['success' => false, 'error' => 'Already marked as arrived']);
                exit;
            }
            $updateData['arrival_time'] = $timestamp;
        } elseif ($action === 'depart') {
            if (empty($stop['arrival_time'])) {
                echo json_encode(['success' => false, 'error' => 'Cannot depart before arrival']);
                exit;
            }
            if (!empty($stop['departure_time'])) {
                echo json_encode(['success' => false, 'error' => 'Already marked as departed']);
                exit;
            }
            $updateData['departure_time'] = $timestamp;
        }

        $updateResult = $supabase->update('trip_stops', $updateData, 'id=eq.' . urlencode($stop_id));
        
        if (empty($updateResult)) {
            echo json_encode(['success' => false, 'error' => 'Failed to update trip stop']);
            exit;
        }

        $allStopsDone = false;
        if ($action === 'depart') {
            try {
                $allStops = $supabase->get('trip_stops',
                    'trip_id=eq.' . urlencode($trip_id) . '&select=id,departure_time'
                );
                if (!empty($allStops)) {
                    $allStopsDone = !array_filter($allStops, fn($s) => empty($s['departure_time']));
                    if ($allStopsDone) {
                        $supabase->update('trips', [
                            'driver_completed'    => true,
                            'driver_completed_at' => $timestamp,
                        ], 'id=eq.' . urlencode($trip_id));
                    }
                }
            } catch (\Exception $e) {
                error_log('Auto-complete check failed: ' . $e->getMessage());
            }
        }


        $logData = [
            'trip_id' => $trip['id'],
            'stop_id' => $stop_id,
            'action' => $action,
            'timestamp' => $timestamp,
            'updated_by' => $_SESSION['user_id'] ?? 'system'
        ];
        $responseExtra = $allStopsDone ? ['all_stops_completed' => true] : [];
    } else {
        
        $isOrigin = ($outlet_id === $trip['origin_outlet_id']);
        $isDestination = ($outlet_id === $trip['destination_outlet_id']);
        
        error_log("Direct outlet mode: isOrigin=" . ($isOrigin ? 'true' : 'false') . ", isDestination=" . ($isDestination ? 'true' : 'false') . ", action=" . $action);
        
        $tripUpdateData = [];
        if ($action === 'arrive') {
            if ($isDestination) {
               
                error_log("Arriving at destination");
                if (!empty($trip['arrival_time'])) {
                    echo json_encode(['success' => false, 'error' => 'Already marked as arrived at destination']);
                    exit;
                }
                $tripUpdateData['arrival_time'] = $timestamp;
                $tripUpdateData['trip_status'] = 'completed';
            } elseif ($isOrigin) {
               
                error_log("Arriving at origin");
                if (!empty($trip['arrival_time'])) {
                    echo json_encode(['success' => false, 'error' => 'Already marked as arrived']);
                    exit;
                }
                $tripUpdateData['arrival_time'] = $timestamp;
                $tripUpdateData['trip_status'] = 'completed';
            } else {
                echo json_encode(['success' => false, 'error' => 'Outlet is not origin or destination']);
                exit;
            }
        } elseif ($action === 'depart') {
            if ($isOrigin) {
            
                error_log("Departing from origin");
                if (!empty($trip['departure_time'])) {
                    echo json_encode(['success' => false, 'error' => 'Already marked as departed from origin']);
                    exit;
                }
                $tripUpdateData['departure_time'] = $timestamp;
                $tripUpdateData['trip_status'] = 'in_transit';
            } elseif ($isDestination) {
                error_log("Departing from destination");
                if (empty($trip['arrival_time'])) {
                    echo json_encode(['success' => false, 'error' => 'Must arrive before departing']);
                    exit;
                }
                
                $tripUpdateData['trip_status'] = 'in_transit';
            } else {
                echo json_encode(['success' => false, 'error' => 'Outlet is not origin or destination']);
                exit;
            }
        }
        
        error_log("Update data for trip: " . json_encode($tripUpdateData));
        
        $updateResult = $supabase->update('trips', $tripUpdateData, 'id=eq.' . urlencode($trip_id));
        
        error_log("Update result: " . json_encode($updateResult));
        
        if (empty($updateResult)) {
            echo json_encode(['success' => false, 'error' => 'Failed to update trip']);
            exit;
        }

       
        $logData = [
            'trip_id' => $trip_id,
            'outlet_id' => $outlet_id,
            'action' => $action,
            'timestamp' => $timestamp,
            'updated_by' => $_SESSION['user_id'] ?? 'system'
        ];
    }

    try {
        $supabase->insert('trip_logs', $logData);
    } catch (Exception $e) {
     
        error_log("Trip log error: " . $e->getMessage());
    }

    $response = array_merge([
        'success'   => true,
        'message'   => ucfirst($action) . ' time updated successfully',
        'timestamp' => $timestamp,
        'mode'      => $mode
    ], $responseExtra ?? []);

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

