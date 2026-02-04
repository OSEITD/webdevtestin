<?php
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}


if (session_status() === PHP_SESSION_NONE) session_start();

// Accept either a user session or a company session. Some deployments store company id
// as `id` or `company_id` while other places use `user_id` for the logged-in user.
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id']) && !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$companyId = $_SESSION['id'] ?? $_SESSION['company_id'] ?? null;

// optional limit param
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

try {
    // If neither company nor user id is present, abort
    if (!$companyId && !$userId) {
        echo json_encode(['success' => true, 'notifications' => []]);
        exit;
    }

    $supabase = new SupabaseClient();
    $accessToken = $_SESSION['access_token'] ?? null;

    // Prefer company-wide notifications, otherwise fetch user-specific notifications
    if ($companyId) {
        $resp = $supabase->getNotifications($companyId, $accessToken, $limit);
    } else {
        // Fallback: fetch notifications where user_id equals current user
        try {
            // Use getRecord to build a safe query string; avoid method that requires companyId
            $endpoint = "notifications?user_id=eq.{$userId}&order=created_at.desc&limit={$limit}";
            $resp = $supabase->getRecord($endpoint);
            // getRecord returns object with data property; normalize below
        } catch (Exception $e) {
            // If fallback fails, return empty list
            echo json_encode(['success' => true, 'notifications' => []]);
            exit;
        }
    }

    // getNotifications returns parsed response (array) or possibly object; normalize to array
    $notifications = [];
    if (is_array($resp)) {
        $notifications = $resp;
    } elseif (is_object($resp) && isset($resp->data) && is_array($resp->data)) {
        $notifications = $resp->data;
    }

    echo json_encode(['success' => true, 'notifications' => $notifications]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

