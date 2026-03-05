<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../includes/supabase-helper.php';

try {
    $userId = $_SESSION['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['notification_id'] ?? null;
    $markAllRead = isset($input['mark_all']) && $input['mark_all'] === true;
    
    $supabase = new SupabaseHelper();
    
    if ($markAllRead) {
        
        $updateData = [
            'status' => 'read',
            'read_at' => date('c')
        ];
        
        $result = $supabase->patch('notifications', $updateData, "recipient_id=eq.$userId&status=eq.unread");
        
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    } else if ($notificationId) {
        
        $updateData = [
            'status' => 'read',
            'read_at' => date('c')
        ];
        
        $result = $supabase->patch('notifications', $updateData, "id=eq.$notificationId&recipient_id=eq.$userId");
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } else {
        throw new Exception('notification_id or mark_all is required');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
