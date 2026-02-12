<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Configure session handling
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
ini_set('session.gc_maxlifetime', 604800); // 7 days
ini_set('session.cookie_lifetime', 604800); // 7 days

// Determine appropriate cookie params for environment
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
$cookieParams = [
  'lifetime' => 604800, // 7 days (extended from 24 hours)
  'path' => '/', // use root path for consistency across the app
  'domain' => '',
  'secure' => !$isLocalhost, // secure only in production
  'httponly' => true,
  'samesite' => 'Lax'
];
session_set_cookie_params($cookieParams);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Clear session on direct GET visit to login page (not POST)
// This allows users to intentionally visit login.php to see the login form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  error_log("GET request to login page detected. Clearing session to allow fresh login.");
  session_unset();
  session_regenerate_id(true);
}

// Debug session info
error_log("Session ID at start: " . session_id());
error_log("Session path: " . session_save_path());
error_log("Initial session data: " . print_r($_SESSION, true));

// Debug session state
error_log("Session state at login: " . print_r($_SESSION, true));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

// Supabase config
$supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $host = $_SERVER['HTTP_HOST'];
  // Extract subdomain from the current URL path instead of host
  $path = $_SERVER['REQUEST_URI'];
  $pathParts = explode('/', $path);
  // Find 'company-app' in the path and use that as the subdomain
  $subdomain = 'company-app';
  
  error_log("Host: " . $host);
  error_log("Path: " . $path);
  error_log("Using subdomain: " . $subdomain);

  $email = $_POST['email'];
  $password = $_POST['password'];

  // Supabase sign-in endpoint
  $authUrl = "$supabaseUrl/auth/v1/token?grant_type=password";

  $payload = json_encode([
    'email' => $email,
    'password' => $password
  ]);

  $ch = curl_init($authUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $supabaseKey",
    "Content-Type: application/json"
  ]);
  $response = curl_exec($ch);
  curl_close($ch);

  // Log the raw response for debugging
  error_log("Auth Response: " . $response);

  $authData = json_decode($response, true);

  // Defensive checks: ensure we decoded JSON and have an array
  if (!is_array($authData)) {
    error_log("Auth decode failed or returned non-array: " . var_export($response, true));
    $error = "Invalid authentication response from server.";
  } elseif (isset($authData['error'])) {
    error_log("Login Error: " . ($authData['error_description'] ?? 'Unknown error'));
    $error = $authData['error_description'] ?? 'Login failed';
  } else {
    // Getting access token and user info (use null coalescing to avoid undefined index warnings)
    $accessToken = $authData['access_token'] ?? null;
    $refreshToken = $authData['refresh_token'] ?? null; // Store refresh token
    $userId = null;
    if (isset($authData['user']) && is_array($authData['user'])) {
      $userId = $authData['user']['id'] ?? null;
    }

    // Avoid passing null into substr (PHP 8.1+ deprecation)
    error_log("Auth successful - Access Token: " . ($accessToken ? substr($accessToken, 0, 20) . "..." : 'NULL'));
    error_log("User ID: " . ($userId ?? 'NULL'));

    if (!$userId) {
      error_log("No user ID in response");
      $error = "Login succeeded, but user ID was not returned.";
    } else {
      // Getting the user's profile
      $profileUrl = "$supabaseUrl/rest/v1/profiles?select=*&id=eq.$userId";

      $context = stream_context_create([
        'http' => [
          'method' => 'GET',
          'header' => "apikey: $supabaseKey\r\nAuthorization: Bearer $accessToken\r\n"
        ]
      ]);

      // Fetch profile data with error handling
      $profileData = @file_get_contents($profileUrl, false, $context);
      
      if ($profileData === false) {
        $error = "Failed to fetch user profile data";
        return;
      }
      
      $profiles = json_decode($profileData, true);

      if (empty($profiles)) {
        $error = "No profile found for this user";
        return;
      }

      $profile = $profiles[0];
      $userRole = $profile['role'] ?? null;
      
      // Checking if user belongs to this subdomain/company
      $id = $profile['id'] ?? null;
      
      if (empty($id)) {
        $error = "No ID associated with this profile";
        return;
      }

      // Check if user is a super_admin
      if ($userRole === 'super_admin') {
        // Super admin login - no company check needed
        error_log("Super admin login detected for user: " . $id);
        error_log("Session data to set - User ID: " . $id . ", Role: " . $userRole);
        
        // Ensure we have a valid session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear any existing session data
        session_unset();
        
        // Set core session info
        $_SESSION['user_id'] = $profile['id'];
        $_SESSION['email'] = $email;
        $_SESSION['user_fullname'] = $profile['full_name'];
        $_SESSION['role'] = $userRole;
        $_SESSION['access_token'] = $accessToken;
        if ($refreshToken) {
          $_SESSION['refresh_token'] = $refreshToken;
        }
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();

        // Set secure cookie with session ID
        $params = session_get_cookie_params();
        $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
        setcookie(session_name(), session_id(), [
          'expires' => time() + 604800, // 7 days (extended from 24 hours)
          'path' => '/',
          'secure' => !$isLocalhost,
          'httponly' => true,
          'samesite' => 'Lax'
        ]);

        error_log("Session data set successfully for super_admin. Session ID: " . session_id());
        
        // Redirect to super admin dashboard
        error_log("Redirecting super admin to dashboard...");
        header("Location: ../pages/dashboard.php");
        exit();
      } else {
        // Company user login - check company access
        // Fetching the company where this profile is the manager (companies.manager_id => profiles.id)
        // Company-admin users are identified by companies.manager_id matching the profile id
        $companyUrl = "$supabaseUrl/rest/v1/companies?manager_id=eq.$id&select=*";
        $companyData = @file_get_contents($companyUrl, false, $context);

        if ($companyData === false) {
          $error = "Failed to fetch company data";
          return;
        }

        $companies = json_decode($companyData, true);
        if (empty($companies)) {
          $error = "Company not found for this manager";
          return;
        }

        // Use the first match (should normally be unique)
        $company = $companies[0];

        error_log("Company check - Company subdomain: " . ($company['subdomain'] ?? 'not set'));
        error_log("Company check - URL subdomain: " . $subdomain);
        error_log("Company data: " . print_r($company, true));
        
        // Always allow 'company-app' subdomain access
        if ($company && ($company['subdomain'] === $subdomain || $subdomain === 'company-app')) {
          // Debug information
          error_log("Login successful - About to set session variables");
          error_log("Current session ID: " . session_id());
          error_log("User ID to set: " . $userId);
          error_log("Company ID to set: " . $company['id']); // Using company's actual id
          error_log("Role to set: " . $userRole);
          
          // Ensure we have a valid session
          if (session_status() === PHP_SESSION_NONE) {
              session_start();
          }
          
          // Clear any existing session data
          session_unset();
          
          // Set core session info
          $_SESSION['user_id'] = $profile['id'];
          $_SESSION['email'] = $email;
          $_SESSION['user_fullname'] = $profile['full_name'];
          $_SESSION['role'] = $userRole;
          $_SESSION['id'] = $company['id']; // Using company's actual id
          $_SESSION['company_name'] = $company['company_name'];
          $_SESSION['access_token'] = $accessToken;
          if ($refreshToken) {
            $_SESSION['refresh_token'] = $refreshToken;
          }
          $_SESSION['logged_in'] = true;
          $_SESSION['last_activity'] = time();

          // Set secure cookie with session ID
          $params = session_get_cookie_params();
          $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
          setcookie(session_name(), session_id(), [
            'expires' => time() + 86400, // 24 hours
            'path' => '/',
            'secure' => !$isLocalhost,
            'httponly' => true,
            'samesite' => 'Lax'
          ]);

          error_log("Session data set successfully. Session ID: " . session_id());
          
          // Redirect to company dashboard
          error_log("Redirecting to company dashboard...");
          header("Location: ../company-app/pages/dashboard.php");
          exit();
        } else {
          $error = "Access denied: Company mismatch.";
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link rel="manifest" href="../manifest.json">
  <meta name="theme-color" content="#2e0b3f">
  <link rel="stylesheet" href="../company-app/assets/css/login-styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('../service-worker.js', {
        scope: '/WDParcelSendReceiverPWA/Admins/super_admin/'
      });
    });
  }
</script>
  <div class="container">
    <img src="../assets/img/Logo.png" alt="Logo for WebDev Technologies" class="logo">
    <div class="form-box">
      
      <h2>Login</h2>

      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <div class="input-container"><i class="fa fa-envelope"></i>
          <input type="email" name="email" placeholder="Email Address" required autocomplete="username">
        </div>

        <div class="input-container">
          <i class="fa fa-lock"></i>
          <input type="password" name="password" id="passwordInput" placeholder="Password" required autocomplete="current-password" style="padding-right: 50px;">
          <button type="button" id="togglePassword" class="toggle-password" aria-label="Show password" title="Show password">
                <svg id="iconEye" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg id="iconEyeOff" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;">
                    <path d="M17.94 17.94A10.06 10.06 0 0 1 12 20c-7 0-11-8-11-8a21.84 21.84 0 0 1 5.06-6.16"></path>
                    <path d="M1 1l22 22"></path>
                    <path d="M9.88 9.88A3 3 0 0 0 14.12 14.12"></path>
                </svg>
            </button>
        </div>

        <div class="p"><a href="forgot_password.php"><u>Forgot Password</u></a></div>

        <div>
          <button type="submit" class="btn">Login</button>
        </div>
      </form>
    </div>
    </div>

  <?php if (isset($_GET['registered']) && $_GET['registered'] === 'success') : ?>
    <script>alert('Registered successfully! You can now log in.');</script>
  <?php endif; ?>
  <?php if (!empty($error)) : ?>
    <div style="color:red; margin: 1em 0; font-weight:bold;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
  
  <script>
    // Password toggle functionality
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('passwordInput');
    const iconEye = document.getElementById('iconEye');
    const iconEyeOff = document.getElementById('iconEyeOff');

    if (togglePasswordBtn) {
      togglePasswordBtn.addEventListener('click', function (e) {
        e.preventDefault();
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        iconEye.style.display = isPassword ? 'none' : 'block';
        iconEyeOff.style.display = isPassword ? 'block' : 'none';
        this.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
        this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      });
    }
  </script>
  </body>
  </html>

