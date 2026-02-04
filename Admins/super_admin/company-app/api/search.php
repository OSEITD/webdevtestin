<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/supabase-client.php';

// Basic input validation
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

// Start session to read access token if present
if (session_status() === PHP_SESSION_NONE) session_start();
$accessToken = $_SESSION['access_token'] ?? null;

try {
    $supabase = new SupabaseClient();
    $svc = $supabase; // alias

    $escaped = str_replace("'", "''", $q);

    $results = [];

    // Normalize response helper
    $normalize = function($res) {
        if (is_array($res)) return $res;
        if (is_object($res) && isset($res->data)) return $res->data;
        if (is_string($res)) {
            error_log('Search API - string response (possible error body): ' . $res);
            return [];
        }
        return [];
    };

    // Helper to execute a get using access token if available, otherwise use service role if configured
    $doGet = function($path) use ($svc, $accessToken) {
        if (!empty($accessToken)) {
            error_log('Search API - using access token for path: ' . $path);
            return $svc->getWithToken($path, $accessToken);
        }
        error_log('Search API - using service/anon key for path: ' . $path);
        return $svc->getRecord($path, true);
    };

    // Parcels: match tracking number
    $parcelsPath = "parcels?track_number=ilike.%25{$escaped}%25&select=id,track_number,receiver_name,sender_name,status&limit=6";
    try {
        $parcels = $doGet($parcelsPath);
        error_log('Search API - parcels response: ' . print_r($parcels, true));
    $parcelsData = $normalize($parcels);
        foreach ($parcelsData as $p) {
            $results[] = [
                'type' => 'parcel',
                'title' => $p['track_number'] ?? ('Parcel ' . ($p['id'] ?? '')),
                'snippet' => ($p['receiver_name'] ?? '') . ' — ' . ($p['status'] ?? ''),
                'link' => 'pages/view_delivery.php?track=' . urlencode($p['track_number'] ?? '')
            ];
        }
    } catch (Exception $e) {
        error_log('Search API - parcels query failed for path: ' . $parcelsPath . ' Error: ' . $e->getMessage());
    }

    // Drivers: match name or phone
    $driversPath = "drivers?driver_name=ilike.%25{$escaped}%25&select=id,driver_name,driver_phone,status&limit=6";
    try {
        $drivers = $doGet($driversPath);
        error_log('Search API - drivers response: ' . print_r($drivers, true));
    $driversData = $normalize($drivers);
        foreach ($driversData as $d) {
            $results[] = [
                'type' => 'driver',
                'title' => $d['driver_name'] ?? 'Driver',
                'snippet' => $d['driver_phone'] ?? '',
                'link' => 'pages/drivers.php?id=' . urlencode($d['id'] ?? '')
            ];
        }
    } catch (Exception $e) {
        error_log('Search API - drivers query failed for path: ' . $driversPath . ' Error: ' . $e->getMessage());
    }

    // Outlets: match name or address
    $outletsPath = "outlets?outlet_name=ilike.%25{$escaped}%25&select=id,outlet_name,address,status&limit=6";
    try {
        $outlets = $doGet($outletsPath);
        error_log('Search API - outlets response: ' . print_r($outlets, true));
    $outletsData = $normalize($outlets);
        foreach ($outletsData as $o) {
            $results[] = [
                'type' => 'outlet',
                'title' => $o['outlet_name'] ?? 'Outlet',
                'snippet' => $o['address'] ?? '',
                'link' => 'pages/company-view-outlet.php?id=' . urlencode($o['id'] ?? '')
            ];
        }
    } catch (Exception $e) {
        error_log('Search API - outlets query failed for path: ' . $outletsPath . ' Error: ' . $e->getMessage());
    }

    // Trips: match trip_date
    $tripsPath = "trips?trip_date=ilike.%25{$escaped}%25&select=id,trip_date,trip_status&limit=6";
    try {
        $trips = $doGet($tripsPath);
        error_log('Search API - trips response: ' . print_r($trips, true));
    $tripsData = $normalize($trips);
        foreach ($tripsData as $t) {
            $results[] = [
                'type' => 'trip',
                'title' => 'Trip ' . ($t['id'] ?? ''),
                'snippet' => ($t['trip_status'] ?? '') . ' — ' . ($t['trip_date'] ?? ''),
                'link' => 'pages/view_trip.php?id=' . urlencode($t['id'] ?? '')
            ];
        }
    } catch (Exception $e) {
        error_log('Search API - trips query failed for path: ' . $tripsPath . ' Error: ' . $e->getMessage());
    }

    // Limit to 12 results total
    $results = array_slice($results, 0, 12);

    echo json_encode(['success' => true, 'results' => $results]);
    exit;
} catch (Exception $e) {
    error_log('Search API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

?>
