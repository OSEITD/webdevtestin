<?php
require __DIR__ . '/api/supabase-client.php';
require __DIR__ . '/api/session-helper.php';
SessionHelper::initializeSecureSession();
$supabase = new SupabaseClient();

$token = $_SESSION['access_token'] ?? null;
if (!$token) {
    die("No auth token in session.\n");
}

echo "Token exists. Attempting to fetch user profile...\n";

// Decode JWT to see what auth.uid() is
$parts = explode('.', $token);
if (count($parts) === 3) {
    $payload = json_decode(base64_decode($parts[1]), true);
    echo "JWT Payload:\n";
    print_r($payload);
    
    $uid = $payload['sub'] ?? null;
    echo "\nExtracted UID: $uid\n";
    
    if ($uid) {
        // Query profiles with this UID
        $profile = $supabase->getWithToken("profiles?id=eq.{$uid}", $token);
        echo "\nProfile Data:\n";
        print_r($profile);
        
        // Also try querying companies directly just in case auth.uid() IS the company_id
        $company = $supabase->getWithToken("companies?id=eq.{$uid}", $token);
        echo "\nCompany Data (if uid is company_id):\n";
        print_r($company);
    }
} else {
    echo "Invalid JWT format.\n";
}
