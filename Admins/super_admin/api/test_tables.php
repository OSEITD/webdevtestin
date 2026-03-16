<?php
session_start();
require_once 'supabase-client.php';

// Set up error handling to return JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    // Test connection and verify table existence
    $tableTests = [
        'parcels' => ['id', 'created_at', 'status'],
        'companies' => ['id', 'revenue'],
        'all_users' => ['id', 'is_active'],
        'outlets' => ['id', 'status', 'performance_rating']
    ];

    $results = [];
    
    foreach ($tableTests as $table => $columns) {
        try {
            $data = callSupabaseWithServiceKey($table, 'GET', [
                'select' => 'id',
                'limit' => 1
            ]);
            $results[$table] = [
                'exists' => true,
                'error' => null
            ];
        } catch (Exception $e) {
            $results[$table] = [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}