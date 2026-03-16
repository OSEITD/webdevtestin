<?php
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$companyId = $_SESSION['id'] ?? null;
$accessToken = $_SESSION['access_token'] ?? null;
// Track who performed the deletion for audit trail
$deletedBy = $_SESSION['user_id'] ?? $companyId;

if (!$companyId || !$accessToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? $_POST['id'] ?? $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing driver id']);
    exit;
}

try {
    $supabase = new SupabaseClient();
    
    // Construct endpoint with filters to ensure company ownership
    $endpoint = 'drivers?id=eq.' . urlencode($id) . '&company_id=eq.' . urlencode($companyId);
    
    // Soft-delete: sets deleted_at timestamp instead of removing the row
    $result = $supabase->softDelete($endpoint, $accessToken, $deletedBy);
    
    echo json_encode(['success' => true, 'message' => 'Driver deleted successfully']);

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'delete_driver.php');
}

