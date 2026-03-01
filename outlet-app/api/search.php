<?php

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../includes/OutletAwareSupabaseHelper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
$role = $_SESSION['role'] ?? '';

$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'all'; 

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$supabase = new OutletAwareSupabaseHelper();
$results = [
    'parcels' => [],
    'customers' => [],
    'notifications' => [],
    'trips' => [],
    'drivers' => []
];

try {
    
    if ($type === 'all' || $type === 'parcels') {
        $parcelResults = $supabase->get('parcels', 
            'company_id=eq.' . urlencode($companyId) . 
            '&or=(track_number.ilike.*' . urlencode($query) . '*,' .
            'sender_name.ilike.*' . urlencode($query) . '*,' .
            'receiver_name.ilike.*' . urlencode($query) . '*,' .
            'sender_phone.ilike.*' . urlencode($query) . '*,' .
            'receiver_phone.ilike.*' . urlencode($query) . '*,' .
            'receiver_address.ilike.*' . urlencode($query) . '*)' .
            '&select=id,track_number,sender_name,receiver_name,status,created_at,delivery_date' .
            '&order=created_at.desc&limit=10'
        );
        
        foreach ($parcelResults as $parcel) {
            $results['parcels'][] = [
                'id'       => $parcel['id'],
                'type'     => 'parcel',
                'title'    => 'Parcel #' . $parcel['track_number'],
                'subtitle' => ($parcel['sender_name'] ?? '?') . ' → ' . ($parcel['receiver_name'] ?? '?'),
                'status'   => $parcel['status'],
                'date'     => $parcel['created_at'],
                'url'      => '../pages/parcel_management.php?parcel_id=' . urlencode($parcel['id']),
                'icon'     => 'fa-box'
            ];
        }
    }

    
    if ($type === 'all' || $type === 'customers') {
        $customerResults = $supabase->get('global_customers', 
            'or=(full_name.ilike.*' . urlencode($query) . '*,' .
            'phone.ilike.*' . urlencode($query) . '*,' .
            'email.ilike.*' . urlencode($query) . '*,' .
            'nrc.ilike.*' . urlencode($query) . '*)' .
            '&select=id,full_name,phone,email,nrc,address' .
            '&order=created_at.desc&limit=10'
        );
        
        foreach ($customerResults as $customer) {
            $results['customers'][] = [
                'id' => $customer['id'],
                'type' => 'customer',
                'title' => $customer['full_name'],
                'subtitle' => $customer['phone'] ?? $customer['email'] ?? 'No contact',
                'info' => $customer['nrc'] ? 'NRC: ' . $customer['nrc'] : '',
                'url' => '../pages/business_customers.php?id=' . $customer['id'],
                'icon' => 'fa-user'
            ];
        }
    }

    
    if ($type === 'all' || $type === 'notifications') {
        $notifResults = $supabase->get('notifications', 
            'company_id=eq.' . urlencode($companyId) . 
            '&recipient_id=eq.' . urlencode($userId) .
            '&or=(title.ilike.*' . urlencode($query) . '*,' .
            'message.ilike.*' . urlencode($query) . '*)' .
            '&select=id,title,message,notification_type,priority,status,created_at' .
            '&order=created_at.desc&limit=10'
        );
        
        foreach ($notifResults as $notif) {
            $results['notifications'][] = [
                'id' => $notif['id'],
                'type' => 'notification',
                'title' => $notif['title'],
                'subtitle' => substr($notif['message'], 0, 100) . '...',
                'status' => $notif['status'],
                'priority' => $notif['priority'],
                'date' => $notif['created_at'],
                'url' => '../pages/notifications.php?id=' . $notif['id'],
                'icon' => 'fa-bell'
            ];
        }
    }

    if ($type === 'all' || $type === 'trips') {
        $outletId = $_SESSION['outlet_id'] ?? '';

        // Build role-aware base filter
        $tripFilter = 'company_id=eq.' . urlencode($companyId);
        if ($role === 'driver') {
            $tripFilter .= '&driver_id=eq.' . urlencode($userId);
        } elseif ($role === 'outlet_manager') {
            $conditions = 'outlet_manager_id.eq.' . urlencode($userId);
            if ($outletId) {
                $conditions .= ',origin_outlet_id.eq.' . urlencode($outletId);
                $conditions .= ',destination_outlet_id.eq.' . urlencode($outletId);
            }
            $tripFilter .= '&or=(' . $conditions . ')';
        }
        // admin / company_admin / super_admin: no extra scope, search all company trips

        // Query filter: UUID prefix -> match by ID, otherwise match by status keyword
        $isUuidLike = (bool) preg_match('/^[0-9a-f\-]{2,}$/i', $query);
        if ($isUuidLike) {
            $tripFilter .= '&id=ilike.' . urlencode($query) . '*';
        } else {
            $tripFilter .= '&trip_status=ilike.*' . urlencode($query) . '*';
        }

        $tripResults = $supabase->get('trips',
            $tripFilter .
            '&select=id,trip_status,departure_time,trip_date,created_at,origin_outlet_id,destination_outlet_id,driver_id' .
            '&order=created_at.desc&limit=10'
        );

        foreach ($tripResults ?? [] as $trip) {
            $shortId  = strtoupper(substr($trip['id'], 0, 8));
            $status   = str_replace('_', ' ', ucfirst($trip['trip_status'] ?? 'unknown'));
            $dateStr  = $trip['trip_date']
                ? date('M j, Y', strtotime($trip['trip_date']))
                : ($trip['departure_time'] ? date('M j, Y', strtotime($trip['departure_time'])) : 'No date');

            $results['trips'][] = [
                'id'       => $trip['id'],
                'type'     => 'trip',
                'title'    => 'Trip #' . $shortId,
                'subtitle' => $status . ' · ' . $dateStr,
                'status'   => $trip['trip_status'],
                'date'     => $trip['created_at'],
                'url'      => '../pages/manager_trips.php?trip_id=' . $trip['id'],
                'icon'     => 'fa-route'
            ];
        }
    }

    
    if ($type === 'all' || $type === 'drivers') {
        $driverResults = $supabase->get('drivers', 
            'company_id=eq.' . urlencode($companyId) .
            '&or=(driver_name.ilike.*' . urlencode($query) . '*,' .
            'driver_email.ilike.*' . urlencode($query) . '*,' .
            'driver_phone.ilike.*' . urlencode($query) . '*)' .
            '&select=id,driver_name,driver_email,driver_phone,status' .
            '&limit=10'
        );
        
        foreach ($driverResults as $driver) {
            $results['drivers'][] = [
                'id' => $driver['id'],
                'type' => 'driver',
                'title' => $driver['driver_name'],
                'subtitle' => $driver['driver_email'] ?? $driver['driver_phone'],
                'status' => $driver['status'],
                'url' => '#driver-' . $driver['id'],
                'icon' => 'fa-user-tie'
            ];
        }
    }

    
    $totalResults = count($results['parcels']) + 
                    count($results['customers']) + 
                    count($results['notifications']) + 
                    count($results['trips']) +
                    count($results['drivers']);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'total' => $totalResults,
        'results' => $results
    ]);

} catch (Exception $e) {
    error_log("Search API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed',
        'message' => $e->getMessage()
    ]);
}
