<?php
session_start();
require_once 'supabase-client.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$status = $input['status'] ?? null;

if (!$id || !$status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing id or status']);
    exit;
}

try {
    // Use PATCH on users table with query param to target row
    $endpoint = "users?id=eq.$id";
    $res = callSupabaseWithServiceKey($endpoint, 'PATCH', ['status' => $status]);
    echo json_encode(['success' => true, 'result' => $res]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
