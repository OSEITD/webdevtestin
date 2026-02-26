<?php
require_once __DIR__ . '/session_manager.php';

function requireAuth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $loginPath = dirname($_SERVER['SCRIPT_NAME']) === '/pages' ? '../login.php' : 
                     (dirname($_SERVER['SCRIPT_NAME']) === '/drivers/pages' ? '../../login.php' : 'login.php');
        header("Location: $loginPath");
        exit();
    }
}

function requireRole($allowedRoles = ['outlet_manager', 'outlet_admin', 'driver']) {
    requireAuth();

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        session_destroy();
        $loginPath = dirname($_SERVER['SCRIPT_NAME']) === '/pages' ? '../login.php' : 
                     (dirname($_SERVER['SCRIPT_NAME']) === '/drivers/pages' ? '../../login.php' : 'login.php');
        header("Location: $loginPath?error=insufficient_permissions");
        exit();
    }
}

function requireOutletAccess() {
    requireRole();

    if (!isset($_SESSION['outlet_id']) || empty($_SESSION['outlet_id'])) {
        $loginPath = dirname($_SERVER['SCRIPT_NAME']) === '/pages' ? '../login.php' : 
                     (dirname($_SERVER['SCRIPT_NAME']) === '/drivers/pages' ? '../../login.php' : 'login.php');
        header("Location: $loginPath?error=no_outlet_access");
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
        ini_set('session.gc_maxlifetime', 604800);
        ini_set('session.cookie_lifetime', 604800);
        session_set_cookie_params([
            'lifetime' => 604800,
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

    // always redirect to the global login page regardless of script location
    $loginPath = '/login.php';
    header("Location: $loginPath?message=logged_out");
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
