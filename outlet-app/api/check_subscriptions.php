<?php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $config = require __DIR__ . '/../config.php';
    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key'];
    
    if (!$supabaseUrl || !$supabaseKey) {
        throw new Exception('Supabase configuration missing');
    }

    $userId = $_GET['user_id'] ?? null;
    $userRole = $_GET['user_role'] ?? null;
    $companyId = $_GET['company_id'] ?? $_SESSION['company_id'] ?? null;
    
    $filters = ['is_active=eq.true'];
    
    if ($userId) {
        $filters[] = "user_id=eq.$userId";
    }
    
    if ($userRole) {
        $filters[] = "user_role=eq.$userRole";
    }
    
    if ($companyId) {
        $filters[] = "company_id=eq.$companyId";
    }
    
    $queryString = implode('&', $filters);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/push_subscriptions?$queryString&select=id,user_id,user_role,endpoint,created_at,is_active");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Failed to fetch subscriptions');
    }
    
    $subscriptions = json_decode($response, true);
    
    $enrichedSubscriptions = [];
    foreach ($subscriptions as $sub) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$supabaseUrl/rest/v1/profiles?id=eq.{$sub['user_id']}&select=full_name,email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        
        $userResponse = curl_exec($ch);
        curl_close($ch);
        
        $userData = json_decode($userResponse, true);
        
        $enrichedSubscriptions[] = [
            'subscription_id' => $sub['id'],
            'user_id' => $sub['user_id'],
            'user_name' => $userData[0]['full_name'] ?? 'Unknown',
            'user_email' => $userData[0]['email'] ?? 'Unknown',
            'user_role' => $sub['user_role'],
            'endpoint' => substr($sub['endpoint'], 0, 50) . '...',
            'created_at' => $sub['created_at'],
            'is_active' => $sub['is_active']
        ];
    }
    
    $byRole = [
        'outlet_manager' => [],
        'customer' => [],
        'driver' => []
    ];
    
    foreach ($enrichedSubscriptions as $sub) {
        $role = $sub['user_role'];
        if (isset($byRole[$role])) {
            $byRole[$role][] = $sub;
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_subscriptions' => count($enrichedSubscriptions),
        'by_role' => [
            'outlet_manager' => count($byRole['outlet_manager']),
            'customer' => count($byRole['customer']),
            'driver' => count($byRole['driver'])
        ],
        'subscriptions' => $enrichedSubscriptions,
        'grouped_by_role' => $byRole
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
