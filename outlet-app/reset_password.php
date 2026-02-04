<?php
session_start();

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/csrf.php';

SecurityHeaders::apply();

$supabaseUrl = EnvLoader::get('SUPABASE_URL', 'https://xerpchdsykqafrsxbqef.supabase.co');
$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');

$error = '';
$success = '';

$token = $_GET['token'] ?? $_GET['access_token'] ?? '';
$type = $_GET['type'] ?? '';

if (empty($token) || $type !== 'recovery') {
    $error = "Invalid or expired reset link. Please request a new password reset.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($newPassword) || empty($confirmPassword)) {
            $error = "Please fill in all fields";
        } elseif (strlen($newPassword) < 8) {
            $error = "Password must be at least 8 characters long";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Passwords do not match";
        } else {
            
            $updateUrl = "$supabaseUrl/auth/v1/user";
            $payload = json_encode([
                'password' => $newPassword
            ]);
            
            $ch = curl_init($updateUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $token",
                "Content-Type: application/json"
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $responseData = json_decode($response, true);
                $userId = $responseData['user']['id'] ?? null;
                
                if ($userId) {
                    
                    $profileUrl = "$supabaseUrl/rest/v1/profiles?id=eq.$userId";
                    $profilePayload = json_encode([
                        'password_last_updated' => date('Y-m-d H:i:s')
                    ]);
                    
                    $ch = curl_init($profileUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $profilePayload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "apikey: $supabaseKey",
                        "Authorization: Bearer $token",
                        "Content-Type: application/json",
                        "Prefer: return=minimal"
                    ]);
                    
                    curl_exec($ch);
                    curl_close($ch);
                }
                
                $success = "Password reset successful! You can now login with your new password.";
            } else {
                $error = "Failed to reset password. The link may have expired. Please request a new reset link.";
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
    <title>Reset Password - Outlet Management</title>
    <link rel="stylesheet" href="./css/login-styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .success-message {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #16a34a;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .password-requirements {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .password-requirements ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            margin: 4px 0;
            color: #64748b;
        }
    </style>
</head>
<body class="login-body">
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="brand-panel">
                <div class="brand-logo">
                    <img src="img/logo.png" alt="Company logo">
                </div>
                <h1 class="brand-title">Reset Your Password</h1>
                <p class="brand-tagline">Enter your new password below.</p>
            </div>
            
            <div class="form-box">
                <h2>Create New Password</h2>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="login.php" class="btn">Go to Login</a>
                    </div>
                <?php elseif (!empty($error) && empty($token)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="forgot_password.php" class="btn">Request New Reset Link</a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($error)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="password-requirements">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li>At least 8 characters long</li>
                            <li>Mix of letters, numbers, and symbols recommended</li>
                        </ul>
                    </div>
                    
                    <form action="reset_password.php?token=<?= htmlspecialchars($token) ?>&type=recovery" method="POST">
                        <?php echo CSRF::field(); ?>
                        
                        <div class="input-container">
                            <i class="fa fa-lock"></i>
                            <input type="password" name="new_password" placeholder="New Password" required minlength="8">
                        </div>
                        
                        <div class="input-container">
                            <i class="fa fa-lock"></i>
                            <input type="password" name="confirm_password" placeholder="Confirm New Password" required minlength="8">
                        </div>
                        
                        <button type="submit" class="btn">Reset Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
