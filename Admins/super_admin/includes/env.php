<?php

class EnvLoader {
    private static $loaded = false;
    private static $variables = [];

    private static function loadFile(string $path): void {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
                if (getenv($key) !== false || isset($_ENV[$key]) || isset(self::$variables[$key])) {
                    continue;
                }

                self::$variables[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $rootEnv = dirname(__DIR__, 3) . '/.env';
            $path = file_exists($rootEnv) ? $rootEnv : __DIR__ . '/../.env';
        }

        if (getenv('APP_ENV') !== 'production') {
            error_log("EnvLoader: loading env file from: {$path}");
        }

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
