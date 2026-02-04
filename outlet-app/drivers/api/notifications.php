<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
session_start();
require_once '../../config.php';
try {
    $driver_id = $_GET['driver_id'] ?? $_SESSION['user_id'] ?? null;
    $unread_only = isset($_GET['unread']) && $_GET['unread'] === 'true';
    
    if (!$driver_id) {
        throw new Exception('Driver ID is required');
    }
  
    $notifications = [];
    
 
    
    $unread_count = 0;
    if ($unread_only) {
        $notifications = array_filter($notifications, function($notif) {
            return !$notif['read'];
        });
    }
    
    $unread_count = count(array_filter($notifications, function($notif) {
        return !$notif['read'];
    }));
    
    $response = [
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count,
        'total_count' => count($notifications)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
