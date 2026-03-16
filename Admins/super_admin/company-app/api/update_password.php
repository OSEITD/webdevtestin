<?php
header('Content-Type: application/json');
require_once __DIR__ . '/supabase-client.php';
require_once __DIR__ . '/error-handler.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id'])) { 
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
    echo json_encode(['success' => false, 'error' => 'Missing fields']); 
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
    $userId = $_SESSION['id'];
    $userEmail = $_SESSION['user_email'] ?? $_SESSION['email'] ?? null;

    // Debug: log all session data to diagnose missing email
    error_log("Session data for password update: " . print_r($_SESSION, true));
    error_log("Extracted userId: {$userId}, userEmail: {$userEmail}");

    if (!$userEmail) {
        throw new Exception('User email not found in session. Available keys: ' . implode(', ', array_keys($_SESSION)));
    }

    // Verify current password by attempting to sign in
    error_log("Attempting to verify current password for user: {$userEmail}");
    $supabase = new SupabaseClient();
    
    $signInResult = $supabase->signIn($userEmail, $currentPassword);
    error_log("Sign in result: " . print_r($signInResult, true));

    if (!is_array($signInResult) || !isset($signInResult['access_token'])) {
        throw new Exception('Current password is incorrect');
    }

    $accessToken = $signInResult['access_token'];

    // Call Supabase Auth API to update password
    // Using the service role key for password update operations
    $supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
    $supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';
    $serviceRoleKey = getenv('SUPABASE_SERVICE_ROLE');
    
    if ($serviceRoleKey) {
        // Preferred: use admin API with service role
        $updateUrl = "{$supabaseUrl}/auth/v1/admin/users/{$userId}";
        $authHeader = "Authorization: Bearer {$serviceRoleKey}";
        error_log('Password update using service role key (admin API)');
    } else {
        // Fallback: use user endpoint with access token
        $updateUrl = "{$supabaseUrl}/auth/v1/user";
        $authHeader = "Authorization: Bearer {$accessToken}";
        error_log('Password update using access token (user endpoint)');
    }

    $updatePayload = json_encode(['password' => $newPassword]);
    
    error_log("Calling Supabase Auth API to update password at: {$updateUrl}");
    
    $headers = [
        $authHeader,
        'apikey: ' . $supabaseAnonKey,
        'Content-Type: application/json'
    ];

    error_log("Request headers: " . print_r($headers, true));
    
    $ch = curl_init($updateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updatePayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    // Capture verbose output
    $verboseHandle = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verboseHandle);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    rewind($verboseHandle);
    $verboseLog = stream_get_contents($verboseHandle);
    fclose($verboseHandle);
    
    curl_close($ch);

    error_log("Auth API response code: {$httpCode}");
    error_log("Auth API response: {$response}");
    error_log("Verbose cURL log: {$verboseLog}");

    if ($curlError) {
        throw new Exception("cURL error: {$curlError}");
    }

    if ($httpCode >= 400) {
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['error_description'] ?? $responseData['message'] ?? $response;
        throw new Exception("Password update failed (HTTP {$httpCode}): {$errorMsg}");
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully'
    ]);

} catch (Exception $e) {
    error_log("Password update error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
