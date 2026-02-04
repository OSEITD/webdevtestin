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
    
    if (!$driver_id) {
        throw new Exception('Driver ID is required');
    }
    
  
    $response = [
        'success' => true,
        'has_current_trip' => false,
        'trip' => null,
        'message' => 'No active trip found'
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
