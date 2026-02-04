<?php

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/csrf.php';

SecurityHeaders::apply();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['requires_password_change']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$supabaseUrl = EnvLoader::get('SUPABASE_URL', 'https://xerpchdsykqafrsxbqef.supabase.co');
$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');

$error = '';
$success = '';
$isFirstLogin = isset($_SESSION['requires_password_change']) && $_SESSION['requires_password_change'] === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Please enter and confirm your new password";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!$isFirstLogin && empty($currentPassword)) {
        $error = "Please enter your current password";
    } else {
        
        
        $accessToken = $_SESSION['access_token'] ?? null;
        
        if (!$isFirstLogin && !$accessToken) {
            
            $authUrl = "$supabaseUrl/auth/v1/token?grant_type=password";
            $authPayload = json_encode([
                'email' => $_SESSION['email'],
                'password' => $currentPassword
            ]);

            $ch = curl_init($authUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $authPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Content-Type: application/json"
            ]);
            $authResponse = curl_exec($ch);
            $authHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($authHttpCode >= 400) {
                $error = "Current password is incorrect";
            } else {
                $authData = json_decode($authResponse, true);
                $accessToken = $authData['access_token'];
            }
        }
        
        if ($accessToken && empty($error)) {
            
            $updateUrl = "$supabaseUrl/auth/v1/user";
            $updatePayload = json_encode(['password' => $newPassword]);

            $ch = curl_init($updateUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $updatePayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ]);
            $updateResponse = curl_exec($ch);
            $updateHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($updateHttpCode >= 200 && $updateHttpCode < 300) {
                
                $profileUpdateUrl = "$supabaseUrl/rest/v1/profiles?id=eq." . $_SESSION['user_id'];
                $profileUpdatePayload = json_encode([
                    'password_last_updated' => date('Y-m-d H:i:s')
                ]);

                $ch = curl_init($profileUpdateUrl);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $profileUpdatePayload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $accessToken",
                    "Content-Type: application/json",
                    "Prefer: return=minimal"
                ]);
                curl_exec($ch);
                curl_close($ch);

                $success = "Password updated successfully!";
                
                
                unset($_SESSION['requires_password_change']);
                
                
                $redirectUrl = ($_SESSION['role'] === 'driver') ? 'drivers/dashboard.php' : 'pages/outlet_dashboard.php';
                
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '$redirectUrl';
                    }, 2000);
                </script>";
            } else {
                $error = "Failed to update password. Please try again. (HTTP $updateHttpCode)";
            }
        }
    }
    } 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isFirstLogin ? 'Set Your Password' : 'Change Password' ?></title>
    <link rel="stylesheet" href="css/login-styles.css">
    <style>
        .form-container {
            max-width: 500px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .btn {
            background: #007cba;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn:hover {
            background: #005a87;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            text-align: center;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .requirements {
            background: #e2e3e5;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 14px;
        }
        .welcome-msg {
            background: #d1ecf1;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <?php if ($isFirstLogin): ?>
            <div class="welcome-msg">
                <h2>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>!</h2>
                <p>For security reasons, you must set a new password before accessing your account.</p>
            </div>
        <?php else: ?>
            <h2>Change Password</h2>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php echo CSRF::field(); ?>
            
            <?php if (!$isFirstLogin): ?>
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="new_password"><?= $isFirstLogin ? 'Set New Password' : 'New Password' ?></label>
                <input type="password" id="new_password" name="new_password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="requirements">
                <strong>Password Requirements:</strong>
                <ul>
                    <li>At least 8 characters long</li>
                    <li>Mix of letters and numbers recommended</li>
                    <li>Use a unique password you haven't used before</li>
                </ul>
            </div>

            <button type="submit" class="btn">
                <?= $isFirstLogin ? 'Set Password & Continue' : 'Update Password' ?>
            </button>
        </form>

        <?php if (!$isFirstLogin): ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="pages/outlet_dashboard.php" style="color: #007cba;">← Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword && confirmPassword.length > 0) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#ddd';
            }
        });
    </script>
</body>
</html>