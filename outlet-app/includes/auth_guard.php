<?php
require_once __DIR__ . '/../includes/session_manager.php';

/**
 * Returns the URL base for the outlet app.
 * On a subdomain vhost (DocumentRoot = outlet-app/) SCRIPT_NAME starts with
 * /pages/, /drivers/, etc.  On the main domain (path-based access) it starts
 * with /outlet-app/.  We use that to build correct absolute redirect URLs
 * that work on both local vhosts and Render's single-service deployment.
 */
function getOutletAppBase() {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($scriptName, '/outlet-app/') === 0) {
        return '/outlet-app';
    }
    return '';
}

function getOutletLoginUrl($extra = '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $url    = "$scheme://$host" . getOutletAppBase() . '/login.php';
    if ($extra) {
        // make sure there is exactly one leading question mark
        if ($extra[0] !== '?') {
            $url .= '?' . ltrim($extra, '?');
        } else {
            $url .= $extra;
        }
    }
    return $url;
}

function requireAuth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: ' . getOutletLoginUrl());
        exit();
    }

    // Silently refresh the Supabase JWT when it is close to expiry so the
    // PHP session (24 h) and the API token stay in sync.
    refreshSupabaseTokenIfNeeded();
}

function requireRole($allowedRoles = ['outlet_manager', 'outlet_admin', 'driver']) {
    requireAuth();

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        session_destroy();
        header('Location: ' . getOutletLoginUrl('?error=insufficient_permissions'));
        exit();
    }
}

function requireOutletAccess() {
    requireRole();

    if (!isset($_SESSION['outlet_id']) || empty($_SESSION['outlet_id'])) {
        header('Location: ' . getOutletLoginUrl('?error=no_outlet_access'));
        exit();
    }
}

function getCurrentUser() {
    requireAuth();

    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'company_id' => $_SESSION['company_id'] ?? null,
        'company_name' => $_SESSION['company_name'] ?? null,
        'outlet_id' => $_SESSION['outlet_id'] ?? null,
    ];
}

function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', 1296000);
        ini_set('session.cookie_lifetime', 1296000);
        session_set_cookie_params([
            'lifetime' => 1296000,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    header('Location: ' . getOutletLoginUrl('?message=logged_out'));
    exit();
}

function validateSession() {
    requireAuth();

    $requiredSessionData = ['user_id', 'email', 'role'];
    foreach ($requiredSessionData as $key) {
        if (!isset($_SESSION[$key]) || empty($_SESSION[$key])) {
            logout();
        }
    }

    return true;
}

if (!function_exists('auth_guard')) {
    function auth_guard($requireOutlet = true) {
        if ($requireOutlet) {
            requireOutletAccess();
        } else {
            requireAuth();
        }
        validateSession();
    }
}
