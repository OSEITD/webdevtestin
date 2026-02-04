<?php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;
    $userId = $input['user_id'] ?? null;
    
    if (!$sessionId || !$userId) {
        throw new Exception('Session ID and User ID required');
    }
    
    
    $config = require __DIR__ . '/../config.php';
    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key'];
    
    
    $url = "$supabaseUrl/rest/v1/push_subscriptions";
    $data = json_encode(['is_active' => false]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$url?user_id=eq.$userId&is_active=eq.true");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 204 || $httpCode === 200) {
        echo json_encode([
            'success' => true,
            'message' => 'Session marked inactive successfully'
        ]);
    } else {
        error_log("Failed to mark session inactive: HTTP $httpCode - $response");
        echo json_encode([
            'success' => false,
            'message' => 'Failed to cleanup session'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Session cleanup error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>