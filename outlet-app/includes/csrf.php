<?php

class CSRF {
    private static $tokenName = 'csrf_token';
    
    
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::$tokenName] = $token;
        $_SESSION[self::$tokenName . '_time'] = time();
        
        return $token;
    }
    
    
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$tokenName]) || self::isTokenExpired()) {
            return self::generateToken();
        }
        
        return $_SESSION[self::$tokenName];
    }
    
    
    public static function validateToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$tokenName])) {
            return false;
        }
        
        if (self::isTokenExpired()) {
            return false;
        }
        
        return hash_equals($_SESSION[self::$tokenName], $token);
    }
    
    
    private static function isTokenExpired() {
        if (!isset($_SESSION[self::$tokenName . '_time'])) {
            return true;
        }
        
        return (time() - $_SESSION[self::$tokenName . '_time']) > 3600;
    }
    
    
    public static function field() {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . htmlspecialchars($token) . '">';
    }
    
    
    public static function validate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true; 
        }
        
        $token = $_POST[self::$tokenName] ?? '';
        
        if (!self::validateToken($token)) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
        
        return true;
    }
}
