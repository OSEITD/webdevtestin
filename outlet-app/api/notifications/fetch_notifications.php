<?php

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../includes/session_manager.php';

try {
    
    ob_clean();
    
    
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated'
        ]);
        ob_end_flush();
        exit();
    }

    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    
    $SUPABASE_URL = "https://xerpchdsykqafrsxbqef.supabase.co";
    $SUPABASE_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

    
    $companyId = $_SESSION['company_id'] ?? null;
    $outletId = $_SESSION['outlet_id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    
    $query = "notifications?";
    $filters = [];
    
    if ($companyId) {
        $filters[] = "company_id=eq.$companyId";
    }
    if ($outletId) {
        $filters[] = "outlet_id=eq.$outletId";
    }
    if ($userId) {
        $filters[] = "recipient_id=eq.$userId";
    }
    
    if (!empty($filters)) {
        $query .= implode('&', $filters) . '&';
    }
    
    $query .= "select=*&order=created_at.desc&limit=$limit&offset=$offset";

    $ch = curl_init("$SUPABASE_URL/rest/v1/$query");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $SUPABASE_API_KEY",
            "Authorization: Bearer $SUPABASE_API_KEY",
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $notifications = json_decode($response, true);
        
        if (is_array($notifications)) {
            
            $unreadCount = 0;
            $processedNotifications = [];
            
            foreach ($notifications as $notification) {
                
                $processedNotification = [
                    'id' => $notification['id'],
                    'title' => $notification['title'] ?? 'Notification',
                    'message' => $notification['message'] ?? '',
                    'type' => $notification['notification_type'] ?? 'general',
                    'priority' => $notification['priority'] ?? 'medium',
                    'is_read' => $notification['status'] === 'read',
                    'created_at' => $notification['created_at'] ?? date('c'),
                    'data' => $notification['data'] ? json_decode($notification['data'], true) : null
                ];
                
                if ($processedNotification['is_read'] === false) {
                    $unreadCount++;
                }
                
                $processedNotifications[] = $processedNotification;
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $processedNotifications,
                'count' => count($processedNotifications),
                'unread_count' => $unreadCount,
                'page' => $page,
                'limit' => $limit
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'notifications' => [],
                'count' => 0,
                'unread_count' => 0
            ]);
        }
    } else {
        
        echo json_encode([
            'success' => true,
            'notifications' => [],
            'count' => 0,
            'unread_count' => 0,
            'debug_error' => "HTTP $http_code: $response"
        ]);
    }
    
    ob_end_flush();

} catch (Exception $e) {
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'notifications' => [],
        'count' => 0,
        'unread_count' => 0,
        'debug_error' => $e->getMessage()
    ]);
    ob_end_flush();
}
?>
