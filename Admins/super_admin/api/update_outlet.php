<?php
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

session_start();
require_once 'supabase-client.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }
    
    if (!$data || empty($data['id'])) {
        throw new Exception('Invalid request data or missing outlet ID');
    }

    $outlet_id = $data['id'];
    unset($data['id']); // Don't update the ID itself

    // Required fields validation
    $requiredFields = ['outlet_name', 'company_id', 'address', 'contact_person', 'contact_phone', 'contact_email', 'status'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $data['updated_at'] = gmdate('Y-m-d H:i:s.u').'Z';

    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $data['company_id'])) {
        throw new Exception('Invalid company ID format');
    }

    $result = callSupabaseWithServiceKey("outlets?id=eq.{$outlet_id}", 'PATCH', $data);
    
    if (!$result || isset($result['error'])) {
        throw new Exception('Failed to update outlet: ' . ($result['error']['message'] ?? 'Unknown error'));
    }

    echo json_encode(['success' => true, 'outlet' => $result]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
