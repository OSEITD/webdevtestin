<?php

require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';
$supabaseUrl = $config['supabase']['url'];
$supabaseKey = $config['supabase']['service_role_key'];

$logFile = __DIR__ . '/../logs/push_cleanup.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

try {
    logMessage("=== Starting Push Subscription Cleanup ===");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?is_active=eq.true&select=id,endpoint,user_id,user_role,created_at");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $subscriptions = json_decode($response, true);
    logMessage("Found " . count($subscriptions) . " active subscriptions");
    
    $markedInactive = 0;
    $oldSubscriptions = 0;
    
    $ninetyDaysAgo = date('Y-m-d', strtotime('-90 days'));
    
    foreach ($subscriptions as $sub) {
        $createdDate = substr($sub['created_at'], 0, 10);
        
        if ($createdDate < $ninetyDaysAgo) {
            $oldSubscriptions++;
            logMessage("Old subscription: {$sub['id']} (created: $createdDate, user: {$sub['user_id']}, role: {$sub['user_role']})");
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?id=eq.{$sub['id']}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['is_active' => false]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json"
            ]);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $markedInactive++;
                logMessage("✓ Marked inactive: {$sub['id']}");
            } else {
                logMessage("✗ Failed to mark inactive: {$sub['id']} (HTTP $httpCode)");
            }
        }
    }
    
    logMessage("=== Cleanup Complete ===");
    logMessage("Old subscriptions found: $oldSubscriptions");
    logMessage("Marked inactive: $markedInactive");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    exit(1);
}

exit(0);
