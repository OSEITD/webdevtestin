<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/supabase-helper.php';

try {
    
    $parcel_id = $_GET['parcel_id'] ?? null;
    
    if (!$parcel_id) {
        throw new Exception('Parcel ID is required');
    }
    
    
    if (!is_numeric($parcel_id)) {
        throw new Exception('Invalid parcel ID format');
    }
    
    
    $supabase = new SupabaseHelper();
    
    
    $parcel_response = $supabase->get('parcels', "id=eq.$parcel_id");
    
    if (empty($parcel_response) || !isset($parcel_response[0])) {
        throw new Exception('Parcel not found');
    }
    
    $parcel = (object) $parcel_response[0];
    
    
    $outlet_data = null;
    if ($parcel->destination_outlet_id) {
        $outlet_response = $supabase->get('outlets', "id=eq.{$parcel->destination_outlet_id}&select=id,outlet_name,address,latitude,longitude");
        
        if (!empty($outlet_response) && isset($outlet_response[0])) {
            $outlet_data = (object) $outlet_response[0];
        }
    }
    
    
    $driver_data = null;
    if ($parcel->driver_id) {
        $driver_response = $supabase->get('drivers', "id=eq.{$parcel->driver_id}&select=id,first_name,last_name,phone_number");
        
        if (!empty($driver_response) && isset($driver_response[0])) {
            $driver_data = (object) $driver_response[0];
        }
    }
    
    
    $formatted_parcel = [
        'id' => $parcel->id,
        'tracking_number' => $parcel->tracking_number,
        'status' => $parcel->status,
        'type' => $parcel->type,
        'weight' => $parcel->weight,
        'total_amount' => $parcel->total_amount,
        'sender_name' => $parcel->sender_name,
        'sender_contact' => $parcel->sender_contact,
        'sender_address' => $parcel->sender_address,
        'receiver_name' => $parcel->receiver_name,
        'receiver_contact' => $parcel->receiver_contact,
        'receiver_address' => $parcel->receiver_address,
        'receiver_latitude' => $parcel->receiver_latitude,
        'receiver_longitude' => $parcel->receiver_longitude,
        'special_instructions' => $parcel->special_instructions,
        'created_at' => $parcel->created_at,
        'updated_at' => $parcel->updated_at,
        'barcode_url' => $parcel->barcode_url,
        
        
        'destination_outlet_id' => $parcel->destination_outlet_id,
        'destination_outlet_name' => $outlet_data->outlet_name ?? null,
        'destination_outlet_address' => $outlet_data->address ?? null,
        'destination_outlet_latitude' => $outlet_data->latitude ?? null,
        'destination_outlet_longitude' => $outlet_data->longitude ?? null,
        
        
        'driver_id' => $parcel->driver_id,
        'driver_name' => $driver_data ? 
            trim($driver_data->first_name . ' ' . $driver_data->last_name) : null,
        'driver_phone' => $driver_data->phone_number ?? null
    ];
    
    
    error_log("✅ Enhanced parcel details fetched successfully for ID: " . $parcel_id);
    
    echo json_encode([
        'success' => true,
        'parcel' => $formatted_parcel
    ]);
    
} catch (Exception $e) {
    error_log("❌ Error fetching enhanced parcel details: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
