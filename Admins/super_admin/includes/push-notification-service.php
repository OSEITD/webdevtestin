<?php
require_once __DIR__ . '/env.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class AdminPushNotificationService {
    private $supabaseClient;
    private $webPush;
    
    public function __construct() {
        require_once __DIR__ . '/supabase-client.php';
        
        // Load environment
        EnvLoader::load(__DIR__ . '/../../.env');
        
        $auth = array(
            'VAPID' => array(
                'subject' => EnvLoader::get('VAPID_SUBJECT', 'mailto:admin@yourcompany.com'),
                'publicKey' => EnvLoader::get('VAPID_PUBLIC_KEY'),
                'privateKey' => EnvLoader::get('VAPID_PRIVATE_KEY'),
            ),
        );
        
        $this->webPush = new WebPush($auth);
    }
    
    /**
     * Send push notification to admin user
     */
    public function sendToAdmin($adminId, $title, $body, $data = array()) {
        try {
            error_log("=== SENDING ADMIN PUSH: $adminId ===");
            error_log("Title: $title");
            error_log("Body: $body");
            
            $subscriptions = $this->getAdminSubscriptions($adminId);
            
            if (empty($subscriptions)) {
                error_log("❌ No push subscriptions found for admin: $adminId");
                return array('success' => false, 'message' => 'No subscriptions found');
            }
            
            error_log("✅ Found " . count($subscriptions) . " active subscription(s) for admin");
            
            $successCount = 0;
            $results = array();
            
            foreach ($subscriptions as $sub) {
                try {
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
                        'icon' => '/Admins/super_admin/assets/img/logo.png',
                        'badge' => '/Admins/super_admin/assets/img/icon-72x72.png',
                        'data' => $data,
                        'actions' => isset($data['actions']) ? $data['actions'] : array(
                            array('action' => 'view', 'title' => 'View'),
                            array('action' => 'dismiss', 'title' => 'Dismiss')
                        ),
                        'tag' => 'admin-notification',
                        'requireInteraction' => true,
                        'vibrate' => array(200, 100, 200)
                    ));
                    
                    $result = $this->webPush->sendOneNotification($subscription, $payload);
                    
                    if ($result->isSuccess()) {
                        $successCount++;
                        error_log("✅ Admin notification sent successfully to subscription: " . $sub['id']);
                        $results[] = array('success' => true, 'subscription_id' => $sub['id']);
                    } else {
                        $reason = $result->getReason();
                        error_log("❌ Admin notification FAILED for subscription " . $sub['id'] . ": " . $reason);
                        $results[] = array('success' => false, 'error' => $reason);
                        
                        // Mark subscription as inactive if expired
                        if ($result->isSubscriptionExpired() || strpos($reason, '403') !== false) {
                            error_log("⚠️ Marking subscription as inactive: " . $sub['id']);
                            $this->markSubscriptionInactive($sub['id']);
                        }
                    }
                } catch (Exception $e) {
                    error_log("❌ Error sending to subscription: " . $e->getMessage());
                    $results[] = array('success' => false, 'error' => $e->getMessage());
                }
            }
            
            error_log("=== ADMIN PUSH RESULT: $successCount/" . count($subscriptions) . " sent ===");
            
            return array(
                'success' => $successCount > 0,
                'sent_count' => $successCount,
                'total_subscriptions' => count($subscriptions),
                'results' => $results
            );
            
        } catch (Exception $e) {
            error_log("❌ Admin push notification error: " . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Get active push subscriptions for admin
     */
    private function getAdminSubscriptions($adminId) {
        try {
            $query = "user_id=eq.$adminId&is_active=eq.true&select=*";
            $subscriptions = callSupabaseWithServiceKey('push_subscriptions', 'GET', array(
                'select' => '*',
                'filters' => array('user_id' => $adminId, 'is_active' => 'true')
            ));
            
            return is_array($subscriptions) ? $subscriptions : array();
        } catch (Exception $e) {
            error_log("Error fetching subscriptions: " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Mark subscription as inactive
     */
    private function markSubscriptionInactive($subscriptionId) {
        try {
            $result = callSupabaseWithServiceKey('push_subscriptions', 'PATCH', array(
                'filters' => array('id' => $subscriptionId),
                'data' => array('is_active' => false)
            ));
            
            error_log("✅ Marked subscription $subscriptionId as inactive");
            return true;
        } catch (Exception $e) {
            error_log("❌ Error marking subscription inactive: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log notification for audit trail
     */
    public function logNotification($adminId, $title, $body, $data, $status) {
        try {
            $payload = array(
                'user_id' => $adminId,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
                'status' => $status,
                'sent_at' => date('c'),
                'created_at' => date('c')
            );
            
            callSupabaseWithServiceKey('notification_logs', 'POST', $payload);
        } catch (Exception $e) {
            error_log("Error logging notification: " . $e->getMessage());
        }
    }
}
