<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../../includes/OutletAwareSupabaseHelper.php';
try {
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['current_password']) || !isset($input['new_password'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'];
    $userId = $_SESSION['user_id'];
    
    
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters long']);
        exit;
    }
    
    
    $supabase = new OutletAwareSupabaseHelper();
    
    
    
    
    
    
    $supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
    $supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
    
    
    $profileData = $supabase->get('profiles', "id=eq.{$userId}", 'email,username');
    
    error_log("Profile query result: " . json_encode($profileData));
    error_log("User ID: " . $userId);
    
    if (empty($profileData)) {
        error_log("No profile data returned for user: {$userId}");
        echo json_encode(['success' => false, 'error' => 'Profile not found']);
        exit;
    }
    
    if (!isset($profileData[0])) {
        error_log("Profile data is not an array or empty: " . json_encode($profileData));
        echo json_encode(['success' => false, 'error' => 'Invalid profile data']);
        exit;
    }
    
    
    $email = $profileData[0]['email'] ?? $profileData[0]['username'] ?? null;
    
    if (!$email) {
        error_log("No email or username found in profile: " . json_encode($profileData[0]));
        echo json_encode(['success' => false, 'error' => 'Email not found in profile']);
        exit;
    }
    
    error_log("Using email/username for authentication: " . $email);
    
    
    $signInUrl = "{$supabaseUrl}/auth/v1/token?grant_type=password";
    $signInData = [
        'email' => $email,
        'password' => $currentPassword
    ];
    
    $ch = curl_init($signInUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($signInData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: {$supabaseKey}",
        "Content-Type: application/json"
    ]);
    
    $signInResponse = curl_exec($ch);
    $signInHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("Sign-in attempt - HTTP Code: {$signInHttpCode}, Response: " . substr($signInResponse, 0, 200));
    
    if ($curlError) {
        error_log("cURL error during sign-in: " . $curlError);
        echo json_encode(['success' => false, 'error' => 'Connection error']);
        exit;
    }
    
    if ($signInHttpCode !== 200) {
        $errorResponse = json_decode($signInResponse, true);
        $errorMsg = $errorResponse['error_description'] ?? $errorResponse['msg'] ?? 'Current password is incorrect';
        error_log("Sign-in failed: " . $errorMsg);
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }
    
    $signInResult = json_decode($signInResponse, true);
    
    if (!isset($signInResult['access_token'])) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
        exit;
    }
    
    $accessToken = $signInResult['access_token'];
    
    
    $updatePasswordUrl = "{$supabaseUrl}/auth/v1/user";
    $updateData = [
        'password' => $newPassword
    ];
    
    $ch = curl_init($updatePasswordUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: {$supabaseKey}",
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json"
    ]);
    
    $updateResponse = curl_exec($ch);
    $updateHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("Password update - HTTP Code: {$updateHttpCode}, Response: " . substr($updateResponse, 0, 200));
    
    if ($curlError) {
        error_log("cURL error during password update: " . $curlError);
        echo json_encode(['success' => false, 'error' => 'Connection error during update']);
        exit;
    }
    
    if ($updateHttpCode === 200) {
        
        $updateResult = $supabase->update('profiles', [
            'password_last_updated' => date('Y-m-d H:i:s')
        ], "id=eq.{$userId}");
        
        error_log("Password updated successfully for user: {$userId}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    } else {
        $errorResult = json_decode($updateResponse, true);
        $errorMessage = $errorResult['error_description'] ?? $errorResult['msg'] ?? $errorResult['message'] ?? 'Failed to update password';
        
        error_log("Password update failed: " . $errorMessage . " | Response: " . $updateResponse);
        
        echo json_encode([
            'success' => false,
            'error' => $errorMessage
        ]);
    }
    
} catch (Exception $e) {
    error_log("Password change error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while updating password'
    ]);
}
