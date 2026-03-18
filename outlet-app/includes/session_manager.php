<?php
if (defined('SESSION_MANAGER_LOADED')) {
    return;
}
define('SESSION_MANAGER_LOADED', true);

// Always extend gc_maxlifetime, even if session is already active,
// so PHP's GC never prematurely removes session files.
define('SESSION_LIFETIME', 86400); // 24 hours in seconds

function initSession() {
    // Only update gc_maxlifetime when we are able to, to avoid PHP warnings.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    if (headers_sent()) {
        error_log("Cannot start session: headers already sent");
        return false;
    }

    if (session_status() === PHP_SESSION_NONE) {
        try {
            ini_set('session.cookie_lifetime', SESSION_LIFETIME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
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

/**
 * Refresh the Supabase JWT access token using the stored refresh_token.
 * Called automatically on every authenticated request so users stay logged in
 * for the full 24-hour PHP session lifetime instead of only ~1 hour.
 */
function refreshSupabaseTokenIfNeeded() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    if (empty($_SESSION['refresh_token'])) {
        error_log('Session refresh skipped: no refresh_token in session. User may need to log in again.');
        return false;
    }

    // Refresh proactively when less than 5 minutes remain on the token.
    $expiresAt = $_SESSION['token_expires_at'] ?? 0;
    if (time() < ($expiresAt - 300)) {
        return true; // Token still valid
    }

    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_ANON_KEY');

    if (!$supabaseUrl || !$supabaseKey) {
        // Try loading from .env if EnvLoader is available
        if (class_exists('EnvLoader')) {
            EnvLoader::load();
            $supabaseUrl = EnvLoader::get('SUPABASE_URL');
            $supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');
        }
        if (!$supabaseUrl || !$supabaseKey) {
            return false;
        }
    }

    $ch = curl_init("$supabaseUrl/auth/v1/token?grant_type=refresh_token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['refresh_token' => $_SESSION['refresh_token']]),
        CURLOPT_HTTPHEADER     => [
            "apikey: $supabaseKey",
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("Token refresh failed: HTTP $httpCode");
        return false;
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        error_log("Token refresh returned no access_token");
        return false;
    }

    $_SESSION['access_token']    = $data['access_token'];
    $_SESSION['refresh_token']   = $data['refresh_token'] ?? $_SESSION['refresh_token'];
    $_SESSION['token_expires_at'] = time() + ($data['expires_in'] ?? 3600);

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
