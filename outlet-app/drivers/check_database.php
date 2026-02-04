<?php
require_once '../includes/OutletAwareSupabaseHelper.php';

session_start();
$_SESSION['company_id'] = '1';

try {
    $supabase = new OutletAwareSupabaseHelper();
    
    echo "<h2>Available Trip Stops</h2>";
    $stops = $supabase->get('trip_stops', 'select=*&limit=10');
    
    if (empty($stops)) {
        echo "<p>No trip stops found. Let's check trips table:</p>";
        
        $trips = $supabase->get('trips', 'select=*&limit=5');
        echo "<h3>Available Trips:</h3>";
        echo "<pre>" . json_encode($trips, JSON_PRETTY_PRINT) . "</pre>";
        
        $outlets = $supabase->get('outlets', 'select=*&limit=5');
        echo "<h3>Available Outlets:</h3>";
        echo "<pre>" . json_encode($outlets, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<pre>" . json_encode($stops, JSON_PRETTY_PRINT) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<h3>Error:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}