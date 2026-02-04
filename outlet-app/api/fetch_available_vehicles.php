<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/MultiTenantSupabaseHelper.php';

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 'test-user';
    }
    if (!isset($_SESSION['company_id'])) {
        $_SESSION['company_id'] = 'O-100';
    }
    
    $user_id = $_SESSION['user_id'];
    $company_id = $_SESSION['company_id'];
    
    $supabase = new MultiTenantSupabaseHelper($company_id);
    
    $vehicles = $supabase->get('vehicle', '', 'id,name,plate_number,status');
    
    if (!is_array($vehicles)) {
        $vehicles = [];
    }
    
    $formatted_vehicles = [];
    foreach ($vehicles as $vehicle) {
        $formatted_vehicles[] = [
            'id' => $vehicle['id'],
            'name' => $vehicle['name'] ?? 'Unknown Vehicle',
            'plate_number' => $vehicle['plate_number'] ?? 'No Plate',
            'status' => $vehicle['status'] ?? 'unknown',
            'display_name' => ($vehicle['name'] ?? 'Vehicle') . ' (' . ($vehicle['plate_number'] ?? 'No Plate') . ')'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'vehicles' => $formatted_vehicles,
        'total_count' => count($formatted_vehicles)
    ]);
    
} catch (Exception $e) {
    error_log("❌ Error fetching vehicles: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'vehicles' => [],
        'total_count' => 0
    ]);
}
?>
