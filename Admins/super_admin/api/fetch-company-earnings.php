<?php
require_once __DIR__ . '/supabase-client.php';

// Prevent any HTML error output
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json');

try {
    // Verify super admin session
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
        http_response_code(401);
        throw new Exception('Unauthorized access');
    }

    // Fetch companies and their revenue info
    $companies = callSupabaseWithServiceKey('companies?select=id,company_name,status,created_at,revenue');
    if (!$companies) {
        throw new Exception('Failed to fetch companies');
    }

    // Format company earnings data
    $companyEarnings = [];
    foreach ($companies as $company) {
        // Filter deliveries for this company
        $companyDeliveries = array_filter($deliveries, function($delivery) use ($company) {
            return $delivery['company_id'] === $company['id'];
        });

        // Calculate total earnings
        $totalEarnings = array_reduce($companyDeliveries, function($sum, $delivery) {
            return $sum + ($delivery['amount'] ?? 0);
        }, 0);

        // Calculate monthly earnings
        $currentMonth = date('Y-m');
        $monthlyEarnings = array_reduce($companyDeliveries, function($sum, $delivery) use ($currentMonth) {
            $deliveryMonth = substr($delivery['delivery_date'] ?? '', 0, 7);
            return $sum + ($deliveryMonth === $currentMonth ? ($delivery['amount'] ?? 0) : 0);
        }, 0);

        // Calculate completed deliveries
        $completedDeliveries = count(array_filter($companyDeliveries, function($delivery) {
            return $delivery['status'] === 'delivered';
        }));

        $companyEarnings[] = [
            'id' => $company['id'],
            'company_name' => $company['company_name'],
            'status' => $company['status'],
            'total_earnings' => $totalEarnings,
            'monthly_earnings' => $monthlyEarnings,
            'completed_deliveries' => $completedDeliveries,
            'created_at' => $company['created_at']
        ];
    }

    // Sort companies by total earnings (highest first)
    usort($companyEarnings, function($a, $b) {
        return $b['total_earnings'] - $a['total_earnings'];
    });

    // Calculate platform totals
    $platformTotals = [
        'total_earnings' => array_sum(array_column($companyEarnings, 'total_earnings')),
        'monthly_earnings' => array_sum(array_column($companyEarnings, 'monthly_earnings')),
        'total_deliveries' => array_sum(array_column($companyEarnings, 'completed_deliveries')),
        'active_companies' => count(array_filter($companyEarnings, fn($c) => $c['status'] === 'active'))
    ];

    echo json_encode([
        'success' => true,
        'companies' => $companyEarnings,
        'platform_totals' => $platformTotals
    ]);

} catch (Exception $e) {
    error_log('Error in fetch-company-earnings.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
