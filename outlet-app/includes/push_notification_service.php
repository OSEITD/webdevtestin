<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/url_helper.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService {
    private $supabase;
    private $webPush;
    
    public function __construct($supabase) {
        $this->supabase = $supabase;
        
        require_once __DIR__ . '/env.php';
        
        $vapidPublic = getenv('VAPID_PUBLIC_KEY') ?: EnvLoader::get('VAPID_PUBLIC_KEY');
        $vapidPrivate = getenv('VAPID_PRIVATE_KEY') ?: EnvLoader::get('VAPID_PRIVATE_KEY');
        $vapidSubject = getenv('VAPID_SUBJECT') ?: EnvLoader::get('VAPID_SUBJECT', 'mailto:admin@yourcompany.com');
        
        error_log("VAPID Configuration Check:");
        error_log("- VAPID_PUBLIC_KEY: " . ($vapidPublic ? "SET (" . strlen($vapidPublic) . " chars)" : "NOT SET"));
        error_log("- VAPID_PRIVATE_KEY: " . ($vapidPrivate ? "SET (" . strlen($vapidPrivate) . " chars)" : "NOT SET"));
        error_log("- VAPID_SUBJECT: " . $vapidSubject);
        
        if (!$vapidPublic || !$vapidPrivate) {
            error_log("WARNING: VAPID keys not configured - push notifications will be disabled");
            $this->webPush = null;
            return;
        }
        
        $auth = array(
            'VAPID' => array(
                'subject' => $vapidSubject,
                'publicKey' => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ),
        );
        
        try {
            $this->webPush = new WebPush($auth);
        } catch (Exception $e) {
            error_log("Failed to initialize WebPush: " . $e->getMessage());
            $this->webPush = null;
        }
    }
    
    public function sendToDriver($driverId, $title, $body, $data = array()) {
        if ($this->webPush === null) {
            error_log("WebPush not initialized - skipping push notification for driver: $driverId");
            return array('success' => false, 'message' => 'Push notifications disabled');
        }
        
        try {
            error_log("=== SENDING TO DRIVER: $driverId ===");
            error_log("Title: $title");
            error_log("Body: $body");
            
            $subscriptions = $this->getDriverSubscriptions($driverId);
            
            if (empty($subscriptions)) {
                error_log("❌ No push subscriptions found for driver: $driverId");
                
                
                $this->sendEmailFallback($driverId, $title, $body, $data);
                
                return array('success' => false, 'message' => 'No subscriptions found');
            }
            
            error_log("✅ Found " . count($subscriptions) . " active subscription(s) for driver");
            
            $successCount = 0;
            $results = array();
            
            foreach ($subscriptions as $sub) {
                error_log("Attempting to send to subscription: " . $sub['id'] . " (endpoint: " . substr($sub['endpoint'], 0, 50) . "...)");
                
                $subscription = Subscription::create(array(
                    'endpoint' => $sub['endpoint'],
                    'keys' => array(
                        'p256dh' => $sub['p256dh_key'],
                        'auth' => $sub['auth_key']
                    )
                ));
                
                $payload = json_encode(array(
                    'title' => $title,
                    'body' => $body,
                    'icon' => '/outlet-app/icons/icon-192x192.png',
                    'badge' => '/outlet-app/icons/icon-72x72.png',
                    'data' => $data,
                    'actions' => isset($data['actions']) ? $data['actions'] : array(
                        array('action' => 'view', 'title' => 'View Trip'),
                        array('action' => 'dismiss', 'title' => 'Dismiss')
                    ),
                    'tag' => 'trip-assignment',
                    'requireInteraction' => true,
                    'vibrate' => array(200, 100, 200)
                ));
                
                error_log("Payload size: " . strlen($payload) . " bytes");
                error_log("Payload data.url: " . ($data['url'] ?? 'NOT SET'));
                error_log("Payload data.type: " . ($data['type'] ?? 'NOT SET'));
                
                $result = $this->webPush->sendOneNotification($subscription, $payload);
                
                if ($result->isSuccess()) {
                    $successCount++;
                    error_log("✅ Driver notification sent successfully to subscription: " . $sub['id']);
                    $results[] = array('success' => true, 'endpoint' => $sub['endpoint'], 'subscription_id' => $sub['id']);
                } else {
                    $reason = $result->getReason();
                    error_log("❌ Driver notification FAILED for subscription " . $sub['id'] . ": " . $reason);
                    $results[] = array('success' => false, 'error' => $reason, 'subscription_id' => $sub['id']);
                    
                    
                    if ($result->isSubscriptionExpired() || 
                        strpos($reason, '403') !== false || 
                        strpos($reason, 'VAPID credentials') !== false ||
                        strpos($reason, 'Forbidden') !== false) {
                        error_log("⚠️ Subscription invalid/expired, marking as inactive: " . $sub['id']);
                        $this->markSubscriptionInactive($sub['id']);
                    }
                }
            }
            
            $this->logNotification($driverId, $title, $body, $data, 'sent');
            
            error_log("=== DRIVER NOTIFICATION RESULT: $successCount/" . count($subscriptions) . " sent successfully ===");
            
            return array(
                'success' => $successCount > 0,
                'sent_count' => $successCount,
                'total_subscriptions' => count($subscriptions),
                'results' => $results
            );
            
        } catch (Exception $e) {
            error_log("❌ Push notification error for driver: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    private function getDriverSubscriptions($driverId) {
        try {
            $query = "user_id=eq.$driverId&is_active=eq.true&select=*";
            $subscriptions = $this->supabase->get('push_subscriptions', $query);
            return $subscriptions ?: array();
        } catch (Exception $e) {
            error_log("Error fetching subscriptions: " . $e->getMessage());
            return array();
        }
    }
    
    private function markSubscriptionInactive($subscriptionId) {
        try {
            // Use SupabaseHelper to maintain proper session context
            require_once __DIR__ . '/supabase-helper.php';
            $supabaseHelper = new SupabaseHelper();
            
            $result = $supabaseHelper->patch(
                'push_subscriptions',
                ['is_active' => false],
                "id=eq.$subscriptionId"
            );
            
            error_log("✅ Marked subscription $subscriptionId as inactive");
            return true;
        } catch (Exception $e) {
            error_log("❌ Error marking subscription inactive: " . $e->getMessage());
            return false;
        }
    }
    
    private function logNotification($userId, $title, $body, $data, $status) {
        try {
            $payload = array(
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
                'status' => $status,
                'sent_at' => date('c'),
                'created_at' => date('c')
            );

            
            if (method_exists($this->supabase, 'insert')) {
                $this->supabase->insert('notification_logs', $payload);
            } elseif (method_exists($this->supabase, 'post')) {
                $this->supabase->post('notification_logs', $payload);
            } else {
                
                if (method_exists($this->supabase, 'makeRequest')) {
                    $this->supabase->makeRequest('POST', $this->supabase->baseUrl . '/notification_logs', $payload);
                } else {
                    error_log('No method available to write notification_logs to Supabase');
                }
            }
        } catch (Exception $e) {
            error_log("Error logging notification: " . $e->getMessage());
        }
    }
    
    public function sendTripAssignmentNotification($driverId, $tripData) {
        $title = '🚗 New Trip Assigned';
        $body = sprintf(
            'Trip from %s to %s - Departure: %s',
            $tripData['origin_outlet_name'] ?? 'Unknown',
            $tripData['destination_outlet_name'] ?? 'Unknown',
            date('g:i A', strtotime($tripData['departure_time'] ?? 'now'))
        );
        
        $data = array(
            'type' => 'trip_assignment',
            'trip_id' => $tripData['trip_id'],
            'url' => getDriverUrl('dashboard.php?trip_id=' . $tripData['trip_id']),
            'timestamp' => time(),
            'actions' => array(
                array('action' => 'view', 'title' => 'View Trip'),
                array('action' => 'dismiss', 'title' => 'Dismiss')
            )
        );
        
        return $this->sendToDriver($driverId, $title, $body, $data);
    }
    
    
    public function sendTripAssignmentToManager($managerId, $tripData) {
        $title = '📋 New Trip Created';
        $body = sprintf(
            'Trip from %s to %s needs your approval - Departure: %s',
            $tripData['origin_outlet_name'] ?? 'Unknown',
            $tripData['destination_outlet_name'] ?? 'Unknown',
            date('g:i A', strtotime($tripData['departure_time'] ?? 'now'))
        );
        
        $data = array(
            'type' => 'trip_assignment_manager',
            'trip_id' => $tripData['trip_id'],
            'url' => getPageUrl('manager_trips.php'),
            'timestamp' => time(),
            'actions' => array(
                array('action' => 'accept_trip', 'title' => '✅ Accept Trip'),
                array('action' => 'view_trip', 'title' => '👀 View Details'),
                array('action' => 'dismiss', 'title' => '❌ Later')
            )
        );
        
        return $this->sendToManager($managerId, $title, $body, $data);
    }
    
    public function sendTripStartedNotification($tripId, $tripData) {
        $results = array(
            'manager_notifications' => array(),
            'customer_notifications' => array()
        );
        
        error_log("📤 Sending trip started notifications for trip: $tripId");
        
        
        if (isset($tripData['outlet_manager_id']) && !empty($tripData['outlet_manager_id'])) {
            $shortTripId = substr($tripId, 0, 8);
            $managerTitle = '🚚 Trip Started';
            $managerBody = sprintf(
                'Trip %s has started moving from %s to %s',
                $shortTripId,
                $tripData['origin_outlet_name'] ?? 'origin',
                $tripData['destination_outlet_name'] ?? 'destination'
            );
            
            $managerData = array(
                'type' => 'trip_started',
                'trip_id' => $tripId,
                'url' => getPageUrl('trips_manager.php?trip_id=' . $tripId),
                'timestamp' => time()
            );
            
            error_log("📨 Sending notification to manager: {$tripData['outlet_manager_id']}");
            $managerResult = $this->sendToManager($tripData['outlet_manager_id'], $managerTitle, $managerBody, $managerData);
            $results['manager_notifications'][] = $managerResult;
            
            if (isset($managerResult['success']) && $managerResult['success']) {
                error_log("✅ Manager notification sent successfully");
            } else {
                error_log("❌ Manager notification failed: " . json_encode($managerResult));
            }
        } else {
            error_log("⚠️ No outlet_manager_id found in trip data");
        }
        
        
        if (isset($tripData['parcel_ids']) && !empty($tripData['parcel_ids'])) {
            error_log("📦 Notifying customers for " . count($tripData['parcel_ids']) . " parcels");
            $results['customer_notifications'] = $this->notifyCustomersAboutTripStart($tripId, $tripData['parcel_ids']);
        }
        
        return $results;
    }
    
    public function sendTripArrivedAtOutletNotification($tripId, $outletId, $tripData) {
        $results = array(
            'manager_notifications' => array(),
            'customer_notifications' => array()
        );
        
        error_log("📍 Sending arrival notifications for trip: $tripId at outlet: $outletId");
        
        
        $outletName = $this->getOutletName($outletId);
        
        
        if (isset($tripData['outlet_manager_id']) && !empty($tripData['outlet_manager_id'])) {
            $shortTripId = substr($tripId, 0, 8);
            $managerTitle = '📍 Trip Arrived';
            $managerBody = sprintf(
                'Trip %s has reached %s',
                $shortTripId,
                $outletName
            );
            
            $managerData = array(
                'type' => 'trip_arrived',
                'trip_id' => $tripId,
                'outlet_id' => $outletId,
                'url' => getPageUrl('trips_manager.php?trip_id=' . $tripId),
                'timestamp' => time()
            );
            
            error_log("📨 Sending arrival notification to manager: {$tripData['outlet_manager_id']}");
            $managerResult = $this->sendToManager($tripData['outlet_manager_id'], $managerTitle, $managerBody, $managerData);
            $results['manager_notifications'][] = $managerResult;
            
            if (isset($managerResult['success']) && $managerResult['success']) {
                error_log("✅ Manager arrival notification sent successfully");
            } else {
                error_log("❌ Manager arrival notification failed: " . json_encode($managerResult));
            }
        } else {
            error_log("⚠️ No outlet_manager_id found in trip data for arrival notification");
        }
        
        
        error_log("📦 Checking for customer notifications at outlet: $outletName");
        $customerResults = $this->notifyCustomersAboutParcelArrival($tripId, $outletId);
        $results['customer_notifications'] = $customerResults;
        error_log("✅ Sent " . count($customerResults) . " customer arrival notifications");
        
        return $results;
    }
    
    public function sendTripCompletedNotification($tripId, $tripData) {
        $results = array(
            'manager_notifications' => array()
        );
        
        
        if (isset($tripData['outlet_manager_id'])) {
            $shortTripId = substr($tripId, 0, 8);
            $managerTitle = '✅ Trip Completed';
            $managerBody = sprintf(
                'Trip %s is completed',
                $shortTripId
            );
            
            $managerData = array(
                'type' => 'trip_completed',
                'trip_id' => $tripId,
                'url' => getPageUrl('trips_manager.php?trip_id=' . $tripId),
                'timestamp' => time()
            );
            
            $results['manager_notifications'][] = $this->sendToManager($tripData['outlet_manager_id'], $managerTitle, $managerBody, $managerData);
        }
        
        return $results;
    }
    
    private function notifyCustomersAboutTripStart($tripId, $parcelIds) {
        $notifications = array();
        
        try {
            
            $parcelIdsStr = implode(',', array_map(function($id) { return addslashes($id); }, $parcelIds));
            $parcels = $this->supabase->get('parcels', "id=in.($parcelIdsStr)", 'id,track_number,sender_phone,receiver_phone,origin_outlet_id,destination_outlet_id,global_sender_id,global_receiver_id');
            
            foreach ($parcels as $parcel) {
                $originName = $this->getOutletName($parcel['origin_outlet_id']);
                $destName = $this->getOutletName($parcel['destination_outlet_id']);
                
                $message = sprintf(
                    '📦 Your parcel %s is now in transit from %s to %s',
                    $parcel['track_number'],
                    $originName,
                    $destName
                );
                
                error_log("📨 Sending trip started notification for parcel {$parcel['track_number']} to customer");
                
                $result = $this->sendToCustomer($parcel, '🚚 Parcel In Transit', $message, [
                    'type' => 'trip_started',
                    'tracking_number' => $parcel['track_number'],
                    'url' => getCustomerUrl('customer-app/track_parcel.php?track=' . $parcel['track_number'])
                ]);
                
                $notifications[] = $result;
                
                if (isset($result['success']) && $result['success']) {
                    error_log("✅ Customer notification sent for parcel {$parcel['track_number']}");
                } else {
                    error_log("❌ Customer notification failed for parcel {$parcel['track_number']}: " . 
                        json_encode($result));
                }
            }
        } catch (Exception $e) {
            error_log("Error notifying customers about trip start: " . $e->getMessage());
        }
        
        return $notifications;
    }
    
    private function notifyCustomersAboutParcelArrival($tripId, $outletId) {
        $notifications = array();
        
        try {
            error_log("🔍 Looking for parcels at outlet $outletId for trip $tripId");
            
            
            $parcelListQuery = "trip_id=eq.$tripId&outlet_id=eq.$outletId&status=in.(in_transit,assigned,at_outlet)";
            $parcelAssignments = $this->supabase->get('parcel_list', $parcelListQuery, 'parcel_id');
            
            if (empty($parcelAssignments)) {
                error_log("⚠️ No parcels found for trip $tripId at outlet $outletId");
                return $notifications;
            }
            
            error_log("📦 Found " . count($parcelAssignments) . " parcel assignments");
            
            
            $parcelIds = array_column($parcelAssignments, 'parcel_id');
            $parcelIdsStr = implode(',', array_map(function($id) { return addslashes($id); }, $parcelIds));
            
            
            $parcels = $this->supabase->get('parcels', "id=in.($parcelIdsStr)", 
                'id,track_number,destination_outlet_id,global_sender_id,global_receiver_id');
            
            $outletName = $this->getOutletName($outletId);
            
            foreach ($parcels as $parcel) {
                error_log("📋 Processing parcel {$parcel['track_number']} - Destination: {$parcel['destination_outlet_id']}, Current: $outletId");
                
                
                if ($parcel['destination_outlet_id'] === $outletId) {
                    $message = sprintf(
                        '✅ Your parcel %s has arrived at %s. Please collect it during business hours.',
                        $parcel['track_number'],
                        $outletName
                    );
                    
                    error_log("📨 Sending arrival notification for parcel {$parcel['track_number']} to customer");
                    
                    
                    $result = $this->sendToCustomer($parcel, '📍 Parcel Arrived', $message, [
                        'type' => 'parcel_arrived',
                        'tracking_number' => $parcel['track_number'],
                        'outlet_name' => $outletName,
                        'url' => getCustomerUrl('track_parcel.php?track=' . $parcel['track_number'])
                    ]);
                    
                    $notifications[] = $result;
                    
                    if (isset($result['success']) && $result['success']) {
                        error_log("✅ Customer notification sent for parcel {$parcel['track_number']}");
                    } else {
                        error_log("❌ Customer notification failed for parcel {$parcel['track_number']}: " . 
                            json_encode($result));
                    }
                } else {
                    error_log("⏭️ Skipping notification - parcel {$parcel['track_number']} destination is different outlet");
                }
            }
            
            error_log("✅ Completed sending " . count($notifications) . " parcel arrival notifications");
            
        } catch (Exception $e) {
            error_log("❌ Error notifying customers about parcel arrival: " . $e->getMessage());
        }
        
        return $notifications;
    }
    
    private function sendToManager($managerId, $title, $body, $data = array()) {
        try {
            $subscriptions = $this->getManagerSubscriptions($managerId);
            
            if (empty($subscriptions)) {
                error_log("No push subscriptions found for manager: $managerId");
                return array('success' => false, 'message' => 'No subscriptions found');
            }
            
            $successCount = 0;
            $results = array();
            
            foreach ($subscriptions as $sub) {
                $subscription = Subscription::create(array(
                    'endpoint' => $sub['endpoint'],
                    'keys' => array(
                        'p256dh' => $sub['p256dh_key'],
                        'auth' => $sub['auth_key']
                    )
                ));
                
                $payload = json_encode(array(
                    'title' => $title,
                    'body' => $body,
                    'icon' => '/outlet-app/icons/icon-192x192.png',
                    'badge' => '/outlet-app/icons/icon-72x72.png',
                    'data' => $data,
                    'actions' => array(
                        array('action' => 'view', 'title' => 'View Details'),
                        array('action' => 'dismiss', 'title' => 'Dismiss')
                    ),
                    'tag' => 'trip-update',
                    'requireInteraction' => false,
                    'vibrate' => array(200, 100, 200)
                ));
                
                $result = $this->webPush->sendOneNotification($subscription, $payload);
                
                if ($result->isSuccess()) {
                    $successCount++;
                    $results[] = array('success' => true, 'endpoint' => $sub['endpoint']);
                    error_log("✅ Push notification sent successfully to manager subscription: " . $sub['id']);
                } else {
                    $errorReason = $result->getReason();
                    $results[] = array('success' => false, 'error' => $errorReason);
                    error_log("❌ Push notification failed for manager subscription {$sub['id']}: $errorReason");
                    
                    if ($result->isSubscriptionExpired()) {
                        $this->markSubscriptionInactive($sub['id']);
                    }
                }
            }
            
            $this->logNotification($managerId, $title, $body, $data, 'sent');
            
            return array(
                'success' => $successCount > 0,
                'sent_count' => $successCount,
                'total_subscriptions' => count($subscriptions),
                'results' => $results
            );
            
        } catch (Exception $e) {
            error_log("Push notification error for manager: " . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    private function getManagerSubscriptions($managerId) {
        try {
            error_log("Fetching subscriptions for manager: $managerId");
            $query = "user_id=eq.$managerId&user_role=eq.outlet_manager&is_active=eq.true&select=*";
            error_log("Subscription query: $query");
            $subscriptions = $this->supabase->get('push_subscriptions', $query);
            error_log("Found " . count($subscriptions) . " subscriptions for manager $managerId");
            return $subscriptions ?: array();
        } catch (Exception $e) {
            error_log("Error fetching manager subscriptions: " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Send notification to outlet manager by outlet ID
     */
    public function sendToOutlet($outletId, $title, $body, $data = array()) {
        try {
            error_log("Sending notification to outlet: $outletId");
            
            // Get outlet manager ID
            $outlet = $this->supabase->get('outlets', "id=eq.$outletId&select=outlet_manager_id,manager_id,outlet_name");
            
            if (empty($outlet)) {
                error_log("Outlet not found: $outletId");
                return array('success' => false, 'message' => 'Outlet not found');
            }
            
            $outletData = $outlet[0];
            $managerId = $outletData['outlet_manager_id'] ?? $outletData['manager_id'] ?? null;
            
            if (empty($managerId)) {
                error_log("No manager assigned to outlet: $outletId");
                return array('success' => false, 'message' => 'No manager assigned');
            }
            
            error_log("Sending to manager $managerId for outlet {$outletData['outlet_name']}");
            return $this->sendToManager($managerId, $title, $body, $data);
            
        } catch (Exception $e) {
            error_log("Error sending to outlet: " . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Send notification to all outlets in a trip route (origin, destination, and stops)
     */
    public function sendToAllOutletsInRoute($tripId, $title, $body, $data = array()) {
        try {
            error_log("Sending notifications to all outlets in route for trip: $tripId");
            
            // Get trip details
            $trip = $this->supabase->get('trips', "id=eq.$tripId&select=origin_outlet_id,destination_outlet_id");
            
            if (empty($trip)) {
                error_log("Trip not found: $tripId");
                return array('success' => false, 'message' => 'Trip not found');
            }
            
            $tripData = $trip[0];
            $outletIds = array();
            
            // Add origin and destination outlets
            if (!empty($tripData['origin_outlet_id'])) {
                $outletIds[] = $tripData['origin_outlet_id'];
            }
            if (!empty($tripData['destination_outlet_id'])) {
                $outletIds[] = $tripData['destination_outlet_id'];
            }
            
            // Get all intermediate stops
            $stops = $this->supabase->get('trip_stops', "trip_id=eq.$tripId&select=outlet_id");
            if (!empty($stops)) {
                foreach ($stops as $stop) {
                    if (!empty($stop['outlet_id'])) {
                        $outletIds[] = $stop['outlet_id'];
                    }
                }
            }
            
            // Remove duplicates
            $outletIds = array_unique($outletIds);
            
            error_log("Found " . count($outletIds) . " outlets in route: " . implode(', ', $outletIds));
            
            $results = array();
            foreach ($outletIds as $outletId) {
                $result = $this->sendToOutlet($outletId, $title, $body, $data);
                $results[] = array(
                    'outlet_id' => $outletId,
                    'result' => $result
                );
            }
            
            $successCount = count(array_filter($results, function($r) { 
                return isset($r['result']['success']) && $r['result']['success']; 
            }));
            
            return array(
                'success' => $successCount > 0,
                'total_outlets' => count($outletIds),
                'successful_sends' => $successCount,
                'results' => $results
            );
            
        } catch (Exception $e) {
            error_log("Error sending to route outlets: " . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    private function getOutletName($outletId) {
        try {
            $outlet = $this->supabase->get('outlets', "id=eq.$outletId", 'outlet_name');
            return !empty($outlet) && isset($outlet[0]['outlet_name']) ? $outlet[0]['outlet_name'] : 'Outlet';
        } catch (Exception $e) {
            error_log("Error fetching outlet name: " . $e->getMessage());
            return 'Outlet';
        }
    }
    
    private function sendToCustomer($parcel, $title, $body, $data = array()) {
        try {
            
            $subscriptions = $this->getCustomerSubscriptions($parcel);
            
            if (empty($subscriptions)) {
                error_log("No push subscriptions found for parcel: " . $parcel['track_number']);
                return array('success' => false, 'message' => 'No subscriptions found');
            }
            
            $successCount = 0;
            $results = array();
            
            foreach ($subscriptions as $sub) {
                error_log("Attempting to send to customer subscription: " . $sub['id']);
                
                $subscription = Subscription::create(array(
                    'endpoint' => $sub['endpoint'],
                    'keys' => array(
                        'p256dh' => $sub['p256dh_key'],
                        'auth' => $sub['auth_key']
                    )
                ));
                
                $payload = json_encode(array(
                    'title' => $title,
                    'body' => $body,
                    'icon' => 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>',
                    'badge' => 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>',
                    'data' => array_merge($data, ['tracking_number' => $parcel['track_number']]),
                    'vibrate' => array(200, 100, 200, 100, 200),
                    'tag' => 'parcel-update',
                    'requireInteraction' => true
                ));
                
                $result = $this->webPush->sendOneNotification($subscription, $payload);
                
                if ($result->isSuccess()) {
                    $successCount++;
                    error_log("✅ Customer notification sent successfully to: " . $sub['id']);
                    $results[] = array('subscription_id' => $sub['id'], 'success' => true);
                } else {
                    error_log("❌ Customer notification failed for " . $sub['id'] . ": " . $result->getReason());
                    $results[] = array('subscription_id' => $sub['id'], 'success' => false, 'reason' => $result->getReason());
                    
                    if ($result->isSubscriptionExpired()) {
                        $this->markSubscriptionInactive($sub['id']);
                    }
                }
            }
            
            return array(
                'success' => $successCount > 0,
                'sent_count' => $successCount,
                'total_subscriptions' => count($subscriptions),
                'results' => $results
            );
            
        } catch (Exception $e) {
            error_log("Push notification error for customer: " . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    private function getCustomerSubscriptions($parcel) {
        try {
            $userIds = array();
            
            
            if (!empty($parcel['global_sender_id'])) {
                $userIds[] = $parcel['global_sender_id'];
                error_log("Added sender ID: " . $parcel['global_sender_id']);
            }
            
            
            if (!empty($parcel['global_receiver_id'])) {
                $userIds[] = $parcel['global_receiver_id'];
                error_log("Added receiver ID: " . $parcel['global_receiver_id']);
            }
            
            
            if (empty($userIds)) {
                error_log("No user IDs found in parcel data");
                return array();
            }
            
            
            $userIdsStr = implode(',', $userIds);
            $query = "user_id=in.($userIdsStr)&user_role=in.(sender,receiver,customer)&is_active=eq.true&select=*";
            error_log("Querying subscriptions with: " . $query);
            $subscriptions = $this->supabase->get('push_subscriptions', $query);
            error_log("Found subscriptions: " . count($subscriptions ?: array()));
            
            return $subscriptions ?: array();
        } catch (Exception $e) {
            error_log("Error fetching customer subscriptions: " . $e->getMessage());
            return array();
        }
    }
    
    private function sendSMS($phoneNumber, $message) {
        
        
        error_log("SMS to $phoneNumber: $message");
        return array('success' => true, 'phone' => $phoneNumber, 'message' => 'SMS logged');
    }
    
    
    private function sendEmailFallback($driverId, $title, $body, $data = array()) {
        try {
            
            $driver = $this->supabase->get('drivers', "id=eq.$driverId&select=email,name");
            
            if (empty($driver) || empty($driver[0]['email'])) {
                error_log("⚠️ No email found for driver $driverId - cannot send fallback");
                return false;
            }
            
            $email = $driver[0]['email'];
            $name = $driver[0]['name'] ?? 'Driver';
            
            error_log("📧 Sending email fallback to $email");
            
            
            $subject = "Trip Assignment: $title";
            $message = "
Hello $name,

$body

This is an automated notification. Please log into your driver dashboard for more details.

Best regards,
WDParcel Team
            ";
            
            $headers = "From: notifications@wdparcel.com\r\n";
            $headers .= "Reply-To: support@wdparcel.com\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            if (mail($email, $subject, $message, $headers)) {
                error_log("✅ Email fallback sent successfully to $email");
                return true;
            } else {
                error_log("❌ Email fallback failed to $email");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Email fallback error: " . $e->getMessage());
            return false;
        }
    }
}
