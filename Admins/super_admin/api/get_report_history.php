<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('get_report_history.php');
ErrorHandler::requireMethod('GET', 'get_report_history.php');

require_once 'supabase-client.php';

try {
    $reportType = $_GET['type'] ?? '';
    
    if (!$reportType) {
        throw new Exception('Report type is required');
    }

    // Fetch report history from the database
    $query = "reports?report_type=eq.$reportType&select=id,report_type,start_date,end_date,created_at,generated_by,file_path&order=created_at.desc&limit=10";
    $reports = callSupabaseWithServiceKey($query, 'GET');

    if ($reports === false) {
        throw new Exception('Failed to fetch report history');
    }

    echo json_encode([
        'success' => true,
        'reports' => $reports
    ]);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'get_report_history.php', 400);
}
