<?php
require_once '../includes/supabase-helper-bypass.php';
require_once '../auth/session-manager.php';

SessionManager::init();

if (!SessionManager::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userData = SessionManager::getUserData();
$userId = $userData['user_id'];
$outletId = $userData['outlet_id'] ?? null;
$companyId = $userData['company_id'] ?? null;

if (!$userId || !$companyId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user or company information']);
    exit;
}

try {
    $supabase = new SupabaseHelperWithBypass();
    
    
    $filter = "user_id=eq.$userId&company_id=eq.$companyId";
    if ($outletId) {
        $filter .= "&outlet_id=eq.$outletId";
    }
    
    
    $result = $supabase->get('notifications', $filter);
    
    
    if ($result === null || $result === false) {
        throw new Exception('Query failed - no result returned');
    }
    
    if (!$result['success']) {
        throw new Exception($result['error'] ?? 'Query failed');
    }
    
    $notifications = $result['data'] ?? [];
    
    
    $totalCount = count($notifications);
    $unreadCount = 0;
    $urgentCount = 0;
    $highCount = 0;
    
    foreach ($notifications as $notification) {
        if (!($notification['is_read'] ?? false)) {
            $unreadCount++;
            if ($notification['priority'] === 'urgent') {
                $urgentCount++;
            } elseif ($notification['priority'] === 'high') {
            }
        }
    }
    
    $counts = [
        'total_count' => $totalCount,
        'unread_count' => $unreadCount,
        'urgent_count' => $urgentCount,
        'high_count' => $highCount
    ];
    
    echo json_encode([
        'success' => true,
        'counts' => $counts,
        'unread_count' => $unreadCount 
    ]);
    
} catch (Exception $e) {
    error_log("Notification counts error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch notification counts']);
}
