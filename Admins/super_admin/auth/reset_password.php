<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Supabase config
$supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accessToken = $_POST['access_token'] ?? '';
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($accessToken)) {
        $error = "Invalid or missing session token. Please try requesting a new password reset link.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        
        $url = "$supabaseUrl/auth/v1/user";
        $data = json_encode(['password' => $newPassword]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $message = "Password updated successfully. <a href='login.php'>Login here</a>";
        } else {
            $respData = json_decode($response, true);
            $error = "Error updating password: " . ($respData['msg'] ?? 'Unknown error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password</title>
  <meta name="theme-color" content="#2e0b3f">
  <link rel="stylesheet" href="../company-app/assets/css/login-styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
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
      .hidden {
          display: none;
      }
  </style>
</head>
<body>
  <div class="container">
    <img src="../company-app/assets/images/logo.png" alt="Logo for WebDev Technologies" class="logo">
    <div class="form-box">
      
      <h2>Reset Password</h2>
      
      <?php if (!empty($message)) : ?>
        <div class="message-box success">
          <?= ($message) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)) : ?>
        <div class="message-box error">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div id="loading-message" class="message-box" style="display:none; color: #666;">Verifying link...</div>
      <div id="invalid-link-message" class="message-box error hidden">Invalid or expired link. Please request a new one.</div>

      <form id="reset-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="hidden">
        <input type="hidden" name="access_token" id="access_token">
        
        <div class="input-container"><i class="fa fa-lock"></i>
          <input type="password" name="password" placeholder="New Password" required minlength="6">
        </div>
        
        <div class="input-container"><i class="fa fa-lock"></i>
          <input type="password" name="confirm_password" placeholder="Confirm Password" required minlength="6">
        </div>

        <div>
          <button type="submit" class="btn">Update Password</button>
        </div>
        
        <div style="margin-top: 15px; text-align: center;">
            <a href="login.php" style="color: white; text-decoration: none;">Back to Login</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // If we already handled the POST, show the form if there was an error, or hide it if success
        const serverMessage = "<?= addslashes($message) ?>";
        const serverError = "<?= addslashes($error) ?>";
        
        // If we just submitted and got success, don't need to parse hash again
        if (serverMessage) {
            return; 
        }

        const form = document.getElementById('reset-form');
        const loadingMsg = document.getElementById('loading-message');
        const invalidMsg = document.getElementById('invalid-link-message');
        const tokenInput = document.getElementById('access_token');
        
        // Check for hash parameters (from Supabase redirect)
        const hash = window.location.hash.substring(1);
        const params = new URLSearchParams(hash);
        const accessToken = params.get('access_token');
        const type = params.get('type'); // should be 'recovery' usually

        if (accessToken) {
            tokenInput.value = accessToken;
            form.classList.remove('hidden');
        } else if (serverError) {
             // If we had a server error (e.g. password mismatch), we might have lost the hash
             // Ideally we should preserve the token.
             // But usually the form will re-render with the POSTed values?
             // Actually, PHP re-renders the page. The hash is NOT sent to server.
             // BUT, the browser usually preserves the hash on the URL after a POST to the same URL?
             // Not guaranteed.
             
             // If PHP sees a POST, it processes it.
             // If valid, we are good.
             // If invalid (e.g. passwords don't match), we re-render.
             // We need the access_token again. 
             // We put it in a hidden field, so the retried POST will have it.
             // PHP should repopulate the hidden input if we want to be nice, 
             // but here I didn't add 'value' attribute to the hidden input in PHP.
             
             // Let's rely on the user not messing up the passwords, or if they do, 
             // hopefully the browser kept the hash or we can re-read it if it's there.
             
             // Simple fallback: check if we have value in POST
             const postedToken = "<?= $_POST['access_token'] ?? '' ?>";
             if (postedToken) {
                 tokenInput.value = postedToken;
                 form.classList.remove('hidden');
             } else {
                 if (!location.hash) {
                     invalidMsg.classList.remove('hidden');
                 }
             }
        } else {
            // No token and no server interaction yet
            invalidMsg.classList.remove('hidden');
        }
    });
  </script>
</body>
</html>
