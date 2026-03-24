<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Configure session handling
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

// Include Supabase client for session tracking
require_once __DIR__ . '/../api/supabase-client.php';

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

// Load environment variables
require_once __DIR__ . '/../includes/env.php';

// Debug session state
error_log("Session state at login: " . print_r($_SESSION, true));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

// Supabase config - loaded from .env
$supabaseUrl = EnvLoader::get('SUPABASE_URL');
$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');

$error = '';
$errorType = '';

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
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  $response = curl_exec($ch);
  $curlError = curl_error($ch);
  curl_close($ch);

  // Log the raw response for debugging
  error_log("Auth Response: " . $response);

  // Check for connection/network errors
  if ($curlError) {
    error_log("cURL Error: " . $curlError);
    $error = "Unable to connect to the server. Please check your internet connection and try again.";
    $errorType = 'connection';
  } else {

  $authData = json_decode($response, true);

  // Defensive checks: ensure we decoded JSON and have an array
  if (!is_array($authData)) {
    error_log("Auth decode failed or returned non-array: " . var_export($response, true));
    $error = "Unable to connect to the server. Please check your internet connection and try again.";
    $errorType = 'connection';
  } elseif (isset($authData['error'])) {
    error_log("Login Error: " . ($authData['error_description'] ?? 'Unknown error'));
    $rawError = $authData['error_description'] ?? $authData['error'] ?? '';
    if (stripos($rawError, 'Invalid login credentials') !== false) {
      $error = "Invalid email or password. Please check your credentials and try again.";
      $errorType = 'credentials';
    } else {
      $error = "Invalid email or password. Please check your credentials and try again.";
      $errorType = 'credentials';
    }
  } else {
    // Getting access token and user info (use null coalescing to avoid undefined index warnings)
    $accessToken = $authData['access_token'] ?? null;
    $refreshToken = $authData['refresh_token'] ?? null; // Store refresh token
    $userId = null;
    if (isset($authData['user']) && is_array($authData['user'])) {
      $userId = $authData['user']['id'] ?? null;
    }

    // Fallback: if Supabase /token did not include user payload, get user from auth endpoint
    if (!$userId && $accessToken) {
      $userUrl = "$supabaseUrl/auth/v1/user";
      $chUser = curl_init($userUrl);
      curl_setopt($chUser, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($chUser, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "apikey: $supabaseKey",
        "Content-Type: application/json"
      ]);
      curl_setopt($chUser, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($chUser, CURLOPT_TIMEOUT, 30);
      $userResponse = curl_exec($chUser);
      $userCurlError = curl_error($chUser);
      curl_close($chUser);

      if (!$userCurlError && $userResponse) {
        $userData = json_decode($userResponse, true);
        if (is_array($userData) && isset($userData['id'])) {
          $userId = $userData['id'];
          error_log("Fallback user lookup success (auth/v1/user) - User ID: $userId");
        }
      } else {
        error_log("Fallback user lookup failed: " . ($userCurlError ?: 'empty response'));
      }
    }

    // Second fallback: use profile lookup by email when still missing userId
    if (!$userId && !empty($email)) {
      $profileUrl = "$supabaseUrl/rest/v1/profiles?select=id&email=eq." . urlencode($email);
      $profileContext = stream_context_create([
        'http' => [
          'method' => 'GET',
          'header' => "apikey: $supabaseKey\r\nAuthorization: Bearer " . ($accessToken ?: $supabaseKey) . "\r\n"
        ]
      ]);
      $profileData = @file_get_contents($profileUrl, false, $profileContext);
      if ($profileData) {
        $profiles = json_decode($profileData, true);
        if (!empty($profiles) && !empty($profiles[0]['id'])) {
          $userId = $profiles[0]['id'];
          error_log("Fallback profile lookup success by email: $userId");
        }
      }
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
        $error = "Unable to connect to the server. Please check your internet connection and try again.";
        $errorType = 'connection';
      } else {
      
      $profiles = json_decode($profileData, true);

      if (empty($profiles)) {
        $error = "No account found with this email address. Please check your email or contact your administrator.";
        $errorType = 'not_found';
      } else {

      $profile = $profiles[0];
      $userRole = $profile['role'] ?? null;
      
      // Checking if user belongs to this subdomain/company
      $id = $profile['id'] ?? null;
      
      if (empty($id)) {
        $error = "No ID associated with this profile";
        $errorType = 'not_found';
      } else {

      // Check if user is suspended
      $userStatus = $profile['status'] ?? 'Active';
      if ($userStatus === 'Suspended') {
        $error = "Your account has been suspended. Please contact your administrator for assistance.";
        $errorType = 'suspended';
        error_log("Login attempt by suspended user: {$id}");
      } else {

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
        
        // Track session in user_sessions table
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $deviceType = (strpos($userAgent, 'Mobile') !== false) ? 'mobile' : 'desktop';
            
            $sessionData = [
                'user_id' => $id,
                'ip_address' => $ipAddress,
                'user_agent' => substr($userAgent, 0, 500),
                'device_type' => $deviceType
            ];
            
            $sessionResult = callSupabaseWithServiceKey('user_sessions', 'POST', $sessionData);
            if ($sessionResult && (is_array($sessionResult) ? isset($sessionResult[0]['id']) : isset($sessionResult['id']))) {
                $sessionId = is_array($sessionResult) ? $sessionResult[0]['id'] : $sessionResult['id'];
                $_SESSION['session_id'] = $sessionId;
                error_log("User session tracked: {$sessionId}");
            } else {
                error_log("Warning: Failed to track user session for super_admin: " . json_encode($sessionResult));
            }
        } catch (Exception $e) {
            error_log("Error tracking session: " . $e->getMessage());
        }
        
        // Redirect to super admin dashboard
        error_log("Redirecting super admin to dashboard...");
        header("Location: ../pages/dashboard.php");
        exit();
      } else {
        // Non-super_admin login - find the user's company
        $company = null;

        // Strategy 1: Check if this user is a company manager (companies.manager_id)
        $companyUrl = "$supabaseUrl/rest/v1/companies?manager_id=eq.$id&select=*";
        $companyData = @file_get_contents($companyUrl, false, $context);
        if ($companyData !== false) {
          $companies = json_decode($companyData, true);
          if (!empty($companies)) {
            $company = $companies[0];
            error_log("Company found via manager_id for user: $id");
          }
        }

        // Strategy 2: If not found by manager_id, look up via profile's company_id
        if (!$company && !empty($profile['company_id'])) {
          $companyIdFromProfile = $profile['company_id'];
          $companyUrl2 = "$supabaseUrl/rest/v1/companies?id=eq.$companyIdFromProfile&select=*";
          $companyData2 = @file_get_contents($companyUrl2, false, $context);
          if ($companyData2 !== false) {
            $companies2 = json_decode($companyData2, true);
            if (!empty($companies2)) {
              $company = $companies2[0];
              error_log("Company found via profile.company_id for user: $id");
            }
          }
        }

        if (!$company) {
          $error = "No company found for this account. Please contact your administrator.";
          $errorType = 'not_found';
        } else {
          error_log("Company check - Company subdomain: " . ($company['subdomain'] ?? 'not set'));
          error_log("Company check - URL subdomain: " . $subdomain);
          error_log("Company data: " . print_r($company, true));
          
          // Check if the company is suspended before allowing login
          if (isset($company['status']) && $company['status'] === 'suspended') {
            $error = "Your company's account is suspended. Please contact the system administrator.";
            $errorType = 'suspended';
            error_log("Login attempt for suspended company: {$company['id']} by user: {$id}");
          }
          // Always allow 'company-app' subdomain access
          elseif ($company['subdomain'] === $subdomain || $subdomain === 'company-app') {
            error_log("Login successful - About to set session variables");
            error_log("Current session ID: " . session_id());
            error_log("User ID to set: " . $userId);
            error_log("Company ID to set: " . $company['id']);
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
            $_SESSION['id'] = $company['id'];
            $_SESSION['company_name'] = $company['company_name'];
            $_SESSION['access_token'] = $accessToken;
            if ($refreshToken) {
              $_SESSION['refresh_token'] = $refreshToken;
            }
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();

            // Set secure cookie with session ID
            $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
            setcookie(session_name(), session_id(), [
              'expires' => time() + 86400,
              'path' => '/',
              'secure' => !$isLocalhost,
              'httponly' => true,
              'samesite' => 'Lax'
            ]);

            error_log("Session data set successfully. Session ID: " . session_id());
            
            // Track session in user_sessions table
            try {
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                $deviceType = (strpos($userAgent, 'Mobile') !== false) ? 'mobile' : 'desktop';
                
                $sessionData = [
                    'user_id' => $profile['id'],
                    'ip_address' => $ipAddress,
                    'user_agent' => substr($userAgent, 0, 500),
                    'device_type' => $deviceType
                ];
                
                $sessionResult = callSupabaseWithServiceKey('user_sessions', 'POST', $sessionData);
                if ($sessionResult && (is_array($sessionResult) ? isset($sessionResult[0]['id']) : isset($sessionResult['id']))) {
                    $sessionId = is_array($sessionResult) ? $sessionResult[0]['id'] : $sessionResult['id'];
                    $_SESSION['session_id'] = $sessionId;
                    error_log("User session tracked: {$sessionId}");
                } else {
                    error_log("Warning: Failed to track user session for company user: " . json_encode($sessionResult));
                }
            } catch (Exception $e) {
                error_log("Error tracking session: " . $e->getMessage());
            }
            
            // Redirect to company dashboard
            error_log("Redirecting to company dashboard...");
            header("Location: ../company-app/pages/dashboard.php");
            exit();
          } else {
            $error = "Access denied: Company mismatch.";
            $errorType = 'credentials';
          }
        }
      }
    }
  }
}
  } // end suspended else
  } // end empty id else
  } // end empty profiles else
  } // end profileData else
  } // end curl error else
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75' font-family='Arial' fill='%232e0b3f'>A</text></svg>" type="image/svg+xml">
  <link rel="manifest" href="../manifest.json">
  <meta name="theme-color" content="#2e0b3f">
  <link rel="stylesheet" href="../../../outlet-app/css/login-styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-body">
    <!-- Toast Notifications -->
    <?php if (!empty($error)): ?>
        <div class="toast-notification error" id="errorToast">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="brand-panel">
                <div class="brand-logo">
                    <img src="../assets/img/Logo.png" alt="Company logo" style="border-radius: 50%;" />
                </div>
                <h1 class="brand-title">Welcome back</h1>
                <p class="brand-tagline">Sign in to continue managing your admin operations.</p>
            </div>
            <div class="form-box">
                <h2>Admin Login</h2>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                    <div class="input-container">
                        <i class="fa fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address" autocomplete="username" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
                    </div>
                    <div class="input-container">
                        <i class="fa fa-lock"></i>
                        <input type="password" name="password" placeholder="Password" autocomplete="current-password" required id="passwordInput" />
                        <button type="button" class="toggle-password" aria-label="Show password" title="Show password">
                            <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4A1C40" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4A1C40" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;">
                                <path d="M17.94 17.94A10.06 10.06 0 0 1 12 20c-7 0-11-8-11-8a21.84 21.84 0 0 1 5.06-6.16"></path>
                                <path d="M1 1l22 22"></path>
                                <path d="M9.88 9.88A3 3 0 0 0 14.12 14.12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="form-meta">
                        <a href="forgot_password.php" class="muted-link">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn">Sign In</button>
                </form>
            </div>
        </div>
    </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toasts = document.querySelectorAll('.toast-notification');
      toasts.forEach(function(toast) {
        setTimeout(function() {
          toast.classList.add('hiding');
          setTimeout(function() {
            toast.remove();
          }, 300);
        }, 5000);
      });

      document.querySelectorAll('.input-container .toggle-password').forEach(function(btn) {
          btn.addEventListener('click', function() {
              const input = this.closest('.input-container').querySelector('input');
              if (!input) return;
              const iconEye = this.querySelector('.icon-eye');
              const iconEyeOff = this.querySelector('.icon-eye-off');
              if (input.type === 'password') {
                  input.type = 'text';
                  this.setAttribute('aria-label','Hide password');
                  this.setAttribute('title','Hide password');
                  this.setAttribute('aria-pressed','true');
                  if (iconEye) iconEye.style.display = 'none';
                  if (iconEyeOff) iconEyeOff.style.display = 'inline-block';
              } else {
                  input.type = 'password';
                  this.setAttribute('aria-label','Show password');
                  this.setAttribute('title','Show password');
                  this.setAttribute('aria-pressed','false');
                  if (iconEye) iconEye.style.display = 'inline-block';
                  if (iconEyeOff) iconEyeOff.style.display = 'none';
              }
          });
      });

    });
  </script>
</body>
</html>

