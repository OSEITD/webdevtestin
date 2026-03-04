<?php
require_once __DIR__ . '/../config.php';

echo "Checking notifications in database...\n\n";

try {
    
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');
    
    $client = new \Supabase\CreateClient($supabaseUrl, $supabaseKey);
    $db = $client->db;
    
    
    echo "Fetching recent notifications...\n";
    $response = $db
        ->from('notifications')
        ->select('*')
        ->order('created_at', ['ascending' => false])
        ->limit(5)
        ->execute();
        
    if ($response->status === 200) {
        $notifications = $response->data;
        echo "Found " . count($notifications) . " recent notifications:\n\n";
        
        foreach ($notifications as $notification) {
            echo "ID: " . $notification['id'] . "\n";
            echo "Title: " . $notification['title'] . "\n";
            echo "Message: " . $notification['message'] . "\n";
            echo "Type: " . $notification['notification_type'] . "\n";
            echo "Created: " . $notification['created_at'] . "\n";
            echo "Read: " . ($notification['is_read'] ? 'Yes' : 'No') . "\n";
            echo "---\n";
        }
    } else {
        echo "Failed to fetch notifications. Status: " . $response->status . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nDone.\n";
?>
