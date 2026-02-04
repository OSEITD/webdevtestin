
require_once __DIR__ . '/../../outlet-app/includes/OutletAwareSupabaseHelper.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? 'list';
$phone = $_GET['phone'] ?? $_POST['phone'] ?? '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';

$supabase = new OutletAwareSupabaseHelper();

try {
    switch ($action) {
        case 'list':
            // Get customer ID from phone or email
            if (empty($phone) && empty($email)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Phone or email required'
                ]);
                exit;
            }
            
            // Find customer in global_customers
            $filter = '';
            if ($phone) {
                $filter = 'phone=eq.' . urlencode($phone);
            } elseif ($email) {
                $filter = 'email=eq.' . urlencode($email);
            }
            
            $customers = $supabase->get('global_customers', $filter);
            
            if (empty($customers)) {
                echo json_encode([
                    'success' => true,
                    'notifications' => [],
                    'unread_count' => 0,
                    'message' => 'No notifications found'
                ]);
                exit;
            }

<?php

require_once __DIR__ . '/../../outlet-app/includes/OutletAwareSupabaseHelper.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? 'list';
$phone = $_GET['phone'] ?? $_POST['phone'] ?? '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';

$supabase = new OutletAwareSupabaseHelper();

try {
    switch ($action) {
        case 'list':
            if (empty($phone) && empty($email)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Phone or email required'
                ]);
                exit;
            }
            $filter = '';
            if ($phone) {
                $filter = 'phone=eq.' . urlencode($phone);
            } elseif ($email) {
                $filter = 'email=eq.' . urlencode($email);
            }
            $customers = $supabase->get('global_customers', $filter);
            if (empty($customers)) {
                echo json_encode([
                    'success' => true,
                    'notifications' => [],
                    'unread_count' => 0,
                    'message' => 'No notifications found'
                ]);
                exit;
            }
            
            
            
            

            $customerId = $customers[0]['id'];
            $notifications = $supabase->get('notifications', 
                'recipient_id=eq.' . urlencode($customerId) . 
                '&order=created_at.desc&limit=50'
            );
            $unreadCount = 0;
            foreach ($notifications as $notification) {
                if ($notification['status'] === 'unread') {
                    $unreadCount++;
                }
            }
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
                'total_count' => count($notifications)
            ]);
            break;
        case 'mark_read':
            $notificationId = $_POST['notification_id'] ?? '';
            if (empty($notificationId)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'notification_id required'
                ]);
                exit;
            }
            $supabase->update('notifications', [
                'status' => 'read',
                'is_read' => true,
                'read_at' => date('Y-m-d H:i:s')
            ], 'id=eq.' . urlencode($notificationId));
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
            break;
        case 'mark_all_read':
            if (empty($phone) && empty($email)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Phone or email required'
                ]);
                exit;
            }
            $filter = '';
            if ($phone) {
                $filter = 'phone=eq.' . urlencode($phone);
            } elseif ($email) {
                $filter = 'email=eq.' . urlencode($email);
            }
            $customers = $supabase->get('global_customers', $filter);
            if (empty($customers)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Customer not found'
                ]);
                exit;
            }
            $customerId = $customers[0]['id'];
            $supabase->update('notifications', [
                'status' => 'read',
                'is_read' => true,
                'read_at' => date('Y-m-d H:i:s')
            ], 'recipient_id=eq.' . urlencode($customerId) . '&status=eq.unread');
            echo json_encode([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
            break;
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    error_log("Notification API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
