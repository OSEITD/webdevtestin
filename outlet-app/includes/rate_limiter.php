<?php

class RateLimiter {
    private static $storageDir = null;
    
    
    private static function init() {
        if (self::$storageDir === null) {
            self::$storageDir = sys_get_temp_dir() . '/rate_limits';
            if (!file_exists(self::$storageDir)) {
                mkdir(self::$storageDir, 0700, true);
            }
        }
    }
    
    
    public static function isLimited($identifier, $maxAttempts = 5, $window = 300) {
        self::init();
        
        $hash = hash('sha256', $identifier);
        $file = self::$storageDir . '/' . $hash;
        
        $attempts = [];
        $now = time();
        
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $attempts = json_decode($content, true) ?: [];
        }
        
        
        $attempts = array_filter($attempts, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        
        if (count($attempts) >= $maxAttempts) {
            return true;
        }
        
        
        $attempts[] = $now;
        file_put_contents($file, json_encode($attempts));
        
        return false;
    }
    
    
    public static function hit($identifier) {
        self::isLimited($identifier, PHP_INT_MAX, 0); 
    }
    
    
    public static function clear($identifier) {
        self::init();
        
        $hash = hash('sha256', $identifier);
        $file = self::$storageDir . '/' . $hash;
        
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    
    public static function getResetTime($identifier, $window = 300) {
        self::init();
        
        $hash = hash('sha256', $identifier);
        $file = self::$storageDir . '/' . $hash;
        
        if (!file_exists($file)) {
            return 0;
        }
        
        $content = file_get_contents($file);
        $attempts = json_decode($content, true) ?: [];
        
        if (empty($attempts)) {
            return 0;
        }
        
        $oldestAttempt = min($attempts);
        $resetTime = $oldestAttempt + $window;
        
        return max(0, $resetTime - time());
    }
    
    
    public static function getClientIdentifier() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return $ip . '|' . substr(md5($userAgent), 0, 8);
    }
    
    
    public static function check($identifier = null, $maxAttempts = 5, $window = 300) {
        if ($identifier === null) {
            $identifier = self::getClientIdentifier();
        }
        
        if (self::isLimited($identifier, $maxAttempts, $window)) {
            $resetTime = self::getResetTime($identifier, $window);
            
            http_response_code(429);
            header('Retry-After: ' . $resetTime);
            
            die(json_encode([
                'success' => false,
                'error' => 'Too many requests. Please try again later.',
                'retry_after' => $resetTime
            ]));
        }
    }
}
