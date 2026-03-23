<?php

class EnvLoader {
    private static $loaded = false;
    private static $variables = [];

    private static function loadFile(string $path): void {
        if (!file_exists($path)) {
            error_log("EnvLoader: .env file not found: {$path}");
            return;
        }

        error_log("EnvLoader: Reading .env file from: {$path}");
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $loadedCount = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip quotes
                if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                    $value = $matches[1];
                }
                
                self::$variables[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
                $loadedCount++;
                
                if ($key === 'SUPABASE_URL') {
                    error_log("EnvLoader: Set SUPABASE_URL = {$value}");
                }
            }
        }
        
        error_log("EnvLoader: Loaded {$loadedCount} variables from {$path}");
    }

    public static function load($path = null) {
        error_log("=== EnvLoader::load() called, \$loaded = " . (self::$loaded ? 'true' : 'false'));
        
        if (self::$loaded) {
            error_log("=== EnvLoader::load() returning early because already loaded");
            return;
        }

        if ($path === null) {
            $adminsEnv = dirname(__DIR__, 2) . '/.env';  // Admins/.env (preferred)
            $rootEnv = dirname(__DIR__, 3) . '/.env';    // webdevtestin/.env (fallback)
            $path = file_exists($adminsEnv) ? $adminsEnv : $rootEnv;
        }

        error_log("=== EnvLoader: loading env file from: {$path}");

        if (!file_exists($path)) {
            $examplePath = dirname($path) . '/.env.example';
            if (file_exists($examplePath)) {
                $path = $examplePath;
            } else {
                throw new Exception('.env file not found at: ' . $path);
            }
        }

        self::loadFile($path);

        // Load secondary env if exists
        $outletEnv = dirname(__DIR__, 3) . '/outlet-app/.env';
        if (file_exists($outletEnv)) {
            self::loadFile($outletEnv);
        }

        self::$loaded = true;
    } // Added missing closing brace here

    public static function get($key, $default = null) {
        if (isset(self::$variables[$key])) return self::$variables[$key];
        if (isset($_ENV[$key])) return $_ENV[$key];
        
        $env = getenv($key);
        return ($env !== false) ? $env : $default;
    }

    public static function has($key) {
        return isset(self::$variables[$key]) || getenv($key) !== false || isset($_ENV[$key]);
    }
}

try {
    EnvLoader::load();
} catch (Exception $e) {
    error_log('Failed to load .env file: ' . $e->getMessage());
}
