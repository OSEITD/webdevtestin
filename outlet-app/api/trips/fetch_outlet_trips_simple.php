<?php

header("Content-Type: application/json");
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized. Please log in."
    ]);
    exit;
}

$mockTrips = [
    [
        'id' => 'test-trip-001',
        'trip_status' => 'scheduled',
        'departure_time' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        'arrival_time' => date('Y-m-d H:i:s', strtotime('+4 hours')),
        'driver' => [
            'id' => 'driver-001',
            'driver_name' => 'John Doe',
            'driver_phone' => '+260 123 456 789'
        ],
        'vehicle' => [
            'id' => 'vehicle-001',
            'name' => 'Toyota Hiace',
            'plate_number' => 'BAZ 1234'
        ],
        'stops' => [
            [
                'id' => 'stop-001',
                'stop_order' => 1,
                'outlet' => [
                    'id' => $_SESSION['outlet_id'] ?? 'outlet-001',
                    'outlet_name' => 'Main Branch',
                    'location' => 'Lusaka, Zambia'
                ]
            ],
            [
                'id' => 'stop-002',
                'stop_order' => 2,
                'outlet' => [
                    'id' => 'outlet-002',
                    'outlet_name' => 'Branch 2',
                    'location' => 'Kitwe, Zambia'
                ]
            ]
        ],
        'parcels' => [
            [
                'id' => 'parcel-001',
                'track_number' => 'PKG001',
                'status' => 'pending',
                'sender_name' => 'Alice Smith',
                'receiver_name' => 'Bob Johnson',
                'receiver_address' => '123 Main St',
                'receiver_phone' => '+260 987 654 321',
                'parcel_weight' => 2.5,
                'delivery_fee' => 50,
                'origin_outlet_name' => 'Main Branch',
                'destination_outlet_name' => 'Branch 2',
                'parcel_list_status' => 'pending',
                'barcode_url' => null
            ],
            [
                'id' => 'parcel-002',
                'track_number' => 'PKG002',
                'status' => 'pending',
                'sender_name' => 'Charlie Brown',
                'receiver_name' => 'Diana Prince',
                'receiver_address' => '456 Oak Ave',
                'receiver_phone' => '+260 555 123 456',
                'parcel_weight' => 1.2,
                'delivery_fee' => 35,
                'origin_outlet_name' => 'Main Branch',
                'destination_outlet_name' => 'Branch 2',
                'parcel_list_status' => 'assigned',
                'barcode_url' => 'https://example.com/barcode.png'
            ]
        ],
        'total_parcels' => 2,
        'is_origin_outlet' => true,
        'outlet_stop_order' => 1
    ]
];

echo json_encode([
    "success" => true,
    "trips" => $mockTrips,
    "outlet_id" => $_SESSION['outlet_id'] ?? 'test-outlet',
    "total_trips" => count($mockTrips),
    "performance" => [
        "total" => 5,
        "queries_count" => 0,
        "note" => "This is mock data for testing"
    ]
]);
