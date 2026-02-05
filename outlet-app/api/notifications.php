<?php

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../includes/OutletAwareSupabaseHelper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
$action = $_GET['action'] ?? 'list';

$supabase = new OutletAwareSupabaseHelper();

try {
    switch ($action) {
        case 'list':
            
            $status = $_GET['status'] ?? 'all'; 
            $type = $_GET['type'] ?? 'all';
            $limit = min((int)($_GET['limit'] ?? 20), 50);
            $offset = (int)($_GET['offset'] ?? 0);

            
            $filters = 'company_id=eq.' . urlencode($companyId) . 
                      '&recipient_id=eq.' . urlencode($userId);
            
            if ($status !== 'all') {
                $filters .= '&status=eq.' . urlencode($status);
            }
            
            if ($type !== 'all') {
                $filters .= '&notification_type=eq.' . urlencode($type);
            }

            $notifications = $supabase->get('notifications',
                $filters . 
                '&select=*' .
                '&order=created_at.desc' .
                '&limit=' . $limit .
                '&offset=' . $offset
            );

            
            foreach ($notifications as &$notif) {
                
                if (!empty($notif['data'])) {
                    $notif['parsed_data'] = json_decode($notif['data'], true);
                }
                
                
                $notif['formatted_date'] = formatNotificationDate($notif['created_at']);
                $notif['time_ago'] = timeAgo($notif['created_at']);
            }

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
            break;

        case 'unread_count':
            
            $unreadNotifs = $supabase->get('notifications',
                'company_id=eq.' . urlencode($companyId) . 
                '&recipient_id=eq.' . urlencode($userId) .
                '&status=eq.unread' .
                '&select=id'
            );

            echo json_encode([
                'success' => true,
                'unread_count' => count($unreadNotifs)
            ]);
            break;

        case 'mark_read':
            
            $notificationIds = $_POST['notification_ids'] ?? [];
            
            if (empty($notificationIds)) {
                $notificationId = $_POST['notification_id'] ?? '';
                if ($notificationId) {
                    $notificationIds = [$notificationId];
                }
            }

            if (empty($notificationIds)) {
                echo json_encode(['success' => false, 'error' => 'No notification IDs provided']);
                exit;
            }

            $idsStr = implode(',', array_map('urlencode', $notificationIds));
            
            $supabase->update('notifications', [
                'status' => 'read',
                'read_at' => date('c'),
                'is_read' => true
            ], 'id=in.(' . $idsStr . ')&recipient_id=eq.' . urlencode($userId));

            echo json_encode([
                'success' => true,
                'message' => 'Notification(s) marked as read',
                'count' => count($notificationIds)
            ]);
            break;

        case 'mark_all_read':
            
            $supabase->update('notifications', [
                'status' => 'read',
                'read_at' => date('c'),
                'is_read' => true
            ], 'company_id=eq.' . urlencode($companyId) . 
               '&recipient_id=eq.' . urlencode($userId) . 
               '&status=eq.unread');

            echo json_encode([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
            break;

        case 'dismiss':
            
            $notificationId = $_POST['notification_id'] ?? '';
            
            if (empty($notificationId)) {
                echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
                exit;
            }

            $supabase->update('notifications', [
                'status' => 'dismissed'
            ], 'id=eq.' . urlencode($notificationId) . 
               '&recipient_id=eq.' . urlencode($userId));

            echo json_encode([
                'success' => true,
                'message' => 'Notification dismissed'
            ]);
            break;

        case 'archive':
            
            $notificationId = $_POST['notification_id'] ?? '';
            
            if (empty($notificationId)) {
                echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
                exit;
            }

            $supabase->update('notifications', [
                'status' => 'archived'
            ], 'id=eq.' . urlencode($notificationId) . 
               '&recipient_id=eq.' . urlencode($userId));

            echo json_encode([
                'success' => true,
                'message' => 'Notification archived'
            ]);
            break;

        case 'delete':
            
            $notificationId = $_POST['notification_id'] ?? '';
            
            if (empty($notificationId)) {
                echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
                exit;
            }

            // Perform a permanent delete, limited to the owner
            $filters = 'id=eq.' . urlencode($notificationId) . '&recipient_id=eq.' . urlencode($userId);
            $deleted = $supabase->delete('notifications', $filters);

            if ($deleted) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification permanently deleted'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Delete failed'
                ]);
            }
            break;

        case 'stats':
            
            $allNotifs = $supabase->get('notifications',
                'company_id=eq.' . urlencode($companyId) . 
                '&recipient_id=eq.' . urlencode($userId) .
                '&select=status,priority,notification_type,created_at'
            );

            $stats = [
                'total' => count($allNotifs),
                'unread' => 0,
                'read' => 0,
                'by_type' => [],
                'by_priority' => [
                    'urgent' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0
                ],
                'today' => 0
            ];

            $today = date('Y-m-d');
            foreach ($allNotifs as $notif) {
                
                if ($notif['status'] === 'unread') {
                    $stats['unread']++;
                } else if ($notif['status'] === 'read') {
                    $stats['read']++;
                }

                
                $type = $notif['notification_type'] ?? 'other';
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = 0;
                }
                $stats['by_type'][$type]++;

                
                $priority = $notif['priority'] ?? 'medium';
                if (isset($stats['by_priority'][$priority])) {
                    $stats['by_priority'][$priority]++;
                }

                
                if (strpos($notif['created_at'], $today) === 0) {
                    $stats['today']++;
                }
            }

            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Operation failed',
        'message' => $e->getMessage()
    ]);
}

function formatNotificationDate($dateStr) {
    $date = new DateTime($dateStr);
    $now = new DateTime();
    $diff = $now->diff($date);

    if ($diff->days === 0) {
        return 'Today at ' . $date->format('g:i A');
    } else if ($diff->days === 1) {
        return 'Yesterday at ' . $date->format('g:i A');
    } else if ($diff->days < 7) {
        return $date->format('l \a\t g:i A');
    } else {
        return $date->format('M j, Y \a\t g:i A');
    }
}

function timeAgo($dateStr) {
    $date = new DateTime($dateStr);
    $now = new DateTime();
    $diff = $now->diff($date);

    if ($diff->days > 30) {
        return $date->format('M j, Y');
    } else if ($diff->days > 0) {
        return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
    } else if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } else if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}
