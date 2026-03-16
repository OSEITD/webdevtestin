<?php
// Central initialization: error reporting, session configuration, and output buffering
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start output buffering early to allow safe header() redirects from any page
if (!ob_get_level()) {
    ob_start();
}

// Configure session before starting it
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);

    // Set session cookie parameters (use root path for consistency)
    $isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
    $cookieParams = [
        'lifetime' => 86400, // 24 hours - matches login.php
        'path' => '/',
        'domain' => '',
        'secure' => !$isLocalhost,
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    session_set_cookie_params($cookieParams);
    session_start();
}

// Basic session validation used by company-app pages. If invalid, clear and redirect to login.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("Invalid or missing session data in init.php");
    error_log("Session data available: " . print_r($_SESSION, true));

    // Clear session and restart cleanly
    session_unset();
    session_destroy();
    session_start();

    // Redirect to login
    header("Location: ../../auth/login.php?error=session_invalid");
    // Flush buffers and exit to ensure redirect is effective
    while (ob_get_level()) { @ob_end_clean(); }
    echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=../../auth/login.php?error=session_invalid"></head><body>If you are not redirected, <a href="../../auth/login.php?error=session_invalid">login</a>.</body></html>';
    exit;
}

// init.php end
?>
