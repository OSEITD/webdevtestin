<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/error-handler.php';

// Verify authentication
ErrorHandler::requireAuth('delete_outlet_admin.php');
ErrorHandler::requireMethod('POST', 'delete_outlet_admin.php');

require_once __DIR__ . '/supabase-client.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $outletId = $input['id'] ?? null;

    if (!$outletId) {
        throw new Exception('Missing outlet ID.');
    }

    global $supabaseUrl, $supabaseServiceKey;
    $client = new SupabaseClient($supabaseUrl, $supabaseServiceKey);

    // Track who performed the deletion (super admin user)
    $deletedBy = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

    // Soft-delete: sets deleted_at timestamp instead of removing the row
    // This preserves related data (drivers, parcels, deliveries) and avoids FK constraint errors
    $response = $client->softDelete('outlets', "id=eq.{$outletId}", $deletedBy);

    echo json_encode(['success' => true, 'message' => 'Outlet deleted successfully']);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'delete_outlet_admin.php', 400);
}
