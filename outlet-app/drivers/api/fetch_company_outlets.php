<?php
require_once '../../includes/OutletAwareSupabaseHelper.php';
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$companyId = $_SESSION['company_id'] ?? null;
if (!$companyId) {
    echo json_encode(['success' => false, 'error' => 'Company ID required']);
    exit;
}
try {
    $supabase = new OutletAwareSupabaseHelper();
    $outlets = $supabase->get('outlets',
        'company_id=eq.' . urlencode($companyId) . '&select=id,outlet_name,address,latitude,longitude,status&order=outlet_name'
    );
    if (empty($outlets)) {
        echo json_encode([
            'success' => true,
            'outlets' => [],
            'message' => 'No outlets found for this company'
        ]);
        exit;
    }
    $formattedOutlets = array_map(function($outlet) {
        return [
            'id' => $outlet['id'],
            'name' => $outlet['outlet_name'] ?? 'Unnamed Outlet',
            'outlet_name' => $outlet['outlet_name'] ?? 'Unnamed Outlet',
            'location' => $outlet['address'] ?? 'Unknown Location',
            'address' => $outlet['address'] ?? 'Unknown Location',
            'latitude' => $outlet['latitude'] ?? null,
            'longitude' => $outlet['longitude'] ?? null,
            'status' => $outlet['status'] ?? 'active'
        ];
    }, $outlets);
    echo json_encode([
        'success' => true,
        'outlets' => $formattedOutlets,
        'message' => count($formattedOutlets) . ' outlets found'
    ]);
} catch (Exception $e) {
    error_log('Fetch Company Outlets API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred',
        'outlets' => []
    ]);
}
