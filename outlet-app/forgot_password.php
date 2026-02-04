<?php
session_start();

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limiter.php';

SecurityHeaders::apply();

$supabaseUrl = EnvLoader::get('SUPABASE_URL', 'https://xerpchdsykqafrsxbqef.supabase.co');
$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');

$error = '';
$success = '';
$step = 'email'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = RateLimiter::getClientIdentifier();
    RateLimiter::check($clientId, 3, 300); 
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'send_reset') {
            $email = trim($_POST['email']);
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address";
            } else {
                
                $resetUrl = "$supabaseUrl/auth/v1/recover";
                $payload = json_encode([
                    'email' => $email
                ]);
                
                $ch = curl_init($resetUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "apikey: $supabaseKey",
                    "Content-Type: application/json"
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $success = "If an account exists with this email, you will receive a password reset link shortly. Please check your email.";
                } else {
                    
                    $success = "If an account exists with this email, you will receive a password reset link shortly. Please check your email.";
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
    <title>Forgot Password - Outlet Management</title>
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
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            text-decoration: underline;
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
                <h1 class="brand-title">Forgot Password?</h1>
                <p class="brand-tagline">Enter your email address and we'll send you a password reset link.</p>
            </div>
            
            <div class="form-box">
                <h2>Reset Password</h2>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($success)): ?>
                <form action="forgot_password.php" method="POST">
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="action" value="send_reset">
                    
                    <div class="input-container">
                        <i class="fa fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <button type="submit" class="btn">Send Reset Link</button>
                </form>
                <?php endif; ?>
                
                <div class="back-link">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
