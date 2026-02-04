<?php
session_start();
require_once '../../includes/MultiTenantSupabaseHelper.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

try {
    $supabase = new MultiTenantSupabaseHelper($_SESSION['company_id']);
    
    
    $destinationOutlet = $_GET['destination_outlet'] ?? null;
    
    
    $query = 'status=eq.pending';
    
    
    if ($destinationOutlet) {
        $query .= "&destination_outlet_id=eq.$destinationOutlet";
    }
    
    
    $parcels = $supabase->get('parcels', $query, 
        'id,track_number,sender_name,sender_phone,receiver_name,receiver_phone,' .
        'parcel_weight,parcel_value,destination_outlet_id,package_details,delivery_option,' .
        'declared_value,special_instructions,created_at'
    );
    
    if ($parcels === false || $parcels === null) {
        $parcels = [];
    }
    
    
    if (!empty($parcels)) {
        foreach ($parcels as &$parcel) {
            if ($parcel['destination_outlet_id']) {
                $outlet = $supabase->get('outlets', "id=eq.{$parcel['destination_outlet_id']}", 'outlet_name');
                $parcel['destination_outlet_name'] = !empty($outlet) ? $outlet[0]['outlet_name'] : 'Unknown';
            }
        }
    }
    
    echo json_encode([
        "success" => true,
        "parcels" => $parcels,
        "count" => count($parcels)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
