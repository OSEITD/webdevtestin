<?php
/**
 * API-specific initialization
 * Required by all protected API endpoints in super_admin
 * 
 * Sets up:
 * - Output buffering (prevents accidental output before headers)
 * - Error reporting (server-side logging without display)
 * - Session management (with security hardening)
 * - Content-Type headers
 * - CORS headers (if needed)
 * 
 * Usage: require_once __DIR__ . '/init.php';
 */

// Prevent any output before headers
if (!ob_get_level()) {
    ob_start();
}

// Enable error reporting but prevent display to clients
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configure session before starting it
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.gc_maxlifetime', 604800); // 7 days
    ini_set('session.cookie_lifetime', 604800); // 7 days

    // Set session cookie parameters
    $isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
    $cookieParams = [
        'lifetime' => 604800, // 7 days (extended from session-only)
        'path' => '/',
        'domain' => '',
        'secure' => !$isLocalhost,
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    session_set_cookie_params($cookieParams);
    session_start();
}

// CSRF Validation for state-changing requests
require_once __DIR__ . '/../includes/csrf-helper.php';
if (CSRFHelper::isStateChangingRequest()) {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!CSRFHelper::validateToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Load error handler
require_once __DIR__ . '/error-handler.php';

// init.php end
?>
