<?php
require_once __DIR__ . '/includes/env.php';

$supabaseUrl = EnvLoader::get('SUPABASE_URL', 'https://xerpchdsykqafrsxbqef.supabase.co');
$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E');

$email = 'junior@nestxpress.com';

// Check if user exists in profiles
$profileUrl = "$supabaseUrl/rest/v1/profiles?select=*&email=eq." . urlencode($email);

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "apikey: $supabaseKey\r\n"
    ]
]);

$profileData = file_get_contents($profileUrl, false, $context);
$profiles = json_decode($profileData, true);

echo "<h2>Profile Check for: $email</h2>";
echo "<pre>";
print_r($profiles);
echo "</pre>";

if (!empty($profiles)) {
    $profile = $profiles[0];
    echo "<h3>Current Role: " . ($profile['role'] ?? 'NULL/EMPTY') . "</h3>";
    echo "<h3>User ID: " . ($profile['id'] ?? 'NULL') . "</h3>";
} else {
    echo "<h3>No profile found</h3>";
}
?>
