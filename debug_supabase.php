<?php
require_once 'outlet-app/includes/supabase-client.php';

try {
    $supabase = getSupabaseClient();

    echo "🔍 Testing Supabase client methods...\n";

    echo "Rest URL: " . $supabase->getRestUrl() . "\n";
    echo "API Key: " . substr($supabase->getApiKey(), 0, 20) . "...\n";
    echo "Full URL: " . $supabase->getUrl() . "\n";

    // Test a simple select query
    echo "\n🧪 Testing select query...\n";
    $query = $supabase->from('payment_transactions')->select('count')->limit(1);
    $response = $query->execute();

    echo "Query response: " . json_encode($response) . "\n";

} catch (Exception $e) {
    echo " Exception: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>