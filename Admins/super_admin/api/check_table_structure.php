<?php
require_once 'supabase-client.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Get table information
    $result = callSupabaseWithServiceKey('all_users', 'GET', [
        'select' => '*',
        'limit' => 1
    ]);
    
    echo json_encode([
        'success' => true,
        'columns' => array_keys((array)$result[0] ?? []),
        'sample' => $result[0] ?? null
    ]);
} catch (Exception $e) {
    error_log("Error checking table structure: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>