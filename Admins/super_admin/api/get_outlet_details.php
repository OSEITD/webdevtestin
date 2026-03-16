<?php
require_once __DIR__ . '/supabase-client.php';

session_start();

header('Content-Type: application/json');

// Only super_admin can fetch outlet details
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $outletId = $_GET['outlet_id'] ?? '';

    if (empty($outletId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing outlet_id']);
        exit;
    }

    // Fetch outlet with address field
    $outlet = callSupabaseWithServiceKey(
        'outlets?id=eq.' . urlencode($outletId) . '&deleted_at=is.null&select=id,outlet_name,address',
        'GET'
    );

    if (empty($outlet)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Outlet not found']);
        exit;
    }

    // Handle both array and object responses
    $outletData = is_array($outlet) ? $outlet[0] : $outlet;

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $outletData['id'] ?? null,
            'outlet_name' => $outletData['outlet_name'] ?? '',
            'address' => $outletData['address'] ?? ''
        ]
    ]);

} catch (Exception $e) {
    error_log('Error in get_outlet_details.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

