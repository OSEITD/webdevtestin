
<?php
session_start();
require_once '../../includes/OutletAwareSupabaseHelper.php';
header('Content-Type: application/json');

$trip_id = $_GET['trip_id'] ?? '';
if (!$trip_id) {
    echo json_encode(['success' => false, 'error' => 'Missing trip_id']);
    exit;
}

$supabase = new OutletAwareSupabaseHelper();

try {
    
    $tripRows = $supabase->get('trips', 'id=eq.' . urlencode($trip_id) . '&select=id,origin_outlet_id,destination_outlet_id,trip_status,departure_time,vehicle_id,outlet_manager_id,company_id');
    if (!$tripRows || !isset($tripRows[0])) {
        echo json_encode(['success' => false, 'error' => 'Trip not found']);
        exit;
    }
    $trip = $tripRows[0];
    $company_id = $trip['company_id'] ?? null;

    
    $vehicle_name = '-';
    $plate_number = '-';
    if (!empty($trip['vehicle_id'])) {
        $vehicleRows = $supabase->get('vehicle', 'id=eq.' . urlencode($trip['vehicle_id']) . '&select=name,plate_number');
        if ($vehicleRows && isset($vehicleRows[0])) {
            $vehicle_name = $vehicleRows[0]['name'] ?? '-';
            $plate_number = $vehicleRows[0]['plate_number'] ?? '-';
        }
    }

    
    $manager_name = '-';
    $manager_phone = '-';
    if (!empty($trip['outlet_manager_id'])) {
        $managerRows = $supabase->get('profiles', 'id=eq.' . urlencode($trip['outlet_manager_id']) . '&select=full_name,phone');
        if ($managerRows && isset($managerRows[0])) {
            $manager_name = $managerRows[0]['full_name'] ?? '-';
            $manager_phone = $managerRows[0]['phone'] ?? '-';
        }
    }

    
    $route = '-';
    $origin_name = '-';
    $destination_name = '-';
    $total_stops = 0;
    
    $stopsRows = $supabase->get('trip_stops', 'trip_id=eq.' . urlencode($trip_id) . '&order=stop_order.asc&select=outlet_id,stop_order');
    
    if ($stopsRows && !empty($stopsRows)) {
        $total_stops = count($stopsRows);
        
        
        $outletIds = array_column($stopsRows, 'outlet_id');
        
        if (!empty($outletIds)) {
            
            $outletIdsStr = implode(',', array_map('urlencode', $outletIds));
            $outletsRows = $supabase->get('outlets', 'id=in.(' . $outletIdsStr . ')&select=id,outlet_name');
            
            if ($outletsRows) {
                
                $outletMap = [];
                foreach ($outletsRows as $outlet) {
                    $outletMap[$outlet['id']] = $outlet['outlet_name'];
                }
                
                
                $routeStops = [];
                foreach ($stopsRows as $stop) {
                    if (isset($outletMap[$stop['outlet_id']])) {
                        $routeStops[] = $outletMap[$stop['outlet_id']];
                    }
                }
                
                if (!empty($routeStops)) {
                    $route = implode(' → ', $routeStops);
                    $origin_name = $routeStops[0];
                    $destination_name = end($routeStops);
                }
            }
        }
    } else {
        
        if (!empty($trip['origin_outlet_id'])) {
            $originRows = $supabase->get('outlets', 'id=eq.' . urlencode($trip['origin_outlet_id']) . '&select=outlet_name');
            if ($originRows && isset($originRows[0]['outlet_name'])) {
                $origin_name = $originRows[0]['outlet_name'];
            }
        }
        if (!empty($trip['destination_outlet_id'])) {
            $destRows = $supabase->get('outlets', 'id=eq.' . urlencode($trip['destination_outlet_id']) . '&select=outlet_name');
            if ($destRows && isset($destRows[0]['outlet_name'])) {
                $destination_name = $destRows[0]['outlet_name'];
            }
        }
        
        
        if ($origin_name !== '-' && $destination_name !== '-') {
            $route = $origin_name . ' → ' . $destination_name;
            $total_stops = 2;
        }
    }

    $response = [
        'success' => true,
        'trip' => [
            'vehicle_name' => $vehicle_name,
            'plate_number' => $plate_number,
            'manager_name' => $manager_name,
            'manager_phone' => $manager_phone,
            'route' => $route,
            'origin' => $origin_name,
            'destination' => $destination_name,
            'status' => $trip['trip_status'] ?? '-',
            'departure' => $trip['departure_time'] ?? '-',
            'total_stops' => $total_stops,
        ]
    ];
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
