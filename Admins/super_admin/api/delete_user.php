<?php
// Centralized API initialization
require_once __DIR__ . '/init.php';

// Verify authentication
ErrorHandler::requireAuth('delete_user.php');
ErrorHandler::requireMethod('POST', 'delete_user.php');

require_once __DIR__ . '/supabase-client.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $userEmail = $input['email'] ?? null;
    $userId = $input['id'] ?? null;
    $userRole = $input['role'] ?? null;

    if (!$userEmail || !$userId || !$userRole) {
        throw new Exception('Missing user data.');
    }

    $tableName = '';
    $idColumn = 'id'; // Default ID column name

    switch ($userRole) {
        case 'company':
            $tableName = 'companies';
            break;
        case 'outlet':
            $tableName = 'outlets';
            break;
        case 'driver':
            $tableName = 'drivers';
            break;
        case 'customer':
            $tableName = 'customers';
            break;
        default:
            throw new Exception('Invalid user role.');
    }

    global $supabaseUrl, $supabaseServiceKey;
    $client = new SupabaseClient($supabaseUrl, $supabaseServiceKey);

    // Perform deletion
    $response = $client->delete($tableName, "{$idColumn}=eq.{$userId}");

    // Check if deletion was successful
    if (empty($response) || (is_array($response) && count($response) === 0)) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
    } else {
        throw new Exception('Failed to delete user: ' . json_encode($response));
    }

} catch (Exception $e) {
    ErrorHandler::handleException($e, 'delete_user.php', 400);
}