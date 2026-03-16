<?php
require_once __DIR__ . '/supabase-client.php';

session_start();

header('Content-Type: application/json');

// Only super_admin can fetch outlets this way
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $companyId = $_GET['company_id'] ?? '';

    if (empty($companyId)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $outlets = callSupabaseWithServiceKey(
        'outlets?company_id=eq.' . urlencode($companyId) . '&deleted_at=is.null&select=id,outlet_name&order=outlet_name.asc',
        'GET'
    );

    if (!is_array($outlets)) $outlets = [];

    echo json_encode(['success' => true, 'data' => $outlets]);

} catch (Exception $e) {
    error_log('Error in get_outlets_by_company.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
