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
if (isset($input['company_name'])) {
    $updates['company_name'] = $input['company_name'];
    
    $supabase = new SupabaseClient();
    // Build proper endpoint for Supabase REST
    $nameEncoded = rawurlencode($updates['company_name']);
    $companyIdEnc = rawurlencode($companyId);
    $endpoint = "companies?company_name=eq.{$nameEncoded}&id=neq.{$companyIdEnc}&select=id";
    $res = $supabase->get($endpoint);
    // $supabase->get() returns an object with ->data or an array; normalize
    $existing = [];
    if (is_object($res) && isset($res->data) && is_array($res->data)) {
        $existing = $res->data;
    } elseif (is_array($res)) {
        $existing = $res;
    }

    if (!empty($existing)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'A company with this name already exists',
            'errors' => ['company_name' => 'A company with this name already exists.']
        ]);
        exit;
    }
}

// support currency instead of timezone
if (isset($input['currency'])) $updates['currency'] = $input['currency'];
// accept notifications object/array
if (isset($input['notifications'])) $updates['notifications'] = $input['notifications'];

// accept address fields
if (isset($input['address'])) $updates['address'] = $input['address'];
if (isset($input['city'])) $updates['city'] = $input['city'];
if (isset($input['state'])) $updates['state'] = $input['state'];
if (isset($input['postal_code'])) $updates['postal_code'] = $input['postal_code'];
if (isset($input['country'])) $updates['country'] = $input['country'];

try {
    // Supabase client already instantiated above if company_name was set, otherwise create it here
    if (!isset($supabase)) $supabase = new SupabaseClient();
    // Use service role if available for server-side update
    $result = $supabase->put("companies?id=eq.{$companyId}", $updates);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
