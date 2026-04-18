<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

try {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalhost = in_array($host, ['localhost', '127.0.0.1']);
    $cookieParams = [
        'lifetime' => 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !$isLocalhost
    ];

    session_set_cookie_params($cookieParams);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $companyId = $_SESSION['id'] ?? $_SESSION['company_id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$companyId && !$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $client = new SupabaseClient();
    $action = $_GET['action'] ?? 'list';

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = max(1, min($limit, 50));
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

    $scopeFilter = $companyId ? "company_id=eq.{$companyId}" : "recipient_id=eq.{$userId}";

    switch ($action) {
        case 'list': {
            $endpoint = "notifications?select=*&order=created_at.desc&limit={$limit}&offset={$offset}&{$scopeFilter}";
            $resp = $client->getRecord($endpoint, true);
            $rows = is_object($resp) && isset($resp->data) ? $resp->data : ($resp ?? []);
            echo json_encode(['success' => true, 'notifications' => is_array($rows) ? $rows : []]);
            break;
        }
        case 'unread_count': {
            $endpoint = "notifications?select=id&is_read=eq.false&{$scopeFilter}";
            $resp = $client->getRecord($endpoint, true);
            $rows = is_object($resp) && isset($resp->data) ? $resp->data : ($resp ?? []);
            $count = is_array($rows) ? count($rows) : 0;
            echo json_encode(['success' => true, 'unread_count' => $count]);
            break;
        }
        case 'mark_read': {
            $ids = $_POST['notification_ids'] ?? [];
            if (empty($ids)) {
                $singleId = $_POST['notification_id'] ?? null;
                if ($singleId) {
                    $ids = [$singleId];
                }
            }
            $ids = array_filter($ids);
            if (empty($ids)) {
                echo json_encode(['success' => false, 'error' => 'No notification IDs provided']);
                exit;
            }
            $idsStr = implode(',', $ids);
            $endpoint = "notifications?id=in.({$idsStr})&{$scopeFilter}";
            $client->put($endpoint, [
                'is_read' => true,
                'status' => 'read',
                'read_at' => date('c')
            ]);
            echo json_encode(['success' => true]);
            break;
        }
        case 'mark_all_read': {
            $endpoint = "notifications?is_read=eq.false&{$scopeFilter}";
            $client->put($endpoint, [
                'is_read' => true,
                'status' => 'read',
                'read_at' => date('c')
            ]);
            echo json_encode(['success' => true]);
            break;
        }
        case 'dismiss': {
            $notificationId = $_POST['notification_id'] ?? null;
            if (!$notificationId) {
                echo json_encode(['success' => false, 'error' => 'Missing notification ID']);
                exit;
            }
            $endpoint = "notifications?id=eq.{$notificationId}&{$scopeFilter}";
            $client->put($endpoint, ['status' => 'dismissed']);
            echo json_encode(['success' => true]);
            break;
        }
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unsupported action']);
            break;
    }
} catch (Exception $e) {
    ErrorHandler::handleException($e, 'notifications.php');
}
