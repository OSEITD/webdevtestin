<?php
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$companyId = $_SESSION['id'] ?? null;
if (!$companyId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? $_POST['id'] ?? $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id']);
    exit;
}

try {
    $supabase = new SupabaseClient();
    $supabaseUrl = $supabase->getUrl();
    $supabaseKey = $supabase->getKey();
    $serviceKey = getenv('SUPABASE_SERVICE_ROLE') ?: $supabaseKey;

    $url = $supabaseUrl . '/rest/v1/drivers?id=eq.' . urlencode($id) . '&company_id=eq.' . urlencode($companyId);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $serviceKey,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) throw new Exception('cURL error: ' . $curlErr);
    if ($httpCode >= 400) {
        $errBody = substr($response ?? '', 0, 1000);
        throw new Exception('API error: HTTP ' . $httpCode . ' - ' . $errBody);
    }

    $decoded = json_decode($response, true);
    echo json_encode(['success' => true, 'deleted' => $decoded]);
} catch (Exception $e) {
    ErrorHandler::handleException($e, 'delete_driver.php');
}
