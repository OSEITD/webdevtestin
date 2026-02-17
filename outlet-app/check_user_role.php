<?php
// Diagnostic tool to check user role in database
require_once __DIR__ . '/includes/env.php';

$supabaseUrl = EnvLoader::get('SUPABASE_URL', 'https://xerpchdsykqafrsxbqef.supabase.co');
$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E');

$email = $_GET['email'] ?? 'junior@nestxpress.com';

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
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Role Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #4A1C40; border-bottom: 2px solid #4A1C40; padding-bottom: 10px; }
        .info-box { background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .role { font-size: 24px; font-weight: bold; color: #2c5aa0; padding: 10px; background: #e3f2fd; border-radius: 5px; }
        .error { color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 5px; }
        .success { color: #388e3c; background: #e8f5e9; padding: 10px; border-radius: 5px; }
        pre { background: #263238; color: #aed581; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .form { margin: 20px 0; }
        input[type="text"] { padding: 8px; width: 300px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 8px 20px; background: #4A1C40; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #6d2a5f; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 User Role Diagnostic Tool</h2>
        
        <div class="form">
            <form method="GET">
                <label>Email Address:</label><br>
                <input type="text" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="user@example.com">
                <button type="submit">Check Role</button>
            </form>
        </div>

        <h3>Profile Check for: <strong><?php echo htmlspecialchars($email); ?></strong></h3>

        <?php if (!empty($profiles)): ?>
            <?php $profile = $profiles[0]; ?>
            
            <div class="success">
                ✅ Profile Found in Database
            </div>

            <div class="info-box">
                <strong>User ID:</strong> <?php echo htmlspecialchars($profile['id'] ?? 'NULL'); ?><br>
                <strong>Full Name:</strong> <?php echo htmlspecialchars($profile['full_name'] ?? 'NULL'); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars($profile['email'] ?? 'NULL'); ?><br>
                <strong>Company ID:</strong> <?php echo htmlspecialchars($profile['company_id'] ?? 'NULL'); ?><br>
                <strong>Outlet ID:</strong> <?php echo htmlspecialchars($profile['outlet_id'] ?? 'NULL'); ?>
            </div>

            <h3>🎭 Current Role:</h3>
            <div class="role">
                <?php 
                $role = $profile['role'] ?? 'NULL/EMPTY'; 
                echo htmlspecialchars($role);
                
                // Show expected vs actual
                if ($role === 'driver') {
                    echo ' <span style="color: green;">✓ CORRECT (should redirect to drivers/dashboard.php)</span>';
                } elseif (in_array($role, ['outlet_manager', 'outlet_admin'])) {
                    echo ' <span style="color: blue;">ℹ MANAGER/ADMIN (can access pages/outlet_dashboard.php)</span>';
                } else {
                    echo ' <span style="color: orange;">⚠ UNKNOWN ROLE</span>';
                }
                ?>
            </div>

            <?php if ($role === 'NULL/EMPTY' || empty($role)): ?>
                <div class="error">
                    <strong>⚠️ PROBLEM:</strong> Role is empty/null in database! This user will be rejected at login.
                    <br><br>
                    <strong>Fix:</strong> Update the role in Supabase profiles table:
                    <pre>UPDATE profiles SET role = 'driver' WHERE email = '<?php echo htmlspecialchars($email); ?>';</pre>
                </div>
            <?php endif; ?>

            <h3>📋 Full Profile Data (JSON):</h3>
            <pre><?php echo json_encode($profile, JSON_PRETTY_PRINT); ?></pre>

        <?php else: ?>
            <div class="error">
                ❌ No profile found for this email in the database.
            </div>
            <p>This user has authenticated (can login to Supabase) but has no profile record.</p>
            <p>A profile will be auto-created on first login based on the URL parameter or email heuristics.</p>
        <?php endif; ?>

        <hr>
        <h3>🧪 Test Common Users:</h3>
        <p>
            <a href="?email=junior@nestxpress.com">junior@nestxpress.com</a> | 
            <a href="?email=manager@nestxpress.com">manager@nestxpress.com</a> | 
            <a href="?email=admin@nestxpress.com">admin@nestxpress.com</a>
        </p>
    </div>
</body>
</html>

