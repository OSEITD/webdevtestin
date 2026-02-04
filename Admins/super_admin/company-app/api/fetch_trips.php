<?php
// Disable error display in production; still log errors
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/curl-helper.php';
require_once __DIR__ . '/error-handler.php';

try {
    // Initialize secure session via session-helper
    require_once __DIR__ . '/session-helper.php';
    SessionHelper::initializeSecureSession();

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }

    // Validate authentication
    if (!isset($_SESSION['id']) || !isset($_SESSION['access_token'])) {
        throw new Exception('Not authenticated', 401);
    }

    $companyId = $_SESSION['id'] ?? $_SESSION['company_id'] ?? null;
    $accessToken = $_SESSION['access_token'];
    $refreshToken = $_SESSION['refresh_token'] ?? null;

    if (!$companyId || !$accessToken) {
        throw new Exception('Not authenticated', 401);
    }

    $supabase = new SupabaseClient();

    // Helper to refresh access token
    function refreshTokenIfNeeded($supabase, $refreshToken) {
        if (!$refreshToken) return null;
        $refreshUrl = $supabase->getUrl() . '/auth/v1/token?grant_type=refresh_token';
        $headers = [
            'apikey: ' . $supabase->getKey(),
            'Content-Type: application/json'
        ];

        $payload = json_encode(['refresh_token' => $refreshToken]);
        try {
            $response = CurlHelper::post($refreshUrl, $payload, $headers);
            $result = json_decode($response, true);
            if (is_array($result) && isset($result['access_token'])) {
                $_SESSION['access_token'] = $result['access_token'];
                return $result['access_token'];
            }
        } catch (Exception $ex) {
            error_log('fetch_trips.php: token refresh via CurlHelper failed: ' . $ex->getMessage());
        }
        return null;
    }

    // Attempt token refresh if necessary
    if ($refreshToken) {
        $newAccess = refreshTokenIfNeeded($supabase, $refreshToken);
        if ($newAccess) $accessToken = $newAccess;
    }

    // --- Fetch trips with parcel and stop details ---
    // Use a simpler select for parcel_list(*) and enrich parcels afterwards.
    $select = '*, stops:trip_stops(*), parcels:parcel_list(*), driver:drivers(*), vehicle:vehicle(*)';
    $select = preg_replace('/\s+/', ' ', trim($select));

    $endpoint = 'trips'
        . '?select=' . rawurlencode($select)
        . '&company_id=eq.' . rawurlencode($companyId)
        . '&order=created_at.desc';

    error_log("Fetching trips endpoint: {$endpoint}");

    // Also log the full URL for debugging (SupabaseClient will log too)
    $fullUrl = $supabase->getUrl() . '/rest/v1/' . $endpoint;
    error_log("Full Supabase URL: {$fullUrl}");

    // Attempt primary request, but if Supabase returns a 4xx about parsing or missing
    // relationships, retry with a simplified select (already compact above).
    try {
        $result = $supabase->getWithToken($endpoint, $accessToken);
    } catch (Exception $e) {
        $errMsg = $e->getMessage();
        error_log("fetch_trips.php: primary request failed: " . $errMsg);

        // Detect common PostgREST/Supabase errors we can reasonably retry from
        $shouldRetry = false;
        if (stripos($errMsg, 'failed to parse select') !== false) $shouldRetry = true;
        if (stripos($errMsg, 'Could not find a relationship') !== false) $shouldRetry = true;
        if (stripos($errMsg, 'PGRST100') !== false || stripos($errMsg, 'PGRST200') !== false) $shouldRetry = true;

        if ($shouldRetry) {
            error_log("fetch_trips.php: detected missing relationship or parse error for nested select, retrying with very simplified select");
            $selectFallback = '*,stops:trip_stops(*),parcels:parcel_list(*),driver:drivers(*),vehicle:vehicle(*)';
            $selectFallback = preg_replace('/\s+/', ' ', trim($selectFallback));
            $endpointFallback = 'trips'
                . '?select=' . rawurlencode($selectFallback)
                . '&company_id=eq.' . rawurlencode($companyId)
                . '&order=created_at.desc';
            error_log("Retry endpoint: {$endpointFallback}");

            // Try the fallback request once
            $result = $supabase->getWithToken($endpointFallback, $accessToken);
        } else {
            // Not a recoverable error: rethrow to be handled below
            throw $e;
        }
    }

    if ($result === null) throw new Exception('Empty response from Supabase');

    $trips = is_object($result) && isset($result->data) ? $result->data : $result;
    if (!is_array($trips)) throw new Exception('Invalid response format from Supabase');

    // --- Normalize parcel objects for frontend ---
    // Ensure every trip has `parcels` as an array of parcel objects with canonical keys
    $trips = array_map(function ($trip) {
        // If API returned parcel_list rows under key 'parcels' keep them as-is (we named them parcels in select)
        if (isset($trip['parcels']) && is_array($trip['parcels'])) {
            $normalized = [];
            foreach ($trip['parcels'] as $pl) {
                // If the primary nested select returned a nested 'parcel' object (older approach), use it
                if (isset($pl['parcel']) && is_array($pl['parcel'])) {
                    $p = $pl['parcel'];
                } else {
                    // If parcel_list row already contains canonical fields use it directly
                    $p = is_array($pl) ? $pl : [];
                }

                $normalizedParcel = [
                    'id' =>  $p['parcel_id'] ?? $p['id'] ?? null,
                    'track_number' =>
                        $p['track_number'] ??
                        $p['tracking_number'] ??
                        $p['parcel_number'] ??
                        null,
                    'sender_name' => $p['sender_name'] ?? $p['sender'] ?? null,
                    'receiver_name' =>
                        $p['receiver_name'] ??
                        $p['recipient_name'] ??
                        $p['receiver'] ??
                        null,
                    'origin_outlet' => $p['origin_outlet'] ?? $p['origin_outlet_id'] ?? null,
                    'destination_outlet' => $p['destination_outlet'] ?? $p['destination_outlet_id'] ?? null,
                    'weight' => isset($p['parcel_weight']) ? $p['parcel_weight'] : (isset($p['weight']) ? $p['weight'] : 0),
                    'delivery_fee' => $p['delivery_fee'] ?? 0,
                    'status' => $p['status'] ?? null,
                ];

                $normalized[] = $normalizedParcel;
            }

            // Replace the trip parcels array with normalized parcel objects
            $trip['parcels'] = $normalized;
        }
        return $trip;
    }, $trips);

    // Post-process: fetch authoritative parcel rows (id, track_number, sender_name, receiver_name)
    // for ALL parcel ids present in the trips and merge them back.
    $parcelIds = [];
    foreach ($trips as $t) {
        if (isset($t['parcels']) && is_array($t['parcels'])) {
            foreach ($t['parcels'] as $p) {
                if (!empty($p['id'])) {
                    $parcelIds[] = $p['id'];
                }
            }
        }
    }

    // Deduplicate ids
    $parcelIds = array_values(array_unique($parcelIds));

    if (!empty($parcelIds)) {
        // Build a PostgREST in-list of quoted UUIDs: ("id1","id2")
        $quoted = array_map(function($id) { return '"' . $id . '"'; }, $parcelIds);
        $inList = '(' . implode(',', $quoted) . ')';

        // Request the canonical fields we want to merge back into the normalized parcels
        // NOTE: removed company_id filter here to ensure we can fetch canonical parcel rows by id
        $select = 'id,track_number,sender_name,receiver_name';
        $endpoint = 'parcels?select=' . rawurlencode($select) . '&id=in.' . $inList;

        try {
            // Use getWithToken to ensure RLS and session token are respected
            $parcelResp = $supabase->getWithToken($endpoint, $accessToken);
            $parcelRows = is_object($parcelResp) && isset($parcelResp->data) ? $parcelResp->data : $parcelResp;

            if (!is_array($parcelRows) || count($parcelRows) === 0) {
                error_log('fetch_trips.php: parcel enrichment returned no rows for ids: ' . json_encode($parcelIds));
            }

            if (is_array($parcelRows)) {
                $parcelMap = [];
                foreach ($parcelRows as $pr) {
                    if (isset($pr['id'])) {
                        $parcelMap[$pr['id']] = $pr;
                    }
                }

                // Merge back into trips; overwrite when DB value is non-empty
                foreach ($trips as &$t) {
                    if (isset($t['parcels']) && is_array($t['parcels'])) {
                        foreach ($t['parcels'] as &$p) {
                            if (!empty($p['id']) && isset($parcelMap[$p['id']])) {
                                $db = $parcelMap[$p['id']];
                                if (!empty($db['track_number'])) {
                                    $p['track_number'] = $db['track_number'];
                                }
                                if (!empty($db['sender_name'])) {
                                    $p['sender_name'] = $db['sender_name'];
                                }
                                if (!empty($db['receiver_name'])) {
                                    $p['receiver_name'] = $db['receiver_name'];
                                }
                                // ensure we still have a readable fallback if track_number is empty
                                if (empty($p['track_number'])) {
                                    $p['track_number'] = isset($p['id']) ? 'ID-' . substr($p['id'], 0, 8) : null;
                                }
                            }
                        }
                        unset($p);
                    }
                }
                unset($t);
            }
        } catch (Exception $e) {
            // Ignore fetch errors and leave existing normalized values as-is
            error_log('fetch_trips.php: could not enrich parcel rows: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'trips' => $trips
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'fetch_trips.php');
}
?>
