<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../includes/supabase.php';

try {
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'] ?? null;
    
    $supabase = new SupabaseHelper();
    
    
    $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 20;
    $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';
    
    
    $query = "recipient_id=eq.$userId";
    
    if ($companyId) {
        $query .= "&company_id=eq.$companyId";
    }
    
    if ($unreadOnly) {
        $query .= "&status=eq.unread";
    }
    
    $query .= "&order=created_at.desc&limit=$limit";
    
    $notifications = $supabase->get('notifications', $query, '*');
    
    if (!is_array($notifications)) {
        $notifications = [];
    }
    
    
    $unreadCount = 0;
    if (!$unreadOnly) {
        $countQuery = "recipient_id=eq.$userId&status=eq.unread";
        if ($companyId) {
            $countQuery .= "&company_id=eq.$companyId";
        }
        $unreadNotifications = $supabase->get('notifications', $countQuery, 'id');
        $unreadCount = is_array($unreadNotifications) ? count($unreadNotifications) : 0;
    } else {
        $unreadCount = count($notifications);
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'total' => count($notifications)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
