<?php

header('Content-Type: application/json');

try {
    
    $config = require __DIR__ . '/../config.php';
    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key'];
    
    if (!$supabaseUrl || !$supabaseKey) {
        throw new Exception('Supabase configuration missing');
    }
    
    
    $url = "$supabaseUrl/rest/v1/push_subscriptions?select=user_role,is_active,created_at";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to fetch subscriptions: HTTP $httpCode");
    }
    
    $stats = json_decode($response, true);
    if (!$stats) {
        throw new Exception("Invalid JSON response from Supabase");
    }
    
    $health = [
        'timestamp' => date('Y-m-d H:i:s'),
        'total_subscriptions' => count($stats),
        'active_subscriptions' => 0,
        'inactive_subscriptions' => 0,
        'old_subscriptions' => 0,
        'by_role' => ['driver' => 0, 'outlet_manager' => 0, 'customer' => 0, 'sender' => 0, 'receiver' => 0],
        'recent_410_errors' => 0
    ];
    
    $now = time();
    $ninetyDaysAgo = $now - (90 * 24 * 60 * 60);
    
    foreach ($stats as $sub) {
        $role = $sub['user_role'];
        if ($sub['is_active']) {
            $health['active_subscriptions']++;
            if (isset($health['by_role'][$role])) {
                $health['by_role'][$role]++;
            } else {
                $health['by_role'][$role] = 1;
            }
        } else {
            $health['inactive_subscriptions']++;
        }
        
        $createdTime = strtotime($sub['created_at']);
        if ($createdTime < $ninetyDaysAgo) {
            $health['old_subscriptions']++;
        }
    }
    
    
    $logFile = '../logs/push_notifications.log';
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        $health['recent_410_errors'] = substr_count($logs, '410 Gone');
    }
    
    
    $health['status'] = 'healthy';
    if ($health['recent_410_errors'] > 10) {
        $health['status'] = 'warning';
    }
    if ($health['active_subscriptions'] === 0) {
        $health['status'] = 'critical';
    }
    
    echo json_encode($health, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to check push notification health',
        'message' => $e->getMessage()
    ]);
}
?>