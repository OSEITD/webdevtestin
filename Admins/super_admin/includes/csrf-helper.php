<?php

class CSRFHelper {
    /**
     * Get or generate CSRF token
     * @return string
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     * @param string $token
     * @return bool
     */
    public static function validateToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $storedToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($token) || empty($storedToken)) {
            return false;
        }
        
        return hash_equals($storedToken, $token);
    }

    /**
     * Check if request is a state-changing method
     * @return bool
     */
    public static function isStateChangingRequest() {
        return in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE']);
    }
}
