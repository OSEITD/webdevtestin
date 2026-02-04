<?php

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required'
    ]);
    exit;
}

$startTime = microtime(true);

$minimalData = [
    'parcels' => [
        'total' => 'Loading...',
        'pending' => '...',
        'in_transit' => '...',
        'delivered' => '...'
    ],
    'trips' => [
        'total' => 'Loading...',
        'scheduled' => '...',
        'in_progress' => '...',
        'completed' => '...'
    ],
    'vehicles' => [
        'total' => 'Loading...',
        'available' => '...',
        'out_for_delivery' => '...'
    ],
    'revenue' => [
        'today' => 'Loading...',
        'week' => '...',
        'month' => '...'
    ]
];

$executionTime = round((microtime(true) - $startTime) * 1000, 2);

echo json_encode([
    'success' => true,
    'data' => $minimalData,
    'timestamp' => date('Y-m-d H:i:s'),
    'cached' => false,
    'debug' => [
        'api_version' => 'emergency_minimal',
        'company_id' => $_SESSION['company_id'],
        'total_execution_time' => $executionTime . 'ms',
        'message' => 'Emergency fallback - real data will load in background'
    ]
]);
?>