<?php
session_start();
require_once 'supabase-client.php';

// Check authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

try {
    $reportId = $_GET['id'] ?? '';
    
    if (!$reportId) {
        throw new Exception('Report ID is required');
    }

    // Fetch report data from the database
    $query = "reports?id=eq.$reportId&select=*";
    $report = callSupabaseWithServiceKey($query, 'GET');

    if (!$report || empty($report)) {
        throw new Exception('Report not found');
    }

    $reportData = $report[0];
    $data = json_decode($reportData['data'], true);

    // Generate CSV content
    $csv = [];
    $filename = sprintf(
        '%s_report_%s_to_%s.csv',
        $reportData['type'],
        date('Y-m-d', strtotime($reportData['start_date'])),
        date('Y-m-d', strtotime($reportData['end_date']))
    );

    // Convert report data to CSV rows based on report type
    switch ($reportData['type']) {
        case 'delivery':
            $csv[] = ['Delivery Performance Overview'];
            $csv[] = ['Metric', 'Value'];
            $csv[] = ['Total Deliveries', $data['total_deliveries']];
            $csv[] = ['Completed Deliveries', $data['completed']];
            $csv[] = ['Pending Deliveries', $data['pending']];
            $csv[] = ['Cancelled Deliveries', $data['cancelled']];
            $csv[] = ['Failed Deliveries', $data['failed']];
            $csv[] = ['Success Rate (%)', number_format($data['success_rate'], 2)];
            $csv[] = ['Average Delivery Time (hours)', $data['avg_delivery_time']];
            $csv[] = [];
            
            $csv[] = ['Delivery Time Distribution'];
            $csv[] = ['Time Range', 'Count'];
            $csv[] = ['Within 24 hours', $data['delivery_times']['within_24h']];
            $csv[] = ['24-48 hours', $data['delivery_times']['within_48h']];
            $csv[] = ['48-72 hours', $data['delivery_times']['within_72h']];
            $csv[] = ['Over 72 hours', $data['delivery_times']['over_72h']];
            $csv[] = [];
            
            $csv[] = ['Performance by Outlet'];
            $csv[] = ['Outlet Name', 'Total Deliveries', 'Completed', 'Pending', 'Success Rate (%)'];
            foreach ($data['by_outlet'] as $outletId => $outlet) {
                $csv[] = [
                    $outlet['name'],
                    $outlet['total'],
                    $outlet['completed'],
                    $outlet['pending'],
                    number_format($outlet['success_rate'], 2)
                ];
            }
            $csv[] = [];
            
            $csv[] = ['Daily Delivery Statistics'];
            $csv[] = ['Date', 'Total Deliveries', 'Completed Deliveries'];
            foreach ($data['by_date'] as $date => $stats) {
                $csv[] = [$date, $stats['total'], $stats['completed']];
            }
            break;

        case 'revenue':
            $csv[] = ['Revenue Overview'];
            $csv[] = ['Metric', 'Value'];
            $csv[] = ['Total Revenue', number_format($data['total_revenue'], 2)];
            $csv[] = ['Previous Period Revenue', number_format($data['prev_period_revenue'], 2)];
            $csv[] = ['Revenue Growth (%)', number_format($data['revenue_growth'], 2)];
            $csv[] = ['Transaction Count', $data['transaction_count']];
            $csv[] = ['Average Transaction Value', number_format($data['avg_transaction_value'], 2)];
            $csv[] = [];
            
            $csv[] = ['Daily Averages'];
            $csv[] = ['Average Daily Revenue', number_format($data['daily_averages']['revenue'], 2)];
            $csv[] = ['Average Daily Transactions', number_format($data['daily_averages']['transactions'], 1)];
            $csv[] = [];
            
            if ($data['highest_value_transaction']) {
                $csv[] = ['Highest Value Transaction'];
                $csv[] = ['Amount', number_format($data['highest_value_transaction']['amount'], 2)];
                $csv[] = ['Date', date('Y-m-d', strtotime($data['highest_value_transaction']['date']))];
                $csv[] = ['Company', $data['highest_value_transaction']['company']];
                $csv[] = [];
            }
            
            $csv[] = ['Company Revenue Breakdown'];
            $csv[] = ['Company Name', 'Total Revenue', 'Transaction Count', 'Average Transaction'];
            foreach ($data['by_company'] as $companyId => $stats) {
                $csv[] = [
                    $companyId,
                    number_format($stats['total'], 2),
                    $stats['count']
                ];
            }
            break;

        case 'users':
            $csv[] = ['Metric', 'Value'];
            $csv[] = ['Total Users', $data['total_users']];
            $csv[] = ['Active Users', $data['active_users']];
            $csv[] = ['New Users', $data['new_users']];
            $csv[] = [''];
            $csv[] = ['User Role Distribution'];
            $csv[] = ['Role', 'Count'];
            foreach ($data['by_role'] as $role => $count) {
                $csv[] = [$role, $count];
            }
            break;

        case 'outlets':
            $csv[] = ['Metric', 'Value'];
            $csv[] = ['Total Outlets', $data['total_outlets']];
            $csv[] = ['Active Outlets', $data['active_outlets']];
            $csv[] = [''];
            $csv[] = ['Company Outlet Distribution'];
            $csv[] = ['Company', 'Total Outlets', 'Active Outlets'];
            foreach ($data['by_company'] as $companyId => $stats) {
                $csv[] = [
                    $stats['name'],
                    $stats['outlet_count'],
                    $stats['active_outlets']
                ];
            }
            break;
    }

    // Output CSV file
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    foreach ($csv as $row) {
        fputcsv($output, $row);
    }
    fclose($output);

} catch (Exception $e) {
    error_log("Error downloading report: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
