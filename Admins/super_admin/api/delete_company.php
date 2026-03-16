<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/error-handler.php';

// Verify authentication
ErrorHandler::requireAuth('delete_company.php');
ErrorHandler::requireMethod('POST', 'delete_company.php');

require_once __DIR__ . '/supabase-client.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $companyId = $input['id'] ?? null;

    if (!$companyId) {
        throw new Exception('Missing company ID.');
    }

    global $supabaseUrl, $supabaseServiceKey;
    $client = new SupabaseClient($supabaseUrl, $supabaseServiceKey);

    // Track who performed the deletion (super admin user)
    $deletedBy = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

    // Soft-delete: sets deleted_at timestamp instead of removing the row
    // This preserves related data and avoids foreign key constraint errors
    $response = $client->softDelete('companies', "id=eq.{$companyId}", $deletedBy);

    echo json_encode(['success' => true, 'message' => 'Company deleted successfully.']);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'delete_company.php', 400);
}
