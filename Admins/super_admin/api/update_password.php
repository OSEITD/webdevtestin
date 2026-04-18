<?php
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$currentPassword = $input['current'] ?? '';
$newPassword = $input['new'] ?? '';
$confirmPassword = $input['confirm'] ?? '';

// Validation
if (!$currentPassword || !$newPassword || !$confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'New password confirmation does not match']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters']);
    exit;
}

try {
    global $supabaseUrl, $supabaseKey, $supabaseServiceKey;

    $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? $_SESSION['email'] ?? null;

    if (!$userEmail) {
        throw new Exception('User email not found in session');
    }

    // Verify current password by attempting to sign in via Supabase Auth
    $signInUrl = "{$supabaseUrl}/auth/v1/token?grant_type=password";
    $signInPayload = json_encode([
        'email' => $userEmail,
        'password' => $currentPassword
    ]);

    $ch = curl_init($signInUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $signInPayload,
        CURLOPT_HTTPHEADER => [
            "apikey: {$supabaseKey}",
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => (getenv('APP_ENV') ?: 'production') === 'production',
        CURLOPT_SSL_VERIFYHOST => ((getenv('APP_ENV') ?: 'production') === 'production') ? 2 : 0,
    ]);

    $signInResponse = curl_exec($ch);
    $signInHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $signInData = json_decode($signInResponse, true);

    if ($signInHttpCode >= 400 || !isset($signInData['access_token'])) {
        throw new Exception('Wrong current password entered');
    }

    // Current password verified — now update to the new password
    // Use service role key if available for admin API, otherwise user token
    if ($supabaseServiceKey && $userId) {
        $updateUrl = "{$supabaseUrl}/auth/v1/admin/users/{$userId}";
        $authHeader = "Authorization: Bearer {$supabaseServiceKey}";
    } else {
        $updateUrl = "{$supabaseUrl}/auth/v1/user";
        $authHeader = "Authorization: Bearer {$signInData['access_token']}";
    }

    $ch = curl_init($updateUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode(['password' => $newPassword]),
        CURLOPT_HTTPHEADER => [
            $authHeader,
            "apikey: {$supabaseKey}",
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => (getenv('APP_ENV') ?: 'production') === 'production',
        CURLOPT_SSL_VERIFYHOST => ((getenv('APP_ENV') ?: 'production') === 'production') ? 2 : 0,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Connection error: {$curlError}");
    }

    if ($httpCode >= 400) {
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['error_description'] ?? $responseData['message'] ?? $response;
        throw new Exception("Password update failed: {$errorMsg}");
    }

    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);

} catch (Exception $e) {
    error_log("Super admin password update error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
