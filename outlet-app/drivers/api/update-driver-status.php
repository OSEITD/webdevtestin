<?php
session_start();
require_once '../../config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}
try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    $driverId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'];
    $status = $input['status'] ?? null;
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    if (!$status || !in_array($status, ['available', 'busy', 'offline'])) {
        throw new Exception('Valid status required (available, busy, offline)');
    }
    $driverQuery = "
        SELECT driver_id, status
        FROM drivers
        WHERE driver_id = ? AND company_id = ?
    ";
    $stmt = $pdo->prepare($driverQuery);
    $stmt->execute([$driverId, $companyId]);
    $driverData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$driverData) {
        throw new Exception('Driver not found or access denied');
    }
    $currentStatus = $driverData['status'];
    $updateFields = [
        'status = ?',
        'last_seen = NOW()',
        'updated_at = NOW()'
    ];
    $updateValues = [$status];
    if ($latitude !== null && $longitude !== null) {
        $updateFields[] = 'current_latitude = ?';
        $updateFields[] = 'current_longitude = ?';
        $updateFields[] = 'location_updated_at = NOW()';
        $updateValues[] = (float)$latitude;
        $updateValues[] = (float)$longitude;
    }
    $updateValues[] = $driverId;
    $updateValues[] = $companyId;
    $updateQuery = "
        UPDATE drivers
        SET " . implode(', ', $updateFields) . "
        WHERE driver_id = ? AND company_id = ?
    ";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateResult = $updateStmt->execute($updateValues);
    if (!$updateResult) {
        throw new Exception('Failed to update driver status');
    }
    $logQuery = "
        INSERT INTO driver_activity_logs
        (driver_id, company_id, action, details, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ";
    $logDetails = json_encode([
        'old_status' => $currentStatus,
        'new_status' => $status,
        'location' => ($latitude && $longitude) ? ['lat' => $latitude, 'lng' => $longitude] : null,
        'timestamp' => date('c'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute([$driverId, $companyId, 'status_change', $logDetails]);
    if ($status === 'offline') {
        $activeTripsQuery = "
            SELECT trip_id
            FROM trips
            WHERE driver_id = ? AND company_id = ? AND status IN ('assigned', 'in_progress')
        ";
        $activeStmt = $pdo->prepare($activeTripsQuery);
        $activeStmt->execute([$driverId, $companyId]);
        $activeTrips = $activeStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($activeTrips) {
            foreach ($activeTrips as $trip) {
                $tripLogDetails = json_encode([
                    'reason' => 'Driver went offline',
                    'timestamp' => date('c')
                ]);
                $tripLogQuery = "
                    INSERT INTO driver_activity_logs
                    (trip_id, driver_id, company_id, action, details, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ";
                $tripLogStmt = $pdo->prepare($tripLogQuery);
                $tripLogStmt->execute([
                    $trip['trip_id'],
                    $driverId,
                    $companyId,
                    'driver_offline',
                    $tripLogDetails
                ]);
            }
        }
    }
    $updatedDriverQuery = "
        SELECT driver_id, status, last_seen, current_latitude, current_longitude, location_updated_at
        FROM drivers
        WHERE driver_id = ? AND company_id = ?
    ";
    $updatedStmt = $pdo->prepare($updatedDriverQuery);
    $updatedStmt->execute([$driverId, $companyId]);
    $updatedDriver = $updatedStmt->fetch(PDO::FETCH_ASSOC);
    $response = [
        'success' => true,
        'message' => 'Status updated successfully',
        'data' => [
            'driver_id' => $driverId,
            'status' => $status,
            'previous_status' => $currentStatus,
            'last_seen' => $updatedDriver['last_seen'] ?? date('Y-m-d H:i:s'),
            'location' => ($latitude && $longitude) ? [
                'latitude' => (float)$latitude,
                'longitude' => (float)$longitude,
                'updated_at' => $updatedDriver['location_updated_at'] ?? date('Y-m-d H:i:s')
            ] : null,
            'updated_at' => date('c')
        ]
    ];
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Driver Status Update Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
