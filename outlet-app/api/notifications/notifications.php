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

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

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
            
            $companyId = $_SESSION['company_id'];
            $outletId = $_SESSION['outlet_id'];
            $supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
            $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
            $filterType = $_GET['type'] ?? 'all';
            $filterStatus = $_GET['status'] ?? 'all';
            $searchTrack = $_GET['parcel_id'] ?? '';
            $filters = [];
            if ($filterType !== 'all') {
                $filters[] = "notification_type=eq.$filterType";
            }
            if ($filterStatus !== 'all') {
                $filters[] = "status=eq.$filterStatus";
            }
            if ($searchTrack !== '') {
                $filters[] = "parcel_id=eq.$searchTrack";
            }
            $filterString = implode('&', $filters);
            $queryUrl = "$supabaseUrl/rest/v1/notifications?company_id=eq.$companyId&outlet_id=eq.$outletId";
            if ($filterString) {
                $queryUrl .= "&$filterString";
            }
            $queryUrl .= "&order=created_at.desc&limit=10";
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        "apikey: $supabaseKey",
                        "Authorization: Bearer $supabaseKey",
                        "Content-Type: application/json"
                    ]
                ]
            ]);
            $response = file_get_contents($queryUrl, false, $context);
            $notifications = json_decode($response, true);
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
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
                
                $filters = "company_id=eq.$company_id&status=eq.unread";
                if ($outlet_id) {
                    $filters .= "&outlet_id=eq.$outlet_id";
                } else {
                    $filters .= "&outlet_id=is.null";
                }
                
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
