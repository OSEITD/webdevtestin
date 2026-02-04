<?php

require_once '../includes/OutletAwareSupabaseHelper.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$companyId = $_SESSION['company_id'] ?? null;

if (!$companyId) {
    echo json_encode(['success' => false, 'error' => 'Company ID required']);
    exit;
}

$supabase = new OutletAwareSupabaseHelper();

try {
    switch ($action) {
        case 'details':
            $outletId = $_GET['id'] ?? '';
            if (!$outletId) {
                echo json_encode(['success' => false, 'error' => 'Outlet ID required']);
                exit;
            }
            
            $outlet = $supabase->get('outlets', 
                'id=eq.' . urlencode($outletId) . '&company_id=eq.' . urlencode($companyId) . '&select=id,outlet_name,address,latitude,longitude,status'
            );
            
            if (empty($outlet)) {
                echo json_encode(['success' => false, 'error' => 'Outlet not found']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'outlet' => $outlet[0]
            ]);
            break;
            
        case 'list':
            $outlets = $supabase->get('outlets', 
                'company_id=eq.' . urlencode($companyId) . '&select=id,outlet_name,address,latitude,longitude,status&order=outlet_name'
            );
            
            echo json_encode([
                'success' => true,
                'outlets' => $outlets,
                'count' => count($outlets)
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log('Outlets API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>