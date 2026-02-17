<?php 
ob_start();

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limiter.php';

ob_end_clean();

SecurityHeaders::apply();

if (isset($_GET['clear_session'])) {
    session_start();
    session_destroy();
    header("Location: login.php");
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 604800);
    ini_set('session.cookie_lifetime', 604800);
    $sessionLifetime = (int)EnvLoader::get('SESSION_LIFETIME', 604800);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$supabaseUrl = EnvLoader::get('SUPABASE_URL', 'https://xerpchdsykqafrsxbqef.supabase.co');
$supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E');

$error = '';
$message = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'insufficient_permissions':
            $error = 'You do not have sufficient permissions to access this resource.';
            break;
        case 'no_outlet_access':
            $error = 'You do not have access to any outlet. Please contact your administrator.';
            break;
        case 'no_session':
            $error = 'Your session has expired. Please log in again.';
            break;
        case 'wrong_role':
            $error = 'Access denied. This area is for drivers only.';
            break;
        default:
            
            if (!empty($_GET['error'])) {
                $error = 'An authentication error occurred: ' . htmlspecialchars($_GET['error']);
            }
    }
}

if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'logged_out':
            $message = 'You have been successfully logged out.';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $clientId = RateLimiter::getClientIdentifier();
    RateLimiter::check($clientId, 5, 300);
    
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
        $host = $_SERVER['HTTP_HOST'];
        $hostParts = explode('.', $host);
        $subdomain = $hostParts[0];
        
        
        if ($host === 'localhost' || strpos($host, '127.0.0.1') !== false || $subdomain === 'acme') {
            $subdomain = 'acme';
        }

        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } else {
            
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
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            echo "<!-- DEBUG: Auth response HTTP code: $httpCode -->";
            if ($curlError) {
                echo "<!-- DEBUG: cURL error: $curlError -->";
            }
            echo "<!-- DEBUG: Auth response: " . substr($response, 0, 200) . "... -->";

            
            if ($curlError) {
                $error = "Connection error: " . $curlError;
                echo "<!-- DEBUG: Setting error - connection error -->";
            } elseif ($httpCode >= 400) {
                $error = "Authentication server error (HTTP $httpCode). Please check your credentials.";
                echo "<!-- DEBUG: Setting error - HTTP $httpCode -->";
            } else {
                $authData = json_decode($response, true);

                if (!$authData) {
                    $error = "Invalid response from authentication server";
                } elseif (isset($authData['error'])) {
                    $error = $authData['error_description'] ?? $authData['error'] ?? 'Login failed';
                } else {
                    $accessToken = $authData['access_token'];
                    $userId = $authData['user']['id'] ?? null;

                    if (!$userId) {
                        $error = "Login succeeded, but user ID was not returned.";
                    } else {
                    
                    $profileUrl = "$supabaseUrl/rest/v1/profiles?select=id,full_name,role,company_id,outlet_id,phone,email,password_last_updated&id=eq.$userId";

                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'header' => "apikey: $supabaseKey\r\nAuthorization: Bearer $accessToken\r\n"
                        ]
                    ]);

                    $profileData = file_get_contents($profileUrl, false, $context);
                    
                    if ($profileData === false) {
                        $error = "Failed to fetch user profile from server.";
                    } else {
                        $profiles = json_decode($profileData, true);
                        
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $error = "Invalid profile data received from server.";
                        } elseif (empty($profiles)) {
                            
                            echo "<!-- DEBUG: Creating new profile for user $userId -->";
                            
                            
                            $intendedRole = 'outlet_manager'; 
                            if (isset($_GET['role']) && $_GET['role'] === 'driver') {
                                $intendedRole = 'driver';
                            } elseif (strpos(strtolower($email), 'driver') !== false) {
                                
                                $intendedRole = 'driver';
                            }
                            
                            $defaultProfile = [
                                'id' => $userId,
                                'role' => $intendedRole,
                                'full_name' => explode('@', $email)[0], 
                                'email' => $email, 
                                'company_id' => null, 
                                'outlet_id' => null   
                            ];
                            
                            
                            $companyUrl = "$supabaseUrl/rest/v1/companies?select=*&subdomain=eq.$subdomain";
                            $companyData = file_get_contents($companyUrl, false, $context);
                            $companies = json_decode($companyData, true);
                            
                            if (empty($companies)) {
                                
                                $newCompany = [
                                    'company_name' => ucfirst($subdomain) . ' Logistics',
                                    'subdomain' => $subdomain,
                                    'status' => 'active'
                                ];
                                
                                $companyPayload = json_encode($newCompany);
                                $ch = curl_init("$supabaseUrl/rest/v1/companies");
                                curl_setopt_array($ch, [
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_HTTPHEADER => [
                                        "apikey: $supabaseKey",
                                        "Authorization: Bearer $accessToken",
                                        "Content-Type: application/json",
                                        "Prefer: return=representation"
                                    ],
                                    CURLOPT_POSTFIELDS => $companyPayload
                                ]);
                                
                                $companyResponse = curl_exec($ch);
                                $companyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                curl_close($ch);
                                
                                echo "<!-- DEBUG: Company creation HTTP: $companyHttpCode -->";
                                
                                $newCompanies = json_decode($companyResponse, true);
                                if (!empty($newCompanies)) {
                                    $company = $newCompanies[0];
                                    $defaultProfile['company_id'] = $company['id'];
                                }
                            } else {
                                $company = $companies[0];
                                $defaultProfile['company_id'] = $company['id'];
                            }
                            
                            
                            if (isset($company['id']) && $intendedRole !== 'driver') {
                                $outletUrl = "$supabaseUrl/rest/v1/outlets?select=*&company_id=eq.{$company['id']}&limit=1";
                                $outletData = file_get_contents($outletUrl, false, $context);
                                $outlets = json_decode($outletData, true);
                                
                                if (empty($outlets)) {
                                    
                                    $newOutlet = [
                                        'company_id' => $company['id'],
                                        'outlet_name' => 'Main Branch',
                                        'location' => 'Head Office',
                                        'status' => 'active'
                                    ];
                                    
                                    $outletPayload = json_encode($newOutlet);
                                    $ch = curl_init("$supabaseUrl/rest/v1/outlets");
                                    curl_setopt_array($ch, [
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_HTTPHEADER => [
                                            "apikey: $supabaseKey",
                                            "Authorization: Bearer $accessToken",
                                            "Content-Type: application/json",
                                            "Prefer: return=representation"
                                        ],
                                        CURLOPT_POSTFIELDS => $outletPayload
                                    ]);
                                    
                                    $outletResponse = curl_exec($ch);
                                    curl_close($ch);
                                    
                                    $newOutlets = json_decode($outletResponse, true);
                                    if (!empty($newOutlets)) {
                                        $defaultProfile['outlet_id'] = $newOutlets[0]['id'];
                                    }
                                } else {
                                    $defaultProfile['outlet_id'] = $outlets[0]['id'];
                                }
                            } else if ($intendedRole === 'driver') {
                                
                                $defaultProfile['outlet_id'] = null;
                                echo "<!-- DEBUG: Driver account - no outlet assignment needed -->";
                            }
                            
                            
                            echo "<!-- DEBUG: Creating profile with role: $intendedRole, data: " . json_encode($defaultProfile) . " -->";
                            
                            $profilePayload = json_encode($defaultProfile);
                            $ch = curl_init("$supabaseUrl/rest/v1/profiles");
                            curl_setopt_array($ch, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPHEADER => [
                                    "apikey: $supabaseKey",
                                    "Authorization: Bearer $accessToken",
                                    "Content-Type: application/json",
                                    "Prefer: return=representation"
                                ],
                                CURLOPT_POSTFIELDS => $profilePayload
                            ]);
                            
                            $profileCreateResponse = curl_exec($ch);
                            $profileHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            echo "<!-- DEBUG: Profile creation HTTP: $profileHttpCode, Response: $profileCreateResponse -->";
                            
                            $createdProfiles = json_decode($profileCreateResponse, true);
                            if (!empty($createdProfiles)) {
                                $profile = $createdProfiles[0];
                                $company = $company ?? ['company_name' => 'Default Company', 'subdomain' => $subdomain];
                                echo "<!-- DEBUG: Profile created successfully with role: " . ($profile['role'] ?? 'UNKNOWN') . " -->";
                            } else {
                                $error = "Failed to create user profile. Please contact administrator. Debug: HTTP $profileHttpCode - $profileCreateResponse";
                            }
                            
                        } else {
                            $profile = $profiles[0];
                            
                            
                            if ($profile['company_id']) {
                                $companyUrl = "$supabaseUrl/rest/v1/companies?select=*&id=eq.{$profile['company_id']}";
                                $companyData = file_get_contents($companyUrl, false, $context);
                                $companies = json_decode($companyData, true);
                                $company = !empty($companies) ? $companies[0] : null;
                            } else {
                                $company = null;
                            }
                        }
                        
                        
                        if (!empty($profile) && !empty($company)) {
                            // Get role from profile - DO NOT default to outlet_manager (security issue)
                            $userRole = $profile['role'] ?? null;
                            $allowedRoles = ['outlet_manager', 'outlet_admin', 'driver', 'admin', 'super_admin'];
                            
                            echo "<!-- DEBUG: Raw role from profile: " . var_export($userRole, true) . " -->";
                            
                            // If role is missing or invalid, show error instead of defaulting
                            if (empty($userRole) || !in_array($userRole, $allowedRoles)) {
                                $error = "Invalid or missing user role. Please contact administrator. Role: " . var_export($userRole, true);
                                echo "<!-- ERROR: Invalid role for user $userId: " . var_export($userRole, true) . " -->";
                            } else {
                                echo "<!-- DEBUG: Valid role assigned: $userRole -->";
                            }
                            
                            // Only proceed if we have a valid role
                            if (empty($error)) {
                            
                            
                            $_SESSION['user_id'] = $userId;
                            $_SESSION['email'] = $email;
                            $_SESSION['role'] = $userRole;  
                            $_SESSION['company_id'] = $profile['company_id'];
                            $_SESSION['company_name'] = $company['company_name'];
                            
                            
                            if ($userRole !== 'driver') {
                                $_SESSION['outlet_id'] = $profile['outlet_id'];
                            } else {
                                
                                $_SESSION['outlet_id'] = null;
                            }
                            
                            $_SESSION['access_token'] = $accessToken;
                            $_SESSION['refresh_token'] = $authData['refresh_token'] ?? null;
                            $_SESSION['full_name'] = $profile['full_name'] ?? '';
                            
                            
                            session_write_close();
                            session_start(); 
                            
                            echo "<!-- DEBUG: Session data committed - Role: " . ($_SESSION['role'] ?? 'NOT SET') . " -->";
                            
                            
                            $needsPasswordChange = false;
                            
                            
                            if (empty($profile['password_last_updated'])) {
                                $needsPasswordChange = true;
                                echo "<!-- DEBUG: First login detected - password change required -->";
                            }
                            
                            
                            if ($needsPasswordChange && !isset($_GET['skip_password_change'])) {
                                $_SESSION['requires_password_change'] = true;
                                $_SESSION['login_timestamp'] = time();
                                
                                if (!headers_sent()) {
                                    header("Location: change_password.php");
                                    exit();
                                } else {
                                    echo "<script>window.location.href = 'change_password.php';</script>";
                                    exit();
                                }
                            }
                            
                            echo "<!-- DEBUG: Session set - Role: " . $_SESSION['role'] . ", Outlet ID: " . ($_SESSION['outlet_id'] ?? 'NULL') . " -->";
                            echo "<!-- DEBUG: User ID in session: " . $_SESSION['user_id'] . " -->";
                            echo "<!-- DEBUG: Session ID: " . session_id() . " -->";
                            
                            
                            if (empty($_SESSION['user_id'])) {
                                echo "<!-- ERROR: Session user_id is empty after setting! -->";
                                $error = "Session setup failed. Please try again.";
                            } else {
                                echo "<!-- DEBUG: Session user_id confirmed set: " . $_SESSION['user_id'] . " -->";

                            
                            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                                echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px;'>";
                                echo "<h3>🎉 Login Successful - Debug Mode</h3>";
                                echo "<p><strong>User ID:</strong> " . htmlspecialchars($_SESSION['user_id']) . "</p>";
                                echo "<p><strong>Email:</strong> " . htmlspecialchars($_SESSION['email']) . "</p>";
                                echo "<p><strong>Role:</strong> " . htmlspecialchars($_SESSION['role']) . "</p>";
                                echo "<p><strong>Company:</strong> " . htmlspecialchars($_SESSION['company_name']) . "</p>";
                                echo "<p><strong>Company ID:</strong> " . htmlspecialchars($_SESSION['company_id'] ?? 'NULL') . "</p>";
                                echo "<p><strong>Outlet ID:</strong> " . htmlspecialchars($_SESSION['outlet_id'] ?? 'None') . "</p>";
                                echo "<p><a href='outlet_dashboard.php'>Continue to Dashboard</a> | <a href='debug_session.php'>View Session</a></p>";
                                echo "</div>";
                                exit();
                            }
                            
                            
                            // Role-based redirect - CRITICAL: ensure correct dashboard per role
                            $redirectUrl = '';
                            switch ($userRole) {
                                case 'driver':
                                    $redirectUrl = 'drivers/dashboard.php';
                                    echo "<!-- DEBUG: Driver role detected - redirecting to drivers/dashboard.php -->";
                                    break;
                                case 'outlet_manager':
                                case 'outlet_admin':
                                    $redirectUrl = 'pages/outlet_dashboard.php';
                                    echo "<!-- DEBUG: Manager/Admin role detected - redirecting to pages/outlet_dashboard.php -->";
                                    break;
                                case 'admin':
                                case 'super_admin':
                                    $redirectUrl = 'pages/outlet_dashboard.php';
                                    echo "<!-- DEBUG: Super Admin role detected - redirecting to pages/outlet_dashboard.php -->";
                                    break;
                                default:
                                    $error = "Unknown role: $userRole. Cannot determine dashboard.";
                                    echo "<!-- ERROR: Unknown role $userRole - cannot redirect -->";
                            }
                            
                            if (!empty($redirectUrl)) {
                                echo "<!-- DEBUG: Final redirect URL: $redirectUrl for role: $userRole -->";
                                
                                if (!headers_sent()) {
                                    header("Location: $redirectUrl");
                                    exit();
                                } else {
                                    echo "<script>window.location.href = '$redirectUrl';</script>";
                                    echo "<p>Redirecting to dashboard... <a href='$redirectUrl'>Click here if not redirected</a></p>";
                                    exit();
                                }
                            }
                            
                            } // end if (empty($error)) // end if (empty($_SESSION['user_id']))
                        } else {
                            $error = "Unable to complete login setup. Please try again or contact administrator.";
                        }
                    }
                }
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
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - Outlet Management</title>
  <link rel="manifest" href="manifest.json" />
  <meta name="theme-color" content="#2e0b3f" />
  <link rel="stylesheet" href="./css/login-styles.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    
    .toast-notification {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 10000;
      min-width: 300px;
      max-width: 600px;
      padding: 16px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
      animation: slideDown 0.3s ease-out;
    }
    
    .toast-notification.error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
    }
    
    .toast-notification.success {
      background: #f0fdf4;
      border: 1px solid #86efac;
      color: #16a34a;
    }
    
    .toast-notification i {
      font-size: 20px;
      flex-shrink: 0;
    }
    
    .toast-notification.hiding {
      animation: slideUp 0.3s ease-out forwards;
    }
    
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateX(-50%) translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
      }
    }
    
    @keyframes slideUp {
      from {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
      }
      to {
        opacity: 0;
        transform: translateX(-50%) translateY(-20px);
      }
    }
  </style>
</head>
<body class="login-body">
    <!-- Toast Notifications at Top -->
    <?php if (!empty($error)): ?>
        <div class="toast-notification error" id="errorToast">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>
  
    <?php if (!empty($message)): ?>
        <div class="toast-notification success" id="successToast">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="brand-panel">
                <div class="brand-logo">
                    <img src="img/logo.png" alt="Company logo" />
                </div>
                <h1 class="brand-title">Welcome back</h1>
                <p class="brand-tagline">Sign in to continue managing your outlet operations.</p>
            </div>
            <div class="form-box">
                <h2>Account Login</h2>
                <form action="login.php" method="POST">
                    <?php echo CSRF::field(); ?>
                    <div class="input-container">
                        <i class="fa fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
                    </div>
                    <div class="input-container">
                        <i class="fa fa-lock"></i>
                        <input type="password" name="password" placeholder="Password" required />
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

      // Toggle password visibility inside input containers
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
  
  <?php include __DIR__ . '/includes/pwa_install_button.php'; ?>
  <script src="js/pwa-install.js"></script>
</body>
</html>
