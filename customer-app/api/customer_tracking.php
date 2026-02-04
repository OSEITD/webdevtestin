<?php
require_once __DIR__ . '/../includes/supabase.php';

header('Content-Type: application/json');

session_start();

$track_number = $_GET['track_number'] ?? $_POST['track_number'] ?? null;

if (!$track_number) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Tracking number is required'
    ]);
    exit;
}

try {
    $supabase = getSupabaseClient();
    
    $parcel_response = $supabase
        ->from('parcels')
        ->select('*')
        ->eq('track_number', $track_number)
        ->execute();
    
    if (!$parcel_response->data || count($parcel_response->data) === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Parcel not found. Please check your tracking number.'
        ]);
        exit;
    }
    
    $parcel = $parcel_response->data[0];
    
    $has_arrived = in_array($parcel['status'], ['at_outlet', 'delivered', 'completed']);
    
    $parcel_list_query = $supabase->from('parcel_list')
        ->select('id, status, trip_id, trip_stop_id, outlet_id')
        ->eq('parcel_id', $parcel['id'])
        ->eq('company_id', $parcel['company_id'])
        ->order('created_at', ['ascending' => false])
        ->limit(1)
        ->execute();
    
    $parcel_list_data = null;
    $current_trip_id = null;
    $is_in_transit = false;
    
    if ($parcel_list_query && isset($parcel_list_query['data']) && count($parcel_list_query['data']) > 0) {
        $parcel_list_data = $parcel_list_query['data'][0];
        $current_trip_id = $parcel_list_data['trip_id'];
        
        $is_in_transit = in_array($parcel_list_data['status'], ['assigned', 'in_transit', 'pending']);
    }
    
    $trip_data = null;
    $estimated_arrival = null;
    
    if ($is_in_transit && $current_trip_id) {
        $trip_query = $supabase->from('trips')
            ->select('*')
            ->eq('id', $current_trip_id)
            ->execute();
        
        if ($trip_query && isset($trip_query->data) && count($trip_query->data) > 0) {
            $trip_data = $trip_query->data[0];
            $estimated_arrival = $trip_data->arrival_time ?? $parcel->estimated_delivery_date ?? null;
        }
    }
    
    $destination_outlet = $parcel['outlets'] ?? null;
    
    $tracking_status = [
        'pending' => [
            'label' => 'Pending Pickup',
            'description' => 'Your parcel is waiting to be picked up by our driver',
            'icon' => 'clock',
            'color' => '#6b7280'
        ],
        'assigned' => [
            'label' => 'Assigned to Driver',
            'description' => 'Your parcel has been assigned to a driver and will be picked up soon',
            'icon' => 'user-check',
            'color' => '#3b82f6'
        ],
        'in_transit' => [
            'label' => 'In Transit',
            'description' => 'Your parcel is on its way to the destination',
            'icon' => 'truck',
            'color' => '#f59e0b'
        ],
        'at_outlet' => [
            'label' => 'Ready for Pickup',
            'description' => 'Your parcel has arrived and is ready for collection',
            'icon' => 'store',
            'color' => '#10b981'
        ],
        'delivered' => [
            'label' => 'Delivered',
            'description' => 'Your parcel has been successfully delivered',
            'icon' => 'check-circle',
            'color' => '#059669'
        ],
        'completed' => [
            'label' => 'Completed',
            'description' => 'Delivery completed',
            'icon' => 'check-double',
            'color' => '#059669'
        ]
    ];
    
    $current_status = $tracking_status[$parcel['status']] ?? $tracking_status['pending'];
    
    $response = [
        'success' => true,
        'parcel' => [
            'track_number' => $parcel['track_number'],
            'status' => $parcel['status'],
            'status_display' => $current_status,
            'receiver_name' => $parcel['receiver_name'],
            'receiver_address' => $parcel['receiver_address'],
            'package_details' => $parcel['package_details'],
            'created_at' => $parcel['created_at'],
            'estimated_delivery_date' => $estimated_arrival ?? $parcel['estimated_delivery_date'],
            'delivered_at' => $parcel['delivered_at'],
            'has_arrived' => $has_arrived,
            'is_in_transit' => $is_in_transit
        ],
        'destination' => null,
        'driver_location' => null,
        'tracking_visible' => false, // Disabled until driver location query is fixed
        'show_driver_info' => false
    ];
    
    if ($destination_outlet) {
        $response['destination'] = [
            'outlet_name' => $destination_outlet['outlet_name'],
            'address' => $destination_outlet['address'],
            'latitude' => $destination_outlet['latitude'],
            'longitude' => $destination_outlet['longitude'],
            'contact_phone' => $destination_outlet['contact_phone']
        ];
    }
    
    if ($is_in_transit && $trip_data) {
        $response['trip'] = [
            'status' => $trip_data['trip_status'],
            'departure_time' => $trip_data['departure_time'],
            'estimated_arrival' => $estimated_arrival,
            'driver_name' => $trip_data['drivers']['driver_name'] ?? 'Driver',
            'driver_phone' => $trip_data['drivers']['driver_phone'] ?? null
        ];
    }
    
    $history_query = $supabase->from('parcel_list')
        ->select('status, updated_at, created_at')
        ->eq('parcel_id', $parcel['id'])
        ->order('created_at', ['ascending' => true])
        ->execute();
    
    if ($history_query && isset($history_query['data'])) {
        $response['history'] = array_map(function($item) use ($tracking_status) {
            return [
                'status' => $item['status'],
                'status_display' => $tracking_status[$item['status']] ?? null,
                'timestamp' => $item['updated_at'] ?? $item['created_at']
            ];
        }, $history_query['data']);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("❌ Error in customer_tracking: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to retrieve tracking information. Please try again later.'
    ]);
}
