<?php
require_once __DIR__ . '/init.php';

// Verify authentication and role
ErrorHandler::requireAuth('notifications.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    if (!isset($_SESSION['id'])) {
        file_put_contents(__DIR__ . '/session_debug.txt', "403 at " . date('Y-m-d H:i:s') . "\nSession content: " . print_r($_SESSION, true) . "\nHeaders: " . print_r(getallheaders(), true) . "\n\n", FILE_APPEND);
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to access this resource.']);
        exit;
    }
}
$isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
$companyId = $_SESSION['id'] ?? null;

require_once 'supabase-client.php';

$userId = $_SESSION['user_id'] ?? null;
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $limit = min((int)($_GET['limit'] ?? 20), 50);
            $offset = (int)($_GET['offset'] ?? 0);
            
            // Build parameters for callSupabaseWithServiceKey
            $params = [
                'select' => '*',
                'order' => 'created_at.desc',
                'limit' => $limit,
                'offset' => $offset
            ];
            
            // If user_id is set, filter by it. 
            if ($userId && !$isSuperAdmin && !$companyId) {
                // strict user-only view fallback
                $params['filters'] = ['recipient_id' => $userId];
            } else if (!$isSuperAdmin && $companyId) {
                // company view
                $params['filters'] = ['company_id' => $companyId];
            } else {
                $params['filters'] = [];
            }
            
            // Advanced frontend UI filters
            $filterType = $_GET['type'] ?? 'all';
            $filterStatus = $_GET['status'] ?? 'all';
            $filterPriority = $_GET['priority'] ?? 'all';
            $filterDate = $_GET['date'] ?? 'all';
            
            if ($filterType !== 'all') {
                $params['filters']['notification_type'] = $filterType;
            }
            
            if ($filterStatus === 'unread') {
                $params['filters']['is_read'] = 'false';
            } elseif ($filterStatus === 'read') {
                $params['filters']['is_read'] = 'true';
            } elseif ($filterStatus !== 'all') {
                $params['filters']['status'] = $filterStatus;
            }
            
            if ($filterPriority !== 'all') {
                $params['filters']['priority'] = $filterPriority;
            }
            
            // Note: date filtering is tricky with strictly equality-based params['filters']. 
            // supabase-client.php might not support gt/lt out of the box through `filters`. 
            // For now, doing it in PHP below if needed, or hoping the client supports it.
            // Since we need it, we'll manually filter in PHP for dates.
            
            $response = callSupabaseWithServiceKey("notifications", 'GET', $params);
            
            if (isset($response['error'])) {
                throw new Exception($response['error']['message'] ?? 'Unknown error fetching notifications');
            }
            
            // If response is not an array (e.g. valid but empty response parsed as null? or object), ensure array
            $notifications = is_array($response) ? $response : [];
            
            // format dates & process manual date filters
            $filteredNotifications = [];
            $now = new DateTime();
            
            foreach ($notifications as &$notif) {
                if (isset($notif['data']) && is_string($notif['data'])) {
                    $notif['parsed_data'] = json_decode($notif['data'], true);
                }
                
                $notifDate = null;
                if (isset($notif['created_at'])) {
                    $notif['formatted_date'] = formatNotificationDate($notif['created_at']);
                    $notif['time_ago'] = timeAgo($notif['created_at']);
                    try { $notifDate = new DateTime($notif['created_at']); } catch (Exception $e) {}
                }
                
                // Manual Date filter application
                $keep = true;
                if ($notifDate && $filterDate !== 'all') {
                    $diffDays = $now->diff($notifDate)->days;
                    if ($filterDate === 'today' && $diffDays > 0) $keep = false;
                    if ($filterDate === 'week' && $diffDays > 7) $keep = false;
                    if ($filterDate === 'month' && $diffDays > 30) $keep = false;
                }
                
                if ($keep) {
                    $filteredNotifications[] = $notif;
                }
            }
            unset($notif);

            echo json_encode([
                'success' => true, 
                'notifications' => $filteredNotifications,
                'pagination' => [
                    'total' => count($filteredNotifications), // Note: true total requires a count query without limit, approximating for now.
                    'page' => ($offset / $limit) + 1,
                    'limit' => $limit
                ],
                'unread_count' => count(array_filter($filteredNotifications, fn($n) => isset($n['is_read']) && $n['is_read'] === false))
            ]);
            break;

        case 'unread_count':
            // We use select=id and count the results in PHP as a simple method using the Service Key client
            // which returns the body.
            $params = [
                'select' => 'id',
                'filters' => ['is_read' => 'false']
            ];
            
            if (!$isSuperAdmin && $companyId) {
                $params['filters']['company_id'] = $companyId;
            } else if ($userId && !$isSuperAdmin) {
                $params['filters']['recipient_id'] = $userId;
            }
            
            $unread = callSupabaseWithServiceKey("notifications", 'GET', $params);
            
            if (isset($unread['error'])) {
                 throw new Exception($unread['error']['message'] ?? 'Error counting unread');
            }
            
            $count = is_array($unread) ? count($unread) : 0;
            
            echo json_encode(['success' => true, 'unread_count' => $count]);
            break;

        case 'mark_read':
            $notificationIds = $_POST['notification_ids'] ?? [];
            if (empty($notificationIds)) {
                $singleId = $_POST['notification_id'] ?? null;
                if ($singleId) $notificationIds = [$singleId];
            }
            
            if (empty($notificationIds)) {
                echo json_encode(['success' => false, 'error' => 'No notification IDs provided']);
                exit;
            }
            
            $idsStr = implode(',', $notificationIds);
            // Construct endpoint with query params
            $endpoint = "notifications?id=in.($idsStr)";
            if ($userId) {
                $endpoint .= "&recipient_id=eq.$userId";
            }
            
            $data = [
                'is_read' => true,
                'status' => 'read',
                'read_at' => date('c')
            ];
            
            $response = callSupabaseWithServiceKey($endpoint, 'PATCH', $data);
            
            if (isset($response['error'])) {
                throw new Exception($response['error']['message']);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'mark_all_read':
            $endpoint = "notifications?is_read=eq.false";
            if ($userId && !$isSuperAdmin && !$companyId) {
                $endpoint .= "&recipient_id=eq.$userId";
            } else if (!$isSuperAdmin && $companyId) {
                $endpoint .= "&company_id=eq.$companyId";
            }
            
            $data = [
                'is_read' => true,
                'status' => 'read',
                'read_at' => date('c')
            ];
            
            $response = callSupabaseWithServiceKey($endpoint, 'PATCH', $data);
            
             if (isset($response['error'])) {
                throw new Exception($response['error']['message']);
            }

            echo json_encode(['success' => true]);
            break;
            
        case 'dismiss':
            $notificationId = $_POST['notification_id'] ?? null;
            if (!$notificationId) {
                echo json_encode(['success' => false, 'error' => 'Missing ID']);
                exit;
            }
            
            $endpoint = "notifications?id=eq.$notificationId";
            if ($userId) {
                $endpoint .= "&recipient_id=eq.$userId";
            }
            
            $response = callSupabaseWithServiceKey($endpoint, 'PATCH', ['status' => 'dismissed']);
            
            if (isset($response['error'])) {
                throw new Exception($response['error']['message']);
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'bulk_action':
            // Read JSON payload for PUT methodology from the outlet UI
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                // Fallback to POST for backwards compatibility
                $notificationIds = $_POST['notification_ids'] ?? [];
                $bulkAction = $_POST['bulk_action'] ?? '';
            } else {
                $notificationIds = $input['notification_ids'] ?? [($input['notification_id'] ?? null)];
                $bulkAction = $input['bulk_action'] ?? $input['action'] ?? '';
            }
            
            $notificationIds = array_filter($notificationIds);
            
            if (empty($notificationIds) || empty($bulkAction)) {
                 echo json_encode(['success' => false, 'error' => 'Missing IDs or action']);
                 exit;
            }
            
            $idsStr = implode(',', $notificationIds);
            
            if ($bulkAction === 'delete') {
                $endpoint = "notifications?id=in.($idsStr)";
                if (!$isSuperAdmin && $companyId) $endpoint .= "&company_id=eq.$companyId";
                
                $response = callSupabaseWithServiceKey($endpoint, 'DELETE', null);
                if (isset($response['error'])) throw new Exception($response['error']['message']);
            } else {
                $updateData = [];
                switch ($bulkAction) {
                    case 'mark_read':
                        $updateData = ['is_read' => true, 'status' => 'read', 'read_at' => date('c')];
                        break;
                    case 'mark_unread':
                        $updateData = ['is_read' => false, 'status' => 'unread', 'read_at' => null];
                        break;
                    case 'archive':
                        $updateData = ['status' => 'archived'];
                        break;
                    case 'dismiss':
                        $updateData = ['status' => 'dismissed'];
                        break;
                    default:
                        throw new Exception('Invalid bulk action');
                }
                
                $endpoint = "notifications?id=in.($idsStr)";
                if (!$isSuperAdmin && $companyId) $endpoint .= "&company_id=eq.$companyId";
                
                $response = callSupabaseWithServiceKey($endpoint, 'PATCH', $updateData);
                if (isset($response['error'])) throw new Exception($response['error']['message']);
            }

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    // Log full context for debugging (server-side only)
    ErrorHandler::logError('Exception: ' . $e->getMessage(), 'notifications.php', [
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'session' => isset($_SESSION) ? $_SESSION : null,
        'get' => $_GET,
        'post' => $_POST
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error (see server logs)']);
}

// Helper Functions
function formatNotificationDate($dateStr) {
    try {
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
    } catch (Exception $e) {
        return $dateStr;
    }
}

function timeAgo($dateStr) {
    try {
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
    } catch (Exception $e) {
        return '';
    }
}
