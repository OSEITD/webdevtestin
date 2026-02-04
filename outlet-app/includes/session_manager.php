<?php
if (defined('SESSION_MANAGER_LOADED')) {
    return;
}
define('SESSION_MANAGER_LOADED', true);

function initSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    if (headers_sent()) {
        error_log("Cannot start session: headers already sent");
        return false;
    }

    if (session_status() === PHP_SESSION_NONE) {
        try {
            session_set_cookie_params([
                'lifetime' => 3600,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();
            return true;
        } catch (Exception $e) {
            error_log("Session start error: " . $e->getMessage());
            return false;
        }
    }

    return true;
}

function safeSessionStart() {
    return initSession();
}

function canStartSession() {
    return !headers_sent() && session_status() !== PHP_SESSION_ACTIVE;
}

if (canStartSession()) {
    safeSessionStart();
}
