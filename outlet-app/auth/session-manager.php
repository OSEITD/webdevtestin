<?php
class SessionManager {
    
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            
            $isLocalhost = isset($_SERVER['HTTP_HOST']) && 
                          (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                           strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
            
            
            session_set_cookie_params([
                'lifetime' => 604800,
                'path' => '/',
                'domain' => $isLocalhost ? '' : '.localhost', 
                'secure' => !$isLocalhost, 
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }
    
    public static function setUserSession($userData) {
        self::init();
        
        $_SESSION['user_id'] = $userData['user_id'];
        $_SESSION['email'] = $userData['email'];
        $_SESSION['role'] = $userData['role'];
        $_SESSION['company_id'] = $userData['company_id'];
        $_SESSION['company_name'] = $userData['company_name'];
        $_SESSION['access_token'] = $userData['access_token'];
        $_SESSION['refresh_token'] = $userData['refresh_token'];
        $_SESSION['outlet_id'] = $userData['outlet_id'];
    }
    
    public static function destroy() {
        self::init();
        
        
        $_SESSION = array();
        
        
        session_destroy();
    }
    
    public static function isAuthenticated() {
        self::init();
        return isset($_SESSION['user_id']);
    }
    
    public static function getUserData() {
        self::init();
        
        if (!self::isAuthenticated()) {
            return null;
        }
        
        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'company_id' => $_SESSION['company_id'] ?? null,
            'company_name' => $_SESSION['company_name'] ?? null,
            'access_token' => $_SESSION['access_token'] ?? null,
            'refresh_token' => $_SESSION['refresh_token'] ?? null,
            'outlet_id' => $_SESSION['outlet_id'] ?? null
        ];
    }
    
    public static function hasRole($roles) {
        self::init();
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
    
    public static function regenerateId() {
        self::init();
        session_regenerate_id(true);
    }
}
?>
