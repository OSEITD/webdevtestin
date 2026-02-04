<?php

header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/../config.php';
    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key'];
    
    if (!$supabaseUrl || !$supabaseKey) {
        throw new Exception('Supabase configuration missing');
    }

    $expiredSubscriptionIds = [
        '6a9ac153-6233-4eda-826b-c37019d5e905',
        '27a37709-8433-4872-bd8f-8530cd492f47',
        '91f75ee5-581b-44ce-a674-44a9d31d1813',
        'd3416f80-9fa6-4e30-9a4f-1c1858b9db97',
        'a2496b06-8455-4124-bea5-8d91421a9e9b',
        'a14ea838-4648-4da3-8989-21bcc9547a04',
        'ebb9fa83-b82d-4fbf-8ab9-2466ec17b358'
    ];
    
    $results = [];
    $successCount = 0;
    
    foreach ($expiredSubscriptionIds as $subId) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?id=eq.$subId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['is_active' => false]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $successCount++;
            $results[] = [
                'subscription_id' => $subId,
                'status' => 'marked_inactive',
                'success' => true
            ];
            error_log("Marked subscription $subId as inactive");
        } else {
            $results[] = [
                'subscription_id' => $subId,
                'status' => 'failed',
                'success' => false,
                'http_code' => $httpCode,
                'response' => $response
            ];
            error_log("Failed to mark subscription $subId as inactive: HTTP $httpCode");
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_processed' => count($expiredSubscriptionIds),
        'marked_inactive' => $successCount,
        'failed' => count($expiredSubscriptionIds) - $successCount,
        'results' => $results
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
