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

$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(10, intval($_GET['limit'] ?? 20))); 
$offset = ($page - 1) * $limit;

$statusFilter = $_GET['status'] ?? '';
$relationFilter = $_GET['relation'] ?? 'all';
$dateRangeFilter = $_GET['dateRange'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');

$supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

try {
    
    $queryFilters = ["company_id=eq.$companyId"];
    
    
    switch ($relationFilter) {
        case 'origin':
            $queryFilters[] = "origin_outlet_id=eq.$outletId";
            break;
        case 'destination':
            $queryFilters[] = "destination_outlet_id=eq.$outletId";
            break;
        case 'listed':
            
            
            break;
        case 'all':
        default:
            
            $queryFilters[] = "or=(origin_outlet_id.eq.$outletId,destination_outlet_id.eq.$outletId)";
            break;
    }
    
    
    if (!empty($statusFilter)) {
        $queryFilters[] = "status=eq.$statusFilter";
    }
    
    
    if (!empty($dateRangeFilter)) {
        $dateCondition = getDateRangeCondition($dateRangeFilter);
        if ($dateCondition) {
            $queryFilters[] = $dateCondition;
        }
    }
    
    
    if (!empty($searchFilter)) {
        $encodedSearch = urlencode('%' . $searchFilter . '%');
        $searchConditions = [
            "track_number.ilike.*$encodedSearch*",
            "sender_name.ilike.*$encodedSearch*",
            "receiver_name.ilike.*$encodedSearch*",
            "sender_phone.ilike.*$encodedSearch*",
            "receiver_phone.ilike.*$encodedSearch*"
        ];
        $queryFilters[] = "or=(" . implode(',', $searchConditions) . ")";
    }
    
    
    $queryString = implode('&', $queryFilters);
    
    
    $countUrl = "$supabaseUrl/rest/v1/parcels?$queryString&select=id";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: count=exact"
            ]
        ]
    ]);
    
    $countResponse = @file_get_contents($countUrl, false, $context);
    
    if ($countResponse === false) {
        throw new Exception('Failed to fetch parcel count');
    }
    
    
    $totalCount = 0;
    if (!empty($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (stripos($header, 'Content-Range:') === 0) {
                if (preg_match('/\d+-\d+\/(\d+)/', $header, $matches)) {
                    $totalCount = intval($matches[1]);
                    break;
                }
            }
        }
    }
    
    
    if ($totalCount === 0) {
        $countData = json_decode($countResponse, true);
        if (is_array($countData)) {
            $totalCount = count($countData);
        }
    }
    
    
    $dataUrl = "$supabaseUrl/rest/v1/parcels?$queryString&select=id,track_number,status,sender_name,sender_phone,receiver_name,receiver_phone,origin_outlet_id,destination_outlet_id,parcel_weight,created_at,updated_at&order=created_at.desc&limit=$limit&offset=$offset";
    
    $dataResponse = @file_get_contents($dataUrl, false, $context);
    
    if ($dataResponse === false) {
        throw new Exception('Failed to fetch parcel data');
    }
    
    $parcels = json_decode($dataResponse, true);
    
    if (!is_array($parcels)) {
        throw new Exception('Invalid response format');
    }
    
    
    if ($relationFilter === 'listed') {
        $parcels = filterListedParcels($parcels, $outletId, $supabaseUrl, $supabaseKey);
    }
    
    
    $totalPages = ceil($totalCount / $limit);
    
    $pagination = [
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'totalCount' => $totalCount,
        'pageSize' => $limit,
        'hasNext' => $page < $totalPages,
        'hasPrev' => $page > 1
    ];
    
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'parcels' => $parcels,
        'pagination' => $pagination
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getDateRangeCondition($range) {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    
    switch ($range) {
        case 'today':
            $start = clone $now;
            $start->setTime(0, 0, 0);
            $end = clone $now;
            $end->setTime(23, 59, 59);
            return "created_at.gte." . $start->format('Y-m-d\TH:i:s') . "&created_at.lte." . $end->format('Y-m-d\TH:i:s');
            
        case 'yesterday':
            $yesterday = clone $now;
            $yesterday->modify('-1 day');
            $start = clone $yesterday;
            $start->setTime(0, 0, 0);
            $end = clone $yesterday;
            $end->setTime(23, 59, 59);
            return "created_at.gte." . $start->format('Y-m-d\TH:i:s') . "&created_at.lte." . $end->format('Y-m-d\TH:i:s');
            
        case 'this_week':
            $startOfWeek = clone $now;
            $startOfWeek->modify('monday this week')->setTime(0, 0, 0);
            return "created_at.gte." . $startOfWeek->format('Y-m-d\TH:i:s');
            
        case 'last_week':
            $startOfLastWeek = clone $now;
            $startOfLastWeek->modify('monday last week')->setTime(0, 0, 0);
            $endOfLastWeek = clone $now;
            $endOfLastWeek->modify('sunday last week')->setTime(23, 59, 59);
            return "created_at.gte." . $startOfLastWeek->format('Y-m-d\TH:i:s') . "&created_at.lte." . $endOfLastWeek->format('Y-m-d\TH:i:s');
            
        case 'this_month':
            $startOfMonth = clone $now;
            $startOfMonth->modify('first day of this month')->setTime(0, 0, 0);
            return "created_at.gte." . $startOfMonth->format('Y-m-d\TH:i:s');
            
        case 'last_month':
            $startOfLastMonth = clone $now;
            $startOfLastMonth->modify('first day of last month')->setTime(0, 0, 0);
            $endOfLastMonth = clone $now;
            $endOfLastMonth->modify('last day of last month')->setTime(23, 59, 59);
            return "created_at.gte." . $startOfLastMonth->format('Y-m-d\TH:i:s') . "&created_at.lte." . $endOfLastMonth->format('Y-m-d\TH:i:s');
            
        default:
            return null;
    }
}

function filterListedParcels($parcels, $outletId, $supabaseUrl, $supabaseKey) {
    if (empty($parcels)) {
        return $parcels;
    }
    
    
    $parcelIds = array_column($parcels, 'id');
    
    if (empty($parcelIds)) {
        return [];
    }
    
    
    $parcelIdsString = implode(',', array_map(function($id) {
        return "\"$id\"";
    }, $parcelIds));
    
    $listUrl = "$supabaseUrl/rest/v1/parcel_list?outlet_id=eq.$outletId&parcel_id=in.($parcelIdsString)&select=parcel_id";
    
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
    
    $listResponse = @file_get_contents($listUrl, false, $context);
    
    if ($listResponse === false) {
        return $parcels; 
    }
    
    $listedParcels = json_decode($listResponse, true);
    
    if (!is_array($listedParcels)) {
        return $parcels;
    }
    
    
    $listedParcelIds = array_column($listedParcels, 'parcel_id');
    
    
    return array_filter($parcels, function($parcel) use ($listedParcelIds) {
        return in_array($parcel['id'], $listedParcelIds);
    });
}
?>