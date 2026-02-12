<?php
function checkSession() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        error_log("Session check failed: User not logged in");
        header('Location: ../../auth/login.php');
        exit();
    }

    // Check session age
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 604800)) {
        error_log("Session expired due to inactivity");
        session_unset();
        session_destroy();
        header('Location: ../../auth/login.php?error=session_expired');
        exit();
    }

    // Check if access token exists
    if (!isset($_SESSION['access_token'])) {
        error_log("No access token in session");
        session_unset();
        session_destroy();
        header('Location: ../../auth/login.php?error=invalid_session');
        exit();
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    // Return session data for convenience
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'company_id' => $_SESSION['id'] ?? null,
        'access_token' => $_SESSION['access_token']
    ];
}