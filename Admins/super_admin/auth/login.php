<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Configure session handling
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

// Determine appropriate cookie params for environment
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
$cookieParams = [
  'lifetime' => 86400, // 24 hours
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

// Debug session info
error_log("Session ID at start: " . session_id());
error_log("Session path: " . session_save_path());
error_log("Initial session data: " . print_r($_SESSION, true));

// Prevent accidental redirect by clearing stale sessions immediately.
// If a session claims to be logged in but lacks a token or last activity, reset it.
$sessionLifetime = 86400; // 24 hours
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
  $hasToken = isset($_SESSION['access_token']) && !empty($_SESSION['access_token']);
  $hasActivity = isset($_SESSION['last_activity']) && is_numeric($_SESSION['last_activity']);
  $notExpired = $hasActivity ? (time() - (int)$_SESSION['last_activity'] <= $sessionLifetime) : false;
  if (!($hasToken && $hasActivity && $notExpired)) {
    error_log("Found stale or partial session data on login page. Clearing session to avoid auto-redirect.");
    session_unset();
    session_regenerate_id(true);
    error_log("New session ID after clear: " . session_id());
  }
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session state
error_log("Session state at login: " . print_r($_SESSION, true));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

// Only redirect if the session is truly valid. Require:
// - logged_in === true
// - an access token present
// - a recent last_activity timestamp (within session lifetime)
// - not a POST request
// - no previous error
// - we are on the login page
$sessionLifetime = 86400; // seconds (24h)
$sessionValid = false;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
  $hasToken = isset($_SESSION['access_token']) && !empty($_SESSION['access_token']);
  $hasActivity = isset($_SESSION['last_activity']) && is_numeric($_SESSION['last_activity']);
  $notExpired = $hasActivity ? (time() - (int)$_SESSION['last_activity'] <= $sessionLifetime) : false;
  $sessionValid = $hasToken && $hasActivity && $notExpired;
}

if ($sessionValid && $_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($error) && basename($_SERVER['PHP_SELF']) === 'login.php') {
  error_log("Redirecting logged-in user to dashboard (validated session)");
  // Redirect based on role
  $role = $_SESSION['role'] ?? null;
  if ($role === 'super_admin') {
    header("Location: ../pages/dashboard.php");
  } else {
    // Default to company app for company users
    header("Location: ../company-app/pages/dashboard.php");
  }
  exit;
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
          'expires' => time() + 86400, // 24 hours
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
          $_SESSION['company_name'] = $company['name'];
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
  <link rel="manifest" href="../company-app/manifest.json">
  <meta name="theme-color" content="#2e0b3f">
  <link rel="stylesheet" href="../company-app/assets/css/login-styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<script>
  // Temporarily disable service worker registration until paths are fixed
  /*if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('../../service-worker.js', {
        scope: '/'
      });
    });
  }*/
</script>
  <div class="container">
    <img src="../company-app/assets/images/logo.png" alt="Logo for WebDev Technologies" class="logo">
    <div class="form-box">
      
      <h2>Login</h2>

      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <div class="input-container"><i class="fa fa-envelope"></i>
          <input type="email" name="email" placeholder="Email Address" required autocomplete="username">
        </div>

        <div class="input-container">
          <i class="fa fa-lock"></i>
          <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
        </div>

        <div class="p"><a href="forgot_password.php"><u>Forgot Password</u></a></div>

        <div>
          <button type="submit" class="btn">Login</button>
        </div>        <div> or </div>
        <button type="button" class="google-btn"><img src="../company-app/assets/icons/google.png"/> Sign in with Google</button>
        <p class="switch-link">Don't have an account? <a href="Register.php">Register</a></p>
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
  </body>
  </html>

