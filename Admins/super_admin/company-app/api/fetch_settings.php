<?php
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';

// Must be GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$companyId = $_SESSION['id'] ?? null;

try {
    $supabase = new SupabaseClient();

    // Access token if stored in session (optional)
    $accessToken = $_SESSION['access_token'] ?? null;

    // Fetch profile
    $profileResp = $supabase->getProfile($userId, $accessToken);
    // getProfile returns an array of rows; take first
    $profile = null;
    if (is_array($profileResp) && count($profileResp) > 0) {
        $profile = $profileResp[0];
    }

    // Fetch company settings if company id present
    $company = null;
    if ($companyId) {
        $companyResp = $supabase->getCompany($companyId, $accessToken);
        if (is_array($companyResp) && count($companyResp) > 0) {
            $company = $companyResp[0];
        }
    }

    echo json_encode(['success' => true, 'data' => [ 'profile' => $profile, 'company' => $company ]]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


?>
