<?php
/**
 * Utility script to update all companies with NULL currency to match global currency
 * Run this once to sync all companies to use the global currency setting
 */

session_start();
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/supabase-client.php';

// Verify super_admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    // Get global currency from system_settings
    $settings = callSupabase('system_settings?select=global_currency&limit=1');
    
    if (empty($settings)) {
        throw new Exception('No system settings found');
    }
    
    $globalCurrency = $settings[0]['global_currency'] ?? 'EUR';
    
    // Get all companies with NULL currency
    $companiesWithNull = callSupabase('companies?select=id,company_name&currency=is.null');
    
    if (empty($companiesWithNull)) {
        echo json_encode([
            'success' => true,
            'message' => 'No companies with NULL currency found',
            'updated' => 0,
            'global_currency' => $globalCurrency
        ]);
        exit;
    }
    
    $updateCount = 0;
    
    // Update each company to use global currency
    foreach ($companiesWithNull as $company) {
        try {
            $updateData = ['currency' => $globalCurrency];
            callSupabaseWithServiceKey(
                "companies?id=eq.{$company['id']}", 
                'PATCH', 
                $updateData
            );
            $updateCount++;
            error_log("Updated company {$company['id']} ({$company['company_name']}) to currency {$globalCurrency}");
        } catch (Exception $e) {
            error_log("Failed to update company {$company['id']}: " . $e->getMessage());
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Updated {$updateCount} companies to currency {$globalCurrency}",
        'updated' => $updateCount,
        'global_currency' => $globalCurrency
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('update_company_currencies error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
