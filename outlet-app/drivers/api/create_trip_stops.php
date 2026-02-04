<?php
/**
 * Create Trip Stops API
 * Creates trip_stops records for trips that don't have them
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../../includes/OutletAwareSupabaseHelper.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['stops']) || !is_array($input['stops']) || empty($input['stops'])) {
        throw new Exception('Invalid stops data');
    }
    
    $supabase = new OutletAwareSupabaseHelper();
    $companyId = $_SESSION['company_id'];
    
    // Validate and add company_id to each stop
    $stopsToCreate = [];
    foreach ($input['stops'] as $stop) {
        if (!isset($stop['trip_id']) || !isset($stop['outlet_id']) || !isset($stop['stop_order'])) {
            throw new Exception('Missing required stop fields');
        }
        
        $stopsToCreate[] = [
            'trip_id' => $stop['trip_id'],
            'outlet_id' => $stop['outlet_id'],
            'stop_order' => $stop['stop_order'],
            'company_id' => $companyId,
            'arrival_time' => $stop['arrival_time'] ?? null,
            'departure_time' => $stop['departure_time'] ?? null
        ];
    }
    
    // Create stops in database
    $createdStops = $supabase->post('trip_stops', $stopsToCreate);
    
    if (!is_array($createdStops)) {
        $createdStops = [$createdStops];
    }
    
    echo json_encode([
        'success' => true,
        'stops' => $createdStops,
        'count' => count($createdStops)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
