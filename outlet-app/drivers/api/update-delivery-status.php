<?php
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
session_start();
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
    $parcelId = $input['parcel_id'] ?? null;
    $status = $input['status'] ?? null;
    $notes = $input['notes'] ?? '';
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $photo = $input['photo'] ?? null;
    $signature = $input['signature'] ?? null;
    $recipientName = $input['recipient_name'] ?? null;
    if (!$parcelId || !$status) {
        throw new Exception('Parcel ID and status are required');
    }
    $validStatuses = ['picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed_delivery', 'returned'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status provided');
    }
    $parcelQuery = "
        SELECT p.*, t.driver_id, t.trip_id, t.company_id as trip_company_id
        FROM parcels p
        LEFT JOIN trips t ON p.trip_id = t.trip_id
        WHERE p.parcel_id = ? AND t.company_id = ? AND t.driver_id = ?
    ";
    $stmt = $pdo->prepare($parcelQuery);
    $stmt->execute([$parcelId, $companyId, $driverId]);
    $parcel = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$parcel) {
        throw new Exception('Parcel not found or access denied');
    }
    $oldStatus = $parcel['status'];
    $validTransitions = [
        'assigned' => ['picked_up', 'failed_delivery'],
        'picked_up' => ['in_transit', 'failed_delivery'],
        'in_transit' => ['out_for_delivery', 'failed_delivery'],
        'out_for_delivery' => ['delivered', 'failed_delivery'],
        'failed_delivery' => ['picked_up', 'returned'],
        'delivered' => [],
        'returned' => []
    ];
    if (!in_array($status, $validTransitions[$oldStatus] ?? [])) {
        throw new Exception("Invalid status transition from {$oldStatus} to {$status}");
    }
    $updateData = [
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    if ($status === 'delivered') {
        if (!$recipientName) {
            throw new Exception('Recipient name is required for delivery');
        }
        $updateData['delivered_at'] = date('Y-m-d H:i:s');
        $updateData['delivered_by'] = $driverId;
        $updateData['recipient_name'] = $recipientName;
    }
    if ($status === 'failed_delivery' && empty($notes)) {
        throw new Exception('Notes are required for failed delivery');
    }
    $photoPath = null;
    if ($photo && $status === 'delivered') {
        $photoPath = saveDeliveryPhoto($photo, $parcelId, $companyId);
        $updateData['delivery_photo'] = $photoPath;
    }
    $signaturePath = null;
    if ($signature && $status === 'delivered') {
        $signaturePath = saveDeliverySignature($signature, $parcelId, $companyId);
        $updateData['signature'] = $signaturePath;
    }
    $updateFields = [];
    $updateValues = [];
    foreach ($updateData as $field => $value) {
        $updateFields[] = "{$field} = ?";
        $updateValues[] = $value;
    }
    $updateValues[] = $parcelId;
    $updateQuery = "UPDATE parcels SET " . implode(', ', $updateFields) . " WHERE parcel_id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateResult = $updateStmt->execute($updateValues);
    if (!$updateResult) {
        throw new Exception('Failed to update parcel status');
    }
    $logData = [
        'parcel_id' => $parcelId,
        'driver_id' => $driverId,
        'company_id' => $companyId,
        'old_status' => $oldStatus,
        'new_status' => $status,
        'notes' => $notes,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'photo_path' => $photoPath,
        'signature_path' => $signaturePath,
        'created_at' => date('Y-m-d H:i:s')
    ];
    $logQuery = "
        INSERT INTO parcel_status_logs
        (parcel_id, driver_id, company_id, old_status, new_status, notes, latitude, longitude, photo_path, signature_path, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute([
        $parcelId, $driverId, $companyId, $oldStatus, $status, $notes,
        $latitude, $longitude, $photoPath, $signaturePath, $logData['created_at']
    ]);
    if ($status === 'delivered') {
        updateTripProgress($parcel['trip_id'], $companyId);
    }
    if (in_array($status, ['delivered', 'failed_delivery'])) {
        sendCustomerNotification($parcel, $status, $notes);
    }
    $response = [
        'success' => true,
        'message' => 'Delivery status updated successfully',
        'data' => [
            'parcel_id' => $parcelId,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'notes' => $notes,
            'timestamp' => date('c'),
            'location' => ($latitude && $longitude) ? [
                'latitude' => (float)$latitude,
                'longitude' => (float)$longitude
            ] : null,
            'photo_uploaded' => !empty($photoPath),
            'signature_captured' => !empty($signaturePath)
        ]
    ];
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Delivery Status Update Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
function saveDeliveryPhoto($base64Photo, $parcelId, $companyId) {
    try {
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Photo));
        if (!$imageData) {
            throw new Exception('Invalid photo data');
        }
        $uploadDir = "../../uploads/deliveries/{$companyId}/" . date('Y/m/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = "delivery_{$parcelId}_" . time() . '.jpg';
        $filePath = $uploadDir . $filename;
        if (file_put_contents($filePath, $imageData) === false) {
            throw new Exception('Failed to save photo');
        }
        return "uploads/deliveries/{$companyId}/" . date('Y/m/') . $filename;
    } catch (Exception $e) {
        error_log("Photo save error: " . $e->getMessage());
        return null;
    }
}
function saveDeliverySignature($base64Signature, $parcelId, $companyId) {
    try {
        $signatureData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Signature));
        if (!$signatureData) {
            throw new Exception('Invalid signature data');
        }
        $uploadDir = "../../uploads/signatures/{$companyId}/" . date('Y/m/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = "signature_{$parcelId}_" . time() . '.png';
        $filePath = $uploadDir . $filename;
        if (file_put_contents($filePath, $signatureData) === false) {
            throw new Exception('Failed to save signature');
        }
        return "uploads/signatures/{$companyId}/" . date('Y/m/') . $filename;
    } catch (Exception $e) {
        error_log("Signature save error: " . $e->getMessage());
        return null;
    }
}
function updateTripProgress($tripId, $companyId) {
    global $pdo;
    try {
        $parcelsQuery = "
            SELECT COUNT(*) as total_parcels,
                   SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_parcels
            FROM parcels
            WHERE trip_id = ?
        ";
        $stmt = $pdo->prepare($parcelsQuery);
        $stmt->execute([$tripId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['total_parcels'] == $result['delivered_parcels']) {
            $updateTripQuery = "
                UPDATE trips
                SET status = 'completed', completed_at = NOW(), updated_at = NOW()
                WHERE trip_id = ? AND company_id = ?
            ";
            $updateStmt = $pdo->prepare($updateTripQuery);
            $updateStmt->execute([$tripId, $companyId]);
        }
    } catch (Exception $e) {
        error_log("Trip progress update error: " . $e->getMessage());
    }
}
function sendCustomerNotification($parcel, $status, $notes) {
    error_log("Customer notification: Parcel {$parcel['parcel_id']} status changed to {$status}. Notes: {$notes}");
}
