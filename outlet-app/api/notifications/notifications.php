<?php
require_once __DIR__ . '/../../includes/security_headers.php';
SecurityHeaders::apply();

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../includes/session_manager.php';
require_once __DIR__ . '/../../includes/auth_guard.php';

ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_flush();
    exit();
}

try {
    
    ob_clean();
    
    
    initSession();
    
    
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        error_log("Authentication failed - no user_id in session");
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required',
            'message' => 'Please log in to access this resource',
            'debug' => [
                'session_status' => session_status(),
                'session_id' => session_id(),
                'has_user_id' => isset($_SESSION['user_id'])
            ]
        ]);
        ob_end_flush();
        exit();
    }
    
    $current_user = getCurrentUser();
} catch (Exception $e) {
    ob_clean();
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required',
        'message' => 'Please log in to access this resource'
    ]);
    ob_end_flush();
    exit();
}

$current_user_id = $current_user['user_id'];
$outlet_id = $current_user['outlet_id'];
$company_id = $current_user['company_id'];

if (!$current_user_id || !$company_id) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

if (!class_exists('EnvLoader')) { require_once __DIR__ . '/../../includes/env.php'; }
EnvLoader::load();
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

$method = $_SERVER['REQUEST_METHOD'];

function makeSupabaseRequest($url, $method = 'GET', $data = null, $supabaseKey = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ],
        CURLOPT_CUSTOMREQUEST => $method
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'data' => json_decode($response, true),
        'status_code' => $http_code
    ];
}

try {
    switch ($method) {
        case 'GET':

            $companyId   = $_SESSION['company_id'];
            $outletId    = $_SESSION['outlet_id'];
            $userId      = $current_user_id;
            $supabaseUrl = getenv('SUPABASE_URL');
            $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

            // Pagination
            $limit  = max(1, min((int)($_GET['limit'] ?? 20), 100));
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $offset = ($page - 1) * $limit;

            // Scope: outlet-wide notifications for this outlet OR personal
            // notifications addressed directly to this user.
            // PostgREST OR syntax: or=(outlet_id.eq.X,recipient_id.eq.Y)
            $scopeFilter = 'company_id=eq.' . urlencode($companyId)
                . '&or=(outlet_id.eq.' . urlencode($outletId)
                . ',recipient_id.eq.' . urlencode($userId) . ')';

            // Optional additional filters
            $extraFilters = [];

            $filterStatus = $_GET['status'] ?? 'all';
            if ($filterStatus !== 'all') {
                $extraFilters[] = 'status=eq.' . urlencode($filterStatus);
            }

            // type filter: frontend sends 'parcel', 'delivery', 'payment', 'system'
            // notification_type values are like 'parcel_created', 'delivery_assigned', etc.
            $filterType = $_GET['type'] ?? '';
            if ($filterType !== '' && $filterType !== 'all') {
                $extraFilters[] = 'notification_type=like.' . urlencode($filterType) . '*';
            }

            // priority filter
            $filterPriority = $_GET['priority'] ?? '';
            if ($filterPriority !== '' && $filterPriority !== 'all') {
                $extraFilters[] = 'priority=eq.' . urlencode($filterPriority);
            }

            // date filter
            $filterDate = $_GET['date'] ?? '';
            if ($filterDate !== '') {
                switch ($filterDate) {
                    case 'today':
                        $extraFilters[] = 'created_at=gte.' . urlencode(date('Y-m-d') . 'T00:00:00Z');
                        break;
                    case 'week':
                        $extraFilters[] = 'created_at=gte.' . urlencode(date('Y-m-d', strtotime('-7 days')) . 'T00:00:00Z');
                        break;
                    case 'month':
                        $extraFilters[] = 'created_at=gte.' . urlencode(date('Y-m-d', strtotime('-30 days')) . 'T00:00:00Z');
                        break;
                }
            }

            $searchTrack = $_GET['parcel_id'] ?? '';
            if ($searchTrack !== '') {
                $extraFilters[] = 'parcel_id=eq.' . urlencode($searchTrack);
            }

            $queryUrl = "$supabaseUrl/rest/v1/notifications?$scopeFilter";
            foreach ($extraFilters as $f) {
                $queryUrl .= "&$f";
            }
            $queryUrl .= '&order=created_at.desc&limit=' . $limit . '&offset=' . $offset;

            // Also get unread count for this scope
            $unreadUrl = "$supabaseUrl/rest/v1/notifications?$scopeFilter"
                . '&status=eq.unread&select=id';

            $headers = [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: count=exact"
            ];

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers)
                ]
            ]);

            $response = file_get_contents($queryUrl, false, $context);
            $notifications = json_decode($response, true) ?? [];

            // Parse Content-Range header for total count
            $totalCount = count($notifications);
            $responseHeaders = $http_response_header ?? [];
            foreach ($responseHeaders as $h) {
                if (stripos($h, 'Content-Range:') === 0) {
                    // Content-Range: 0-19/47
                    if (preg_match('/\/(\d+)/', $h, $m)) {
                        $totalCount = (int)$m[1];
                    }
                }
            }

            // Fetch unread count separately (no limit)
            $unreadContext = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers)
                ]
            ]);
            $unreadResponse  = file_get_contents($unreadUrl, false, $unreadContext);
            $unreadItems     = json_decode($unreadResponse, true) ?? [];
            $unreadCount     = count($unreadItems);

            $totalPages = $limit > 0 ? (int)ceil($totalCount / $limit) : 1;

            echo json_encode([
                'success'      => true,
                'notifications' => $notifications,
                'count'        => count($notifications),
                'unread_count' => $unreadCount,
                'pagination'   => [
                    'page'        => $page,
                    'limit'       => $limit,
                    'total'       => $totalCount,
                    'total_pages' => $totalPages,
                    'has_next'    => $page < $totalPages,
                    'has_prev'    => $page > 1,
                ],
            ]);
            ob_end_flush();
            break;
            
        case 'PUT':
            
            $input = json_decode(file_get_contents('php://input'), true);
            $notification_id = $input['notification_id'] ?? null;
            $action = $input['action'] ?? 'mark_read';
            
            if (!$notification_id) {
                throw new Exception('Notification ID is required');
            }
            
            $updateData = [];
            switch ($action) {
                case 'mark_read':
                    $updateData = [
                        'status' => 'read',
                        'read_at' => date('c')
                    ];
                    break;
                case 'mark_unread':
                    $updateData = [
                        'status' => 'unread',
                        'read_at' => null
                    ];
                    break;
                case 'dismiss':
                    $updateData = ['status' => 'dismissed'];
                    break;
                case 'archive':
                    $updateData = ['status' => 'archived'];
                    break;
                default:
                    throw new Exception('Invalid action');
            }
            
            
            $filters = "id=eq.$notification_id&company_id=eq.$company_id";
            if ($outlet_id) {
                $filters .= "&outlet_id=eq.$outlet_id";
            }
            $url = "$supabaseUrl/rest/v1/notifications?" . $filters;
            $response = makeSupabaseRequest($url, 'PATCH', $updateData, $supabaseKey);
                
            if ($response['status_code'] >= 400) {
                throw new Exception('Failed to update notification');
            }
            
            echo json_encode(['success' => true, 'message' => 'Notification updated successfully']);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? 'mark_all_read';
            
            if ($action === 'mark_all_read') {

                $updateData = [
                    'status' => 'read',
                    'read_at' => date('c')
                ];

                // Only mark notifications visible to this user as read.
                // Same scope rule as GET: outlet-wide OR personally addressed.
                $filters = 'company_id=eq.' . urlencode($company_id)
                    . '&or=(outlet_id.eq.' . urlencode($outlet_id)
                    . ',recipient_id.eq.' . urlencode($current_user_id) . ')'
                    . '&status=eq.unread';

                $url = "$supabaseUrl/rest/v1/notifications?$filters";
                $response = makeSupabaseRequest($url, 'PATCH', $updateData, $supabaseKey);
                    
                if ($response['status_code'] >= 400) {
                    throw new Exception('Failed to mark all notifications as read');
                }
                
                echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
                
            } elseif ($action === 'bulk_action') {
                
                $notification_ids = $input['notification_ids'] ?? [];
                $bulk_action = $input['bulk_action'] ?? '';
                
                if (empty($notification_ids) || empty($bulk_action)) {
                    throw new Exception('Notification IDs and action are required');
                }
                
                $updateData = [];
                switch ($bulk_action) {
                    case 'mark_read':
                        $updateData = [
                            'status' => 'read',
                            'read_at' => date('c')
                        ];
                        break;
                    case 'mark_unread':
                        $updateData = [
                            'status' => 'unread',
                            'read_at' => null
                        ];
                        break;
                    case 'archive':
                        $updateData = ['status' => 'archived'];
                        break;
                    case 'delete':
                        
                        break;
                    default:
                        throw new Exception('Invalid bulk action');
                }
                
                if ($bulk_action === 'delete') {
                    
                    $deleted_count = 0;
                    foreach ($notification_ids as $id) {
                        $filters = "id=eq.$id&company_id=eq.$company_id";
                        if ($outlet_id) {
                            $filters .= "&outlet_id=eq.$outlet_id";
                        }
                        $url = "$supabaseUrl/rest/v1/notifications?" . $filters;
                        $response = makeSupabaseRequest($url, 'DELETE', null, $supabaseKey);
                        if ($response['status_code'] < 400) {
                            $deleted_count++;
                        }
                    }
                    echo json_encode(['success' => true, 'message' => "$deleted_count notifications deleted"]);
                } else {
                    
                    $updated_count = 0;
                    foreach ($notification_ids as $id) {
                        $filters = "id=eq.$id&company_id=eq.$company_id";
                        if ($outlet_id) {
                            $filters .= "&outlet_id=eq.$outlet_id";
                        }
                        $url = "$supabaseUrl/rest/v1/notifications?" . $filters;
                        $response = makeSupabaseRequest($url, 'PATCH', $updateData, $supabaseKey);
                        if ($response['status_code'] < 400) {
                            $updated_count++;
                        }
                    }
                    echo json_encode(['success' => true, 'message' => "$updated_count notifications updated"]);
                }
            }
            break;
            
        case 'DELETE':
            
            $input = json_decode(file_get_contents('php://input'), true);
            $notification_id = $input['notification_id'] ?? null;
            
            if (!$notification_id) {
                throw new Exception('Notification ID is required');
            }
            
            
            $filters = "id=eq.$notification_id&company_id=eq.$company_id";
            if ($outlet_id) {
                $filters .= "&outlet_id=eq.$outlet_id";
            }
            $url = "$supabaseUrl/rest/v1/notifications?" . $filters;
            $response = makeSupabaseRequest($url, 'DELETE', null, $supabaseKey);
                
            if ($response['status_code'] >= 400) {
                throw new Exception('Failed to delete notification');
            }
            
            echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
    ob_end_flush();
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    ob_end_flush();
}
?>
