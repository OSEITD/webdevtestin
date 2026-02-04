<?php

ob_start();
header('Content-Type: application/json');

require_once '../../includes/auth_guard.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['outlet_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$outletId = $_SESSION['outlet_id'];
$companyId = $_SESSION['company_id'];

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

try {
    $stats = [
        'total' => 0,
        'pending' => 0,
        'at_outlet' => 0,
        'assigned' => 0,
        'in_transit' => 0,
        'delivered' => 0,
        'cancelled' => 0,
        'ready_for_dispatch' => 0
    ];
    
    
    $queryUrl = "$supabaseUrl/rest/v1/parcels?company_id=eq.$companyId&or=(origin_outlet_id.eq.$outletId,destination_outlet_id.eq.$outletId)&select=status";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json"
            ]
        ]
    ]);
    
    $response = @file_get_contents($queryUrl, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to fetch parcel statistics');
    }
    
    $parcels = json_decode($response, true);
    
    if (!is_array($parcels)) {
        throw new Exception('Invalid response format');
    }
    
    
    $stats['total'] = count($parcels);
    
    foreach ($parcels as $parcel) {
        $status = strtolower($parcel['status'] ?? 'pending');
        
        switch ($status) {
            case 'pending':
                $stats['pending']++;
                break;
            case 'at_outlet':
                $stats['at_outlet']++;
                break;
            case 'assigned':
                $stats['assigned']++;
                break;
            case 'in_transit':
            case 'in transit':
                $stats['in_transit']++;
                break;
            case 'delivered':
                $stats['delivered']++;
                break;
            case 'cancelled':
                $stats['cancelled']++;
                break;
            case 'ready_for_dispatch':
                $stats['ready_for_dispatch']++;
                break;
            default:
                
                $stats['pending']++;
                break;
        }
    }
    
    
    $listStatsUrl = "$supabaseUrl/rest/v1/parcel_list?outlet_id=eq.$outletId&select=status";
    
    $listResponse = @file_get_contents($listStatsUrl, false, $context);
    
    if ($listResponse !== false) {
        $listParcels = json_decode($listResponse, true);
        
        if (is_array($listParcels)) {
            $listStats = [
                'list_total' => count($listParcels),
                'list_pending' => 0,
                'list_assigned' => 0,
                'list_in_transit' => 0,
                'list_completed' => 0,
                'list_cancelled' => 0
            ];
            
            foreach ($listParcels as $listParcel) {
                $status = strtolower($listParcel['status'] ?? 'pending');
                $listStats['list_' . $status]++;
            }
            
            
            $stats = array_merge($stats, $listStats);
        }
    }
    
    
    $stats['in_progress'] = $stats['assigned'] + $stats['in_transit'];
    $stats['completed'] = $stats['delivered'];
    $stats['active'] = $stats['total'] - $stats['delivered'] - $stats['cancelled'];
    
    
    if ($stats['total'] > 0) {
        $stats['completion_rate'] = round(($stats['delivered'] / $stats['total']) * 100, 1);
        $stats['pending_rate'] = round(($stats['pending'] / $stats['total']) * 100, 1);
        $stats['in_transit_rate'] = round(($stats['in_transit'] / $stats['total']) * 100, 1);
    } else {
        $stats['completion_rate'] = 0;
        $stats['pending_rate'] = 0;
        $stats['in_transit_rate'] = 0;
    }
    
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'outlet_id' => $outletId,
        'company_id' => $companyId,
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>