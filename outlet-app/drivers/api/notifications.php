<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/../../includes/session_manager.php';
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';

initSession();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'] ?? null;

if (!$companyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing company context']);
    exit;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
$limit = max(1, min($limit, 20));
$page = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
$offset = ($page - 1) * $limit;

try {
    $supabase = new OutletAwareSupabaseHelper();

    $query = 'recipient_id=eq.' . urlencode($driverId)
        . '&company_id=eq.' . urlencode($companyId)
        . '&order=created_at.desc'
        . '&limit=' . $limit
        . '&offset=' . $offset;

    $notifications = $supabase->get('notifications', $query, 'id,title,message,status,created_at,notification_type,data,outlet_id,parcel_id');
    if (!is_array($notifications)) {
        $notifications = [];
    }

    $unreadRecords = $supabase->get(
        'notifications',
        'recipient_id=eq.' . urlencode($driverId) . '&company_id=eq.' . urlencode($companyId) . '&status=eq.unread&select=id'
    );

    $unreadCount = is_array($unreadRecords) ? count($unreadRecords) : 0;

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'total_count' => count($notifications),
        'page' => $page,
        'limit' => $limit
    ]);
} catch (Exception $e) {
    error_log('[Driver Notifications] ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load notifications',
    ]);
}
?>
