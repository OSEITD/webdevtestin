<?php
session_start();
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
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['tracking_data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No tracking data provided']);
    exit();
}
$trackingData = $input['tracking_data'];
if (!isset($trackingData['success']) || !$trackingData['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid tracking data']);
    exit();
}
if (!isset($trackingData['data']) || !isset($trackingData['customer_role'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Incomplete tracking data']);
    exit();
}
$_SESSION['verified_tracking_data'] = [
    'verified_at' => time(),
    'parcel' => $trackingData['data'],
    'customer_role' => $trackingData['customer_role'],
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];
echo json_encode([
    'success' => true,
    'message' => 'Session created successfully',
    'redirect_url' => 'track_details.php'
]);
?>