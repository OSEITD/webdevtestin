<?php

class EnvLoader {
    private static $loaded = false;
    private static $variables = [];

    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            // Prefer outlet-app/.env for outlet-app context, then Admins/.env, then root .env
            $adminsEnv = __DIR__ . '/../../Admins/.env';
            $outletEnv = __DIR__ . '/../.env';
            $rootEnv = __DIR__ . '/../../.env';

            if (file_exists($outletEnv)) {
                $path = $outletEnv;
            } elseif (file_exists($adminsEnv)) {
                $path = $adminsEnv;
            } elseif (file_exists($rootEnv)) {
                $path = $rootEnv;
            } else {
                // Try common alternative locations
                $candidates = [
                    __DIR__ . '/../../.env',   // repo root
                    __DIR__ . '/../../../.env' // one level higher
                ];
                foreach ($candidates as $candidate) {
                    if (file_exists($candidate)) {
                        $path = $candidate;
                        break;
                    }
                }
            }
        }

        if ($path === null || !file_exists($path)) {
            // No .env file found - this is OK in production
            // Environment variables may come from the platform (e.g., Render, Docker)
            self::$loaded = true;
            return;
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
    // In production (like Render), environment variables come from the platform
    // Don't throw error if .env file is missing
    error_log('Note: .env file not found, using system environment variables: ' . $e->getMessage());
}
