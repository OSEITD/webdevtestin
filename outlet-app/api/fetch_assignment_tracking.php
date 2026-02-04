<?php
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

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
    
    $trips = $supabase->get('trips', 'vehicle_id=not.is.null&trip_status=in.(scheduled,in_transit)', 
        'id,vehicle_id,trip_status,departure_time,arrival_time,created_at,updated_at');
    
    if (!is_array($trips)) {
        $trips = [];
    }
    
    $formatted_assignments = [];
    
    foreach ($trips as $trip) {
        $trip_id = $trip['id'];
        $vehicle_id = $trip['vehicle_id'];
        
        $vehicle = $supabase->get('vehicle', "id=eq.$vehicle_id", 'id,name,plate_number,status');
        $vehicle_data = $vehicle[0] ?? null;
        
        $parcel_assignments = $supabase->get('parcel_list', "trip_id=eq.$trip_id", 
            'id,parcel_id,status,created_at,updated_at,outlet_id');
        
        if (!is_array($parcel_assignments)) {
            continue;
        }
        
        foreach ($parcel_assignments as $assignment) {
            $parcel_id = $assignment['parcel_id'];
            
            $parcel = $supabase->get('parcels', "id=eq.$parcel_id", 
                'id,track_number,status,sender_name,receiver_name,receiver_address,receiver_phone,parcel_weight,delivery_option,parcel_value,special_instructions,estimated_delivery_date,created_at,updated_at');
            
            if (!$parcel || empty($parcel)) {
                continue;
            }
            
            $parcel_data = $parcel[0];
            
            $outlet = $supabase->get('outlets', "id=eq.{$assignment['outlet_id']}", 'id,outlet_name,address');
            $outlet_data = $outlet[0] ?? null;
            
            $formatted_assignment = [
                'id' => $assignment['id'],
                'trip_id' => $trip_id,
                'parcel_id' => $parcel_id,
                'track_number' => $parcel_data['track_number'] ?? 'N/A',
                'status' => $assignment['status'] ?? $parcel_data['status'] ?? 'unknown',
                'trip_status' => $trip['trip_status'] ?? 'unknown',
                'assigned_at' => $assignment['created_at'] ?? $trip['created_at'],
                'updated_at' => $assignment['updated_at'] ?? $trip['updated_at'],
                
                'departure_time' => $trip['departure_time'],
                'arrival_time' => $trip['arrival_time'],
                
                'sender_name' => $parcel_data['sender_name'] ?? 'Unknown Sender',
                'recipient_name' => $parcel_data['receiver_name'] ?? 'Unknown Recipient',
                'recipient_phone' => $parcel_data['receiver_phone'] ?? null,
                'weight' => $parcel_data['parcel_weight'] ?? null,
                'delivery_address' => $parcel_data['receiver_address'] ?? 'Unknown Address',
                'parcel_type' => $parcel_data['delivery_option'] ?? 'Standard',
                'parcel_value' => $parcel_data['parcel_value'] ?? null,
                'special_instructions' => $parcel_data['special_instructions'] ?? null,
                'estimated_delivery_date' => $parcel_data['estimated_delivery_date'] ?? null,
                
                'vehicle_id' => $vehicle_id,
                'vehicle_name' => $vehicle_data['name'] ?? 'Unknown Vehicle',
                'vehicle_plate' => $vehicle_data['plate_number'] ?? 'No Plate',
                'vehicle_status' => $vehicle_data['status'] ?? 'unknown',
                
                'outlet_id' => $assignment['outlet_id'],
                'outlet_name' => $outlet_data['outlet_name'] ?? 'Unknown Outlet',
                'outlet_address' => $outlet_data['address'] ?? null,
                
                'driver_name' => $vehicle_data['name'] ?? 'Vehicle Assignment',
                'driver_phone' => null,
                'vehicle_number' => $vehicle_data['plate_number'] ?? 'No Plate'
            ];
            
            $formatted_assignments[] = $formatted_assignment;
        }
    }
    
    usort($formatted_assignments, function($a, $b) {
        return strtotime($b['assigned_at']) - strtotime($a['assigned_at']);
    });
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'assignments' => $formatted_assignments,
        'total_count' => count($formatted_assignments),
        'company_id' => $company_id
    ]);
    
} catch (Exception $e) {
    error_log("❌ Error fetching assignment tracking data: " . $e->getMessage());
    
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'assignments' => [],
        'total_count' => 0
    ]);
}
?>
