<?php
/**
 * Centralized session initialization with secure cookie settings.
 * Enforces Secure, HttpOnly, and SameSite flags across all API endpoints.
 * Should be called at the start of every endpoint that uses sessions.
 */

class SessionHelper {
    /**
     * Initialize session with secure cookie settings.
     * Call this BEFORE session_start() in every endpoint.
     * 
     * Sets:
     * - Secure flag (HTTPS only in production)
     * - HttpOnly (no JS access, prevents XSS token theft)
     * - SameSite=Strict (prevents CSRF via cross-site requests)
     * - Reasonable lifetime (1 hour default)
     */
    public static function initializeSecureSession($lifetime = 3600) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Session already started, do not reinitialize
            return;
        }

        // Detect if we're in production (not localhost)
        $isProduction = !in_array($_SERVER['HTTP_HOST'] ?? 'localhost', [
            'localhost',
            '127.0.0.1',
            '::1'
        ]);

        // Configure secure session cookie parameters
        $cookieParams = [
            'lifetime' => $lifetime,      // Session expires after 1 hour of inactivity
            'path' => '/',
            'httponly' => true,           // No JavaScript access (prevents XSS token theft)
            'samesite' => 'Strict',       // Prevent CSRF via cross-site requests
            'secure' => $isProduction     // HTTPS only in production
        ];

        // Only set domain in production
        if ($isProduction) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if ($host && strpos($host, ':') === false) {  // Skip if port included
                $cookieParams['domain'] = '.' . $host;
            }
        }

        // Apply cookie parameters before session_start()
        session_set_cookie_params($cookieParams);

        // Generate secure session ID
        ini_set('session.use_strict_mode', '1');       // Prevent session fixation
        ini_set('session.use_only_cookies', '1');      // Only use cookies, no URL-based sessions
        ini_set('session.cookie_httponly', '1');       // Already set via params, but enforce in INI
        ini_set('session.sid_length', '32');           // Use longer session ID
        ini_set('session.sid_bits_per_character', '6'); // Use more entropy bits

        // Start session
        session_start();

        // Regenerate session ID on first use (prevent fixation)
        if (!isset($_SESSION['__session_initialized'])) {
            session_regenerate_id(true);
            $_SESSION['__session_initialized'] = time();
        }
    }

    /**
     * Check if the session is authenticated (has required user/company info).
     * Returns true if valid session with id and access_token, false otherwise.
     * 
     * @return bool
     */
    public static function isAuthenticated() {
        return isset($_SESSION['id']) && 
               isset($_SESSION['access_token']) && 
               !empty($_SESSION['id']) && 
               !empty($_SESSION['access_token']);
    }

    /**
     * Require authentication, throw exception if not authenticated.
     * Call this in endpoints that require login.
     * 
     * @throws Exception
     */
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            throw new Exception('Not authenticated', 401);
        }
    }

    /**
     * Get the current company ID from session.
     * Returns null if not in session.
     * 
     * @return string|null
     */
    public static function getCompanyId() {
        return $_SESSION['id'] ?? null;
    }

    /**
     * Get the current access token from session.
     * Returns null if not in session.
     * 
     * @return string|null
     */
    public static function getAccessToken() {
        return $_SESSION['access_token'] ?? null;
    }

    /**
     * Destroy session securely (logout).
     * Clears session data and expires the cookie.
     */
    public static function destroySession() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }
}
?>
