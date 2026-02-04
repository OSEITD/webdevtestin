<?php
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// start session and validate
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id'])) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit; }

$companyId = $_SESSION['id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$updates = [];
if (isset($input['company_name'])) $updates['company_name'] = $input['company_name'];
// support currency instead of timezone
if (isset($input['currency'])) $updates['currency'] = $input['currency'];
// accept notifications object/array
if (isset($input['notifications'])) $updates['notifications'] = $input['notifications'];

try {
    $supabase = new SupabaseClient();
    // Use service role if available for server-side update
    $result = $supabase->put("companies?id=eq.{$companyId}", $updates);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
