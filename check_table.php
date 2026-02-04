<?php
require_once 'customer-app/includes/supabase.php';

try {
    // Get Supabase client
    $supabase = getSupabaseClient();

    // Check if table exists by trying to select from it
    $response = $supabase->from('payment_transactions')->select('*')->limit(1)->execute();

    if ($response->status === 200) {
        echo "Table exists and is accessible\n";

        // Get table structure by selecting all columns
        $structureResponse = $supabase->from('payment_transactions')->select('*')->limit(0)->execute();

        if ($structureResponse->status === 200) {
            echo "Table structure retrieved successfully\n";
        } else {
            echo "Could not retrieve table structure\n";
        }
    } else {
        echo "Table does not exist or is not accessible\n";
        echo "Status: " . $response->status . "\n";
        echo "Error: " . ($response->error ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>