<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
require_once __DIR__ . '/../includes/env.php';
// Extra security headers
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com https://fonts.googleapis.com; connect-src 'self';");

// Supabase config - loaded from .env
$supabaseUrl = EnvLoader::get('SUPABASE_URL');
$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if ($email) {
        // Construct the redirect URL for the reset page
        // Ensure this path matches the actual location of reset_password.php
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        // Build a redirect URL based on the current script location to avoid hard-coded project path
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $redirectUrl = "$protocol://$host" . $scriptDir . '/reset_password.php';
        
        $url = "$supabaseUrl/auth/v1/recover";
        
        $data = json_encode([
            'email' => $email,
            'redirect_to' => $redirectUrl
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Content-Type: application/json"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Supabase returns 200 OK even if the email doesn't exist (for security)
        if ($httpCode === 200) {
            $message = "If an account exists with this email, password reset instructions have been sent.";
        } else {
            $respData = json_decode($response, true);
            $errorMsg = $respData['msg'] ?? ($respData['error_description'] ?? 'Failed to send reset email.');
            $error = "Error ($httpCode): " . $errorMsg;
            
            // Detailed debugging logging
            error_log("Supabase Recovery Error:");
            error_log("URL: " . $url);
            error_log("Redirect URL sent: " . $redirectUrl);
            error_log("HTTP Code: " . $httpCode);
            error_log("Response: " . $response);

            if ($httpCode === 422 || $httpCode === 400) {
                 $error .= " <br><small>Hint: Check if the Redirect URL is whitelisted in Supabase Authentication settings.</small>";
            } elseif ($httpCode === 429) {
                 $error .= " <br><small>Hint: Too many requests. Wait a bit before trying again.</small>";
            }
        }
    } else {
        $error = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password</title>
  <meta name="theme-color" content="#2e0b3f">
  <!-- Reusing login styles -->
  <link rel="stylesheet" href="../company-app/assets/css/login-styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
      /* Additional styles specific to this page if needed */
      .back-link {
          display: block;
          text-align: center;
          margin-top: 15px;
          color: #fff; /* Assuming dark background or appropriate contrast */
          text-decoration: none;
      }
      .back-link:hover {
          text-decoration: underline;
      }
      .message-box {
          padding: 10px;
          margin-bottom: 15px;
          border-radius: 5px;
          text-align: center;
      }
      .success {
          background-color: #d4edda;
          color: #155724;
      }
      .error {
          background-color: #f8d7da;
          color: #721c24;
      }
  </style>
  <meta name="robots" content="noindex, nofollow">
</head>
<body>
    <div style="background:#fff3cd;color:#856404;padding:10px 20px;border-radius:6px;margin:16px auto;max-width:500px;text-align:center;font-size:1rem;">
      <strong>Security Notice:</strong> For your protection, only enter your email on this official site. This page is not indexed by search engines.
    </div>
  <div class="container">
    <img src="../company-app/assets/images/logo.png" alt="Logo for WebDev Technologies" class="logo">
    <div class="form-box">
      
      <h2>Forgot Password</h2>
      <p style="text-align: center; margin-bottom: 2rem; color: #666;">Enter your email to receive password reset instructions.</p>

      <?php if (!empty($message)) : ?>
        <div class="message-box success">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)) : ?>
        <div class="message-box error">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <div class="input-container"><i class="fa fa-envelope"></i>
          <input type="email" name="email" placeholder="Email Address" required>
        </div>

        <div>
          <button type="submit" class="btn">Send Reset Link</button>
        </div>
        
        <a href="login.php" class="back-link">Back to Login</a>
      </form>
    </div>
  </div>
</body>
</html>
