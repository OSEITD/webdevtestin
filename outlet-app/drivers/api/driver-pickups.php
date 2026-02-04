<?php
session_start();
require_once '../../config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    $driverId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'];
    
    $status = $_GET['status'] ?? 'all';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $whereConditions = ["t.company_id = ?", "t.driver_id = ?"];
    $params = [$companyId, $driverId];
    
    if ($status !== 'all') {
        $whereConditions[] = "p.status = ?";
        $params[] = $status;
    }
    
    $query = "
        SELECT 
            p.parcel_id,
            p.tracking_number,
            p.status,
            p.priority,
            p.weight,
            p.description,
            p.pickup_address,
            p.pickup_latitude,
            p.pickup_longitude,
            p.delivery_address,
            p.delivery_latitude,
            p.delivery_longitude,
            p.sender_name,
            p.sender_phone,
            p.sender_email,
            p.recipient_name,
            p.recipient_phone,
            p.recipient_email,
            p.created_at,
            p.updated_at,
            t.trip_id,
            t.trip_code,
            t.status as trip_status,
            t.scheduled_pickup_time,
            t.scheduled_delivery_time,
            pickup_outlet.outlet_name as pickup_outlet_name,
            delivery_outlet.outlet_name as delivery_outlet_name
        FROM parcels p
        INNER JOIN trips t ON p.trip_id = t.trip_id
        LEFT JOIN outlets pickup_outlet ON t.origin_outlet_id = pickup_outlet.outlet_id
        LEFT JOIN outlets delivery_outlet ON t.destination_outlet_id = delivery_outlet.outlet_id
        WHERE " . implode(' AND ', $whereConditions) . "
        ORDER BY 
            CASE p.priority 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'low' THEN 3 
                ELSE 4 
            END,
            p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $pickups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $countQuery = "
        SELECT COUNT(*) as total
        FROM parcels p
        INNER JOIN trips t ON p.trip_id = t.trip_id
        WHERE " . implode(' AND ', array_slice($whereConditions, 0, -1)) . "
    ";
    
    $countParams = array_slice($params, 0, -2);
    if ($status !== 'all') {
        $countParams[] = $status;
    }
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalCount = $countStmt->fetchColumn();
    
    $formattedPickups = array_map(function($pickup) {
        return [
            'parcel_id' => $pickup['parcel_id'],
            'tracking_number' => $pickup['tracking_number'],
            'status' => $pickup['status'],
            'priority' => $pickup['priority'] ?: 'normal',
            'weight' => $pickup['weight'] ? floatval($pickup['weight']) : null,
            'description' => $pickup['description'],
            'pickup_address' => $pickup['pickup_address'],
            'pickup_latitude' => $pickup['pickup_latitude'] ? floatval($pickup['pickup_latitude']) : null,
            'pickup_longitude' => $pickup['pickup_longitude'] ? floatval($pickup['pickup_longitude']) : null,
            'delivery_address' => $pickup['delivery_address'],
            'delivery_latitude' => $pickup['delivery_latitude'] ? floatval($pickup['delivery_latitude']) : null,
            'delivery_longitude' => $pickup['delivery_longitude'] ? floatval($pickup['delivery_longitude']) : null,
            'sender_name' => $pickup['sender_name'],
            'sender_phone' => $pickup['sender_phone'],
            'sender_email' => $pickup['sender_email'],
            'recipient_name' => $pickup['recipient_name'],
            'recipient_phone' => $pickup['recipient_phone'],
            'recipient_email' => $pickup['recipient_email'],
            'created_at' => $pickup['created_at'],
            'updated_at' => $pickup['updated_at'],
            'trip' => [
                'trip_id' => $pickup['trip_id'],
                'trip_code' => $pickup['trip_code'],
                'status' => $pickup['trip_status'],
                'scheduled_pickup_time' => $pickup['scheduled_pickup_time'],
                'scheduled_delivery_time' => $pickup['scheduled_delivery_time'],
                'pickup_outlet_name' => $pickup['pickup_outlet_name'],
                'delivery_outlet_name' => $pickup['delivery_outlet_name']
            ]
        ];
    }, $pickups);
    
    $statusSummaryQuery = "
        SELECT 
            p.status,
            COUNT(*) as count
        FROM parcels p
        INNER JOIN trips t ON p.trip_id = t.trip_id
        WHERE t.company_id = ? AND t.driver_id = ?
        GROUP BY p.status
    ";
    
    $statusStmt = $pdo->prepare($statusSummaryQuery);
    $statusStmt->execute([$companyId, $driverId]);
    $statusCounts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $response = [
        'success' => true,
        'data' => $formattedPickups,
        'pagination' => [
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ],
        'summary' => [
            'total_pickups' => (int)$totalCount,
            'status_counts' => $statusCounts,
            'filter' => $status
        ],
        'multitenancy' => [
            'company_id' => $companyId,
            'driver_id' => $driverId,
            'data_isolation' => 'enforced'
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Driver Pickups API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load pickups',
        'debug' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>
