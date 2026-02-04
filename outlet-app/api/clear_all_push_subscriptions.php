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
    logMessage("=== CLEARING ALL PUSH SUBSCRIPTIONS FOR VAPID KEY RESET ===");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?select=id,endpoint,user_id,user_role");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to fetch subscriptions: HTTP $httpCode");
    }

    $subscriptions = json_decode($response, true);
    logMessage("Found " . count($subscriptions) . " total subscriptions");

    if (count($subscriptions) > 0) {
        $successCount = 0;
        $failCount = 0;

        foreach ($subscriptions as $sub) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?id=eq.{$sub['id']}");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'is_active' => false,
                'updated_at' => date('c')
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json"
            ]);

            $updateResponse = curl_exec($ch);
            $updateHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($updateHttpCode >= 200 && $updateHttpCode < 300) {
                $successCount++;
                logMessage("✅ Marked subscription {$sub['id']} as inactive");
            } else {
                $failCount++;
                logMessage("❌ Failed to update subscription {$sub['id']}: HTTP $updateHttpCode");
            }
        }

        logMessage("Results: $successCount successful, $failCount failed");
    } else {
        logMessage("No subscriptions to clear");
    }

    logMessage("=== VAPID KEY RESET COMPLETE - Users must re-enable push notifications ===");

} catch (Exception $e) {
    logMessage("❌ ERROR: " . $e->getMessage());
    http_response_code(500);
}
?>