<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('get_dashboard_stats.php');

// Verify GET method
ErrorHandler::requireMethod('GET', 'get_dashboard_stats.php');

require_once 'supabase-client.php';

try {
    error_log("Starting dashboard stats collection...");
    // Initialize response array
    $response = [
        'success' => true,
        'stats' => [
            'total_companies' => 0,
            'active_users' => 0,
            'total_deliveries' => 0,
            'ongoing_deliveries' => 0
        ],
        'revenue' => [
            'total' => 0,
            'monthly_growth' => 0
        ],
        'topCompanies' => []
    ];

    // Fetch system settings for currency
    $settings = callSupabase('system_settings?id=eq.1');
    $defaultCurrency = 'USD';
    if (is_array($settings) && !empty($settings[0]) && !empty($settings[0]['default_currency'])) {
        $defaultCurrency = strtoupper(substr($settings[0]['default_currency'], 0, 3));
    }

    // Set currency information
    $currencySymbol = '$';
    if ($defaultCurrency === 'EUR') {
        $currencySymbol = '€';
    } elseif ($defaultCurrency === 'GBP') {
        $currencySymbol = '£';
    } elseif ($defaultCurrency === 'ZMW') {
        $currencySymbol = 'ZK';
    }

    $response['currency'] = [
        'code' => $defaultCurrency,
        'symbol' => $currencySymbol
    ];

    // Get companies with revenue data
    $companiesData = callSupabase('companies?select=id,company_name,revenue,status&order=revenue.desc');
    if (is_array($companiesData)) {
        $response['stats']['total_companies'] = count($companiesData);
        
        // Calculate revenue metrics
        $totalRevenue = 0;
        $companyEarnings = [];
        
        foreach ($companiesData as $company) {
            if ($company['status'] === 'active') {
                $revenue = $company['revenue'] ?? 0;
                // Convert to selected currency (assume all stored in USD for this example)
                $convertedRevenue = $revenue;
                if ($defaultCurrency !== 'USD' && isset($exchangeRates[$defaultCurrency])) {
                    $convertedRevenue = $revenue * $exchangeRates[$defaultCurrency];
                }
                $totalRevenue += $convertedRevenue;
                $companyEarnings[] = [
                    'name' => $company['company_name'],
                    'earnings' => $convertedRevenue
                ];
            }
        }
        
        $response['revenue']['total'] = $totalRevenue;
        $lastMonthRevenue = 0; // This needs to be calculated properly in the future
        $response['revenue']['monthly_growth'] = $lastMonthRevenue > 0 
            ? (($totalRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;
            
        // Sort companies by earnings and get top 5
        usort($companyEarnings, fn($a, $b) => $b['earnings'] - $a['earnings']);
        $response['topCompanies'] = array_slice($companyEarnings, 0, 5);
    }

    // Get active users count
    try {
        error_log("Fetching active users...");
        $activeUsersQuery = callSupabase('all_users', 'GET', ['select' => 'id', 'account_status' => 'active']);
        error_log("Active users query result: " . print_r($activeUsersQuery, true));
        if (is_array($activeUsersQuery)) {
            $response['stats']['active_users'] = count($activeUsersQuery);
            error_log("Updated active_users count: " . $response['stats']['active_users']);
        }

        // Get delivery counts
        error_log("Fetching delivered parcels...");
        $deliveredParcelsQuery = callSupabase('parcels', 'GET', ['select' => 'id', 'status' => 'delivered']);
        error_log("Delivered parcels query result: " . print_r($deliveredParcelsQuery, true));
        if (is_array($deliveredParcelsQuery)) {
            $response['stats']['total_deliveries'] = count($deliveredParcelsQuery);
            error_log("Updated total_deliveries count: " . $response['stats']['total_deliveries']);
        }

        // Get ongoing deliveries
        error_log("Fetching ongoing deliveries...");
        $inProgressQuery = callSupabase('parcels', 'GET', ['select' => 'id', 'status.neq' => 'delivered']);
        error_log("In-progress parcels query result: " . print_r($inProgressQuery, true));
        if (is_array($inProgressQuery)) {
            $response['stats']['ongoing_deliveries'] = count($inProgressQuery);
            error_log("Updated ongoing_deliveries count: " . $response['stats']['ongoing_deliveries']);
        }
    } catch (Exception $e) {
        error_log("Error fetching stats: " . $e->getMessage());
    }

    // Get delivery trend data for the chart
    try {
        error_log("Fetching delivery trend data...");
        
        // Get the period from query parameter (default to 30 days)
        $period = isset($_GET['period']) ? intval($_GET['period']) : 30;
        $period = in_array($period, [30, 90, 365]) ? $period : 30; // Validate period
        
        // Calculate date range
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-$period days"));
        
        // Fetch parcels created within the date range
        $query = "parcels?select=created_at,status&created_at=gte.$startDate&created_at=lte.$endDate";
        $parcelsData = callSupabase($query);
        
        $deliveryTrends = [];
        if (is_array($parcelsData)) {
            // Initialize array with all dates in range
            $currentDate = new DateTime($startDate);
            $lastDate = new DateTime($endDate);
            
            while ($currentDate <= $lastDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $deliveryTrends[$dateStr] = 0;
                $currentDate->modify('+1 day');
            }
            
            // Count deliveries per day
            foreach ($parcelsData as $parcel) {
                $deliveryDate = substr($parcel['created_at'], 0, 10); // Get just the date part
                if (isset($deliveryTrends[$deliveryDate])) {
                    $deliveryTrends[$deliveryDate]++;
                }
            }
            
            // Convert to arrays for the chart
            $chartData = [
                'labels' => array_keys($deliveryTrends),
                'data' => array_values($deliveryTrends)
            ];
            
            // Calculate trend
            $firstHalf = array_slice($chartData['data'], 0, floor($period/2));
            $secondHalf = array_slice($chartData['data'], floor($period/2));
            
            $firstAvg = !empty($firstHalf) ? array_sum($firstHalf) / count($firstHalf) : 0;
            $secondAvg = !empty($secondHalf) ? array_sum($secondHalf) / count($secondHalf) : 0;
            
            $trendPercentage = $firstAvg > 0 ? (($secondAvg - $firstAvg) / $firstAvg) * 100 : 0;
            
            $response['chartData'] = [
                'labels' => $chartData['labels'],
                'data' => $chartData['data'],
                'trend' => [
                    'percentage' => round($trendPercentage, 1),
                    'direction' => $trendPercentage >= 0 ? 'up' : 'down'
                ]
            ];
        }
        
        error_log("Chart data collected successfully");
    } catch (Exception $e) {
        error_log("Error fetching chart data: " . $e->getMessage());
        $response['chartData'] = [
            'labels' => [],
            'data' => [],
            'trend' => ['percentage' => 0, 'direction' => 'up']
        ];
    }

    error_log("Final response data: " . print_r($response, true));
    echo json_encode($response);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'get_dashboard_stats.php');
}