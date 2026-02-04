<?php
require_once __DIR__ . '/error_handler.php';
session_start();
require_once '../../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['trip_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}
$tripId = $input['trip_id'];
$newStatus = $input['status'];
$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
$validStatuses = ['assigned', 'in_progress', 'completed', 'cancelled', 'paused'];
if (!in_array($newStatus, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit();
}
try {
    $pdo->beginTransaction();
    
    
    $stmt = $pdo->prepare("
        SELECT trip_id, status 
        FROM trips 
        WHERE trip_id = :trip_id 
        AND driver_id = :driver_id 
        AND company_id = :company_id
    ");
    
    $stmt->execute([
        ':trip_id' => $tripId,
        ':driver_id' => $driverId,
        ':company_id' => $companyId
    ]);
    
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trip) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Trip not found or unauthorized']);
        exit();
    }
    
    $currentStatus = $trip['status'];
    
    
    $validTransitions = [
        'assigned' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'paused', 'cancelled'],
        'paused' => ['in_progress', 'cancelled'],
        'completed' => [], 
        'cancelled' => [] 
    ];
    
    if (!in_array($newStatus, $validTransitions[$currentStatus])) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => "Cannot change status from {$currentStatus} to {$newStatus}"
        ]);
        exit();
    }
    
    
    $updateStmt = $pdo->prepare("
        UPDATE trips 
        SET status = :status, 
            updated_at = NOW(),
            started_at = CASE WHEN :status = 'in_progress' AND started_at IS NULL THEN NOW() ELSE started_at END,
            completed_at = CASE WHEN :status = 'completed' THEN NOW() ELSE completed_at END
        WHERE trip_id = :trip_id
    ");
    
    $updateStmt->execute([
        ':status' => $newStatus,
        ':trip_id' => $tripId
    ]);
    
    
    $logStmt = $pdo->prepare("
        INSERT INTO trip_status_logs (trip_id, old_status, new_status, changed_by, changed_at, notes)
        VALUES (:trip_id, :old_status, :new_status, :changed_by, NOW(), :notes)
    ");
    
    $logStmt->execute([
        ':trip_id' => $tripId,
        ':old_status' => $currentStatus,
        ':new_status' => $newStatus,
        ':changed_by' => $driverId,
        ':notes' => "Status changed by driver via mobile app"
    ]);
    
    
    if ($newStatus === 'completed') {
        $parcelStmt = $pdo->prepare("
            UPDATE parcels 
            SET status = 'delivered', 
                delivered_at = NOW(),
                updated_at = NOW()
            WHERE trip_id = :trip_id 
            AND status = 'in_transit'
        ");
        
        $parcelStmt->execute([':trip_id' => $tripId]);
    }
    
    
    if ($newStatus === 'in_progress') {
        $parcelStmt = $pdo->prepare("
            UPDATE parcels 
            SET status = 'in_transit', 
                updated_at = NOW()
            WHERE trip_id = :trip_id 
            AND status = 'ready_for_pickup'
        ");
        
        $parcelStmt->execute([':trip_id' => $tripId]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Trip status updated to {$newStatus}",
        'data' => [
            'trip_id' => $tripId,
            'old_status' => $currentStatus,
            'new_status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error updating trip status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update trip status'
    ]);
}
?>
