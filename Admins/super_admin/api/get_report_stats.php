<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('get_report_stats.php');
ErrorHandler::requireMethod('GET', 'get_report_stats.php');

require_once __DIR__ . '/supabase-client.php';

function sendJsonResponse(bool $success, $data = null, $error = null, int $status = 200)
{
    http_response_code($status);
    $response = [
        'success' => $success,
        'stats' => $data,
        'error' => $error
    ];

    $json = json_encode($response);
    if ($json === false) {
        error_log('JSON encode error: ' . json_last_error_msg());
        echo json_encode([
            'success' => false,
            'stats' => null,
            'error' => 'Server error: JSON encoding failed'
        ]);
    } else {
        echo $json;
    }
    exit;
}

// Helper: safe Supabase call wrapper
function safeSupabaseFetch(string $table, string $method = 'GET', array $params = [])
{
    if (!function_exists('callSupabase')) {
        $error = 'Supabase client function callSupabase not available. Include path: ' . __DIR__ . '/../../customer-app/api/supabase-client.php';
        error_log($error);
        throw new RuntimeException($error);
    }

    try {
        // Build query string safely
        $queryString = '';
        if (!empty($params)) {
            // Clean up select parameter if present
            if (isset($params['select']) && is_string($params['select'])) {
                $params['select'] = str_replace(' ', '', $params['select']);
            }

            $queryString = http_build_query($params);
        }

        error_log("safeSupabaseFetch: Fetching from {$table} with query: {$queryString}");

        // Pass final query string to callSupabase
        $result = callSupabase($table, $queryString);

        error_log("safeSupabaseFetch: Raw response from {$table}: " . print_r($result, true));

        if ($result === null) {
            throw new RuntimeException("Empty response when fetching {$table}. Check Supabase connection and table existence.");
        }

        if (!is_array($result)) {
            $type = gettype($result);
            $content = print_r($result, true);
            throw new RuntimeException("Invalid response format from {$table}. Expected array, got {$type}. Content: {$content}");
        }

        return $result;
    } catch (Exception $e) {
        $error = "Error in safeSupabaseFetch for {$table}: " . $e->getMessage();
        error_log($error);
        throw new RuntimeException($error);
    }
}


// Initialize default stats
$stats = [
    'delivery' => [
        'total' => 0,
        'success_rate' => 0.0,
        'avg_time' => 0.0 // hours
    ],
    'revenue' => [
        'total_revenue' => 0.00,
        'monthly_growth' => 0.0
    ],
    'users' => [
        'total_users' => 0,
        'active_users' => 0
    ],
    'outlets' => [
        'total_outlets' => 0,
        'performance_rate' => 0.0,  // Changed to match usage in code
        'avg_rating' => 0.0
    ]
];

try {
    error_log('Starting report stats collection...');
    
    // 1. Fetch Delivery Stats
    error_log('get_report_stats.php: Fetching parcels');
    try {
        $parcels = safeSupabaseFetch('parcels', 'GET', ['select' => 'id,created_at,delivery_date,status']);
        
        if (is_array($parcels)) {
            // Count only delivered parcels for total deliveries
            $delivered = array_filter($parcels, function ($p) {
                return isset($p['status']) && strtolower($p['status']) === 'delivered';
            });
            $deliveredCount = count($delivered);
            
            $stats['delivery']['total'] = $deliveredCount;
            
            $totalParcels = count($parcels);
            $stats['delivery']['success_rate'] = $totalParcels > 0 ? round(($deliveredCount / $totalParcels) * 100, 1) : 0.0;

            // Average delivery time in hours (only for parcels with valid timestamps)
            $totalSeconds = 0;
            $countWithTime = 0;
            foreach ($delivered as $d) {
                if (!empty($d['created_at']) && !empty($d['delivery_date'])) {
                    $t1 = strtotime($d['created_at']);
                    $t2 = strtotime($d['delivery_date']);
                    if ($t1 !== false && $t2 !== false && $t2 > $t1) {
                        $totalSeconds += ($t2 - $t1);
                        $countWithTime++;
                    }
                }
            }
            $stats['delivery']['avg_time'] = $countWithTime > 0 ? round(($totalSeconds / $countWithTime) / 3600, 1) : 0.0;
        }
    } catch (Exception $e) {
        error_log("Error processing parcels: " . $e->getMessage());
    }

    // 2. Revenue: Fetch from companies table
    error_log('get_report_stats.php: Fetching companies for revenue calculation');
    try {
        $totalRevenue = 0.0;
        $thisMonthRevenue = 0.0;
        $lastMonthRevenue = 0.0;

        // Fetch companies and sum revenue for active companies
        $companies = safeSupabaseFetch('companies', 'GET', ['select' => 'id,revenue,status,created_at']);

        if (is_array($companies)) {
            error_log('Companies fetched: ' . count($companies) . ' rows');
            
            foreach ($companies as $company) {
                if (isset($company['status']) && $company['status'] === 'active') {
                    if (isset($company['revenue']) && $company['revenue'] !== null && $company['revenue'] !== '') {
                        $revenue = floatval($company['revenue']);
                        $totalRevenue += $revenue;
                    }
                }
            }
            error_log('Total revenue from active companies: ' . $totalRevenue);
        } else {
            error_log('Companies fetch returned non-array or empty');
        }

        $stats['revenue']['total_revenue'] = round($totalRevenue, 2);
        $stats['revenue']['monthly_growth'] = 0.0; // Monthly growth calculation can be added later

    } catch (Exception $e) {
        error_log('Error fetching companies for revenue: ' . $e->getMessage());
    }

    // 3. Users
    error_log('get_report_stats.php: Fetching users');
    // Try to select status if available; fall back to id-only count
    try {
        $usersTest = safeSupabaseFetch('all_users', 'GET', ['select' => 'id,status', 'limit' => 1]);
        $hasStatus = is_array($usersTest) && count($usersTest) > 0 && array_key_exists('status', $usersTest[0]);
    } catch (Exception $e) {
        error_log('User test query failed: ' . $e->getMessage());
        $hasStatus = false;
    }

    if ($hasStatus) {
        $users = safeSupabaseFetch('all_users', 'GET', ['select' => 'id,status']);
        if (is_array($users)) {
            $stats['users']['total_users'] = count($users);
            $active = array_filter($users, function ($u) {
                if (!isset($u['status'])) return false;
                $s = strtolower((string)$u['status']);
                return in_array($s, ['active', '1', 'true'], true);
            });
            $stats['users']['active_users'] = count($active);
        }
    } else {
        // Fallback: count ids only
        $users = safeSupabaseFetch('all_users', 'GET', ['select' => 'id']);
        if (is_array($users)) {
            $stats['users']['total_users'] = count($users);
            $stats['users']['active_users'] = $stats['users']['total_users']; // assume active if no status
        }
    }

    // Outlets
    error_log('get_report_stats.php: Fetching outlets');
    $outlets = safeSupabaseFetch('outlets', 'GET', [
        'select' => 'id,status,performance_rating',
        'order' => 'id.asc'
    ]);

    if (is_array($outlets)) {
        $totalOutlets = count($outlets);
        $stats['outlets']['total_outlets'] = $totalOutlets;

        $activeOutlets = array_filter($outlets, function ($o) {
            if (!isset($o['status'])) return false;
            $s = strtolower((string)$o['status']);
            return in_array($s, ['active', '1', 'true'], true);
        });

        $stats['outlets']['performance_rate'] = $totalOutlets > 0 ? round((count($activeOutlets) / $totalOutlets) * 100, 1) : 0.0;

        // Average rating calculation
        $totalRating = 0.0;
        $ratedCount = 0;
        foreach ($outlets as $o) {
            if (isset($o['performance_rating']) && is_numeric($o['performance_rating'])) {
                $totalRating += floatval($o['performance_rating']);
                $ratedCount++;
            }
        }
        $stats['outlets']['avg_rating'] = $ratedCount > 0 ? round($totalRating / $ratedCount, 1) : 0.0;
    }

    // Return success
    sendJsonResponse(true, $stats, null, 200);

} catch (Exception $ex) {
    $error = 'Exception in get_report_stats.php: ' . $ex->getMessage();
    error_log($error);
    error_log('Trace: ' . $ex->getTraceAsString());
    sendJsonResponse(false, null, $error);
}

?>