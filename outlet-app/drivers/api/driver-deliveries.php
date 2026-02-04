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
    
    if ($status === 'all') {
        $whereConditions[] = "p.status IN ('picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed_delivery')";
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
            p.delivered_at,
            p.delivered_by,
            p.delivery_photo,
            p.signature,
            p.created_at,
            p.updated_at,
            t.trip_id,
            t.trip_code,
            t.status as trip_status,
            t.scheduled_pickup_time,
            t.scheduled_delivery_time,
            pickup_outlet.outlet_name as pickup_outlet_name,
            delivery_outlet.outlet_name as delivery_outlet_name,
            profiles.full_name as driver_name
        FROM parcels p
        INNER JOIN trips t ON p.trip_id = t.trip_id
        LEFT JOIN outlets pickup_outlet ON t.origin_outlet_id = pickup_outlet.outlet_id
        LEFT JOIN outlets delivery_outlet ON t.destination_outlet_id = delivery_outlet.outlet_id
        LEFT JOIN profiles ON p.delivered_by = profiles.profile_id
        WHERE " . implode(' AND ', $whereConditions) . "
        ORDER BY 
            CASE p.status 
                WHEN 'out_for_delivery' THEN 1
                WHEN 'in_transit' THEN 2
                WHEN 'picked_up' THEN 3
                WHEN 'delivered' THEN 4
                WHEN 'failed_delivery' THEN 5
                ELSE 6
            END,
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
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $countConditions = array_slice($whereConditions, 0, count($whereConditions) - (($status === 'all') ? 1 : 0));
    $countQuery = "
        SELECT COUNT(*) as total
        FROM parcels p
        INNER JOIN trips t ON p.trip_id = t.trip_id
        WHERE " . implode(' AND ', $countConditions);
    
    if ($status !== 'all') {
        $countQuery .= " AND p.status = ?";
    } else {
        $countQuery .= " AND p.status IN ('picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed_delivery')";
    }
    
    $countParams = array_slice($params, 0, -2);
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalCount = $countStmt->fetchColumn();
    
    $formattedDeliveries = array_map(function($delivery) {
        $distance = calculateDistance($delivery);
        $eta = calculateETA($delivery);
        
        return [
            'parcel_id' => $delivery['parcel_id'],
            'tracking_number' => $delivery['tracking_number'],
            'status' => $delivery['status'],
            'priority' => $delivery['priority'] ?: 'normal',
            'weight' => $delivery['weight'] ? floatval($delivery['weight']) : null,
            'description' => $delivery['description'],
            'pickup_address' => $delivery['pickup_address'],
            'pickup_latitude' => $delivery['pickup_latitude'] ? floatval($delivery['pickup_latitude']) : null,
            'pickup_longitude' => $delivery['pickup_longitude'] ? floatval($delivery['pickup_longitude']) : null,
            'delivery_address' => $delivery['delivery_address'],
            'delivery_latitude' => $delivery['delivery_latitude'] ? floatval($delivery['delivery_latitude']) : null,
            'delivery_longitude' => $delivery['delivery_longitude'] ? floatval($delivery['delivery_longitude']) : null,
            'sender_name' => $delivery['sender_name'],
            'sender_phone' => $delivery['sender_phone'],
            'sender_email' => $delivery['sender_email'],
            'recipient_name' => $delivery['recipient_name'],
            'recipient_phone' => $delivery['recipient_phone'],
            'recipient_email' => $delivery['recipient_email'],
            'delivered_at' => $delivery['delivered_at'],
            'delivered_by' => $delivery['delivered_by'],
            'driver_name' => $delivery['driver_name'],
            'delivery_photo' => $delivery['delivery_photo'],
            'signature' => $delivery['signature'],
            'created_at' => $delivery['created_at'],
            'updated_at' => $delivery['updated_at'],
            'estimated_distance' => $distance,
            'estimated_eta' => $eta,
            'trip' => [
                'trip_id' => $delivery['trip_id'],
                'trip_code' => $delivery['trip_code'],
                'status' => $delivery['trip_status'],
                'scheduled_pickup_time' => $delivery['scheduled_pickup_time'],
                'scheduled_delivery_time' => $delivery['scheduled_delivery_time'],
                'pickup_outlet_name' => $delivery['pickup_outlet_name'],
                'delivery_outlet_name' => $delivery['delivery_outlet_name']
            ]
        ];
    }, $deliveries);
    
    $statusSummaryQuery = "
        SELECT 
            p.status,
            COUNT(*) as count
        FROM parcels p
        INNER JOIN trips t ON p.trip_id = t.trip_id
        WHERE t.company_id = ? AND t.driver_id = ? 
        AND p.status IN ('picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed_delivery')
        GROUP BY p.status
    ";
    
    $statusStmt = $pdo->prepare($statusSummaryQuery);
    $statusStmt->execute([$companyId, $driverId]);
    $statusCounts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $metricsQuery = "
        SELECT 
            COUNT(*) as total_deliveries,
            SUM(CASE WHEN p.status = 'delivered' THEN 1 ELSE 0 END) as completed_deliveries,
            SUM(CASE WHEN p.status = 'failed_delivery' THEN 1 ELSE 0 END) as failed_deliveries,
            SUM(CASE WHEN DATE(p.updated_at) = CURDATE() THEN 1 ELSE 0 END) as today_deliveries
        FROM parcels p
        INNER JOIN trips t ON p.trip_id = t.trip_id
        WHERE t.company_id = ? AND t.driver_id = ?
        AND p.status IN ('picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed_delivery')
    ";
    
    $metricsStmt = $pdo->prepare($metricsQuery);
    $metricsStmt->execute([$companyId, $driverId]);
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'data' => $formattedDeliveries,
        'pagination' => [
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ],
        'summary' => [
            'total_deliveries' => (int)$totalCount,
            'status_counts' => $statusCounts,
            'filter' => $status
        ],
        'metrics' => [
            'total_deliveries' => (int)$metrics['total_deliveries'],
            'completed_deliveries' => (int)$metrics['completed_deliveries'],
            'failed_deliveries' => (int)$metrics['failed_deliveries'],
            'today_deliveries' => (int)$metrics['today_deliveries'],
            'success_rate' => $metrics['total_deliveries'] > 0 ? 
                round(($metrics['completed_deliveries'] / $metrics['total_deliveries']) * 100, 1) : 0
        ],
        'multitenancy' => [
            'company_id' => $companyId,
            'driver_id' => $driverId,
            'data_isolation' => 'enforced'
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Driver Deliveries API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load deliveries',
        'debug' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
function calculateDistance($delivery) {
    if ($delivery['delivery_latitude'] && $delivery['delivery_longitude']) {
        return round(rand(5, 50) / 10, 1) . ' km';
    }
    return 'N/A';
}
function calculateETA($delivery) {
    if ($delivery['delivery_latitude'] && $delivery['delivery_longitude']) {
        return rand(5, 45) . ' min';
    }
    return 'N/A';
}
