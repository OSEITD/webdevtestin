<?php

class EnvLoader {
    private static $loaded = false;
    private static $variables = [];

    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = __DIR__ . '/../.env';
        }

        if (!file_exists($path)) {
            throw new Exception('.env file not found at: ' . $path);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                
                self::$variables[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        return self::$variables[$key] ?? getenv($key) ?: $_ENV[$key] ?? $default;
    }

    public static function has($key) {
        return isset(self::$variables[$key]) || getenv($key) !== false || isset($_ENV[$key]);
    }
}

try {
    EnvLoader::load();
} catch (Exception $e) {
    
    if (getenv('APP_ENV') === 'production') {
        error_log('Failed to load .env file: ' . $e->getMessage());
    }
}
