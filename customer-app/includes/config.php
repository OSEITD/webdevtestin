<?php
if (!defined('CONFIG_LOADED')) {
    define('CONFIG_LOADED', true);

    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://xerpchdsykqafrsxbqef.supabase.co');
    define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: '');
    define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');

    define('VAPID_PUBLIC_KEY', getenv('VAPID_PUBLIC_KEY') ?: '');
    define('VAPID_PRIVATE_KEY', getenv('VAPID_PRIVATE_KEY') ?: '');
    define('VAPID_SUBJECT', getenv('VAPID_SUBJECT') ?: 'mailto:admin@yourcompany.com');

    define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');
    define('DEBUG_MODE', ENVIRONMENT === 'development');

    if (ENVIRONMENT === 'production') {
        error_reporting(E_ERROR | E_PARSE);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
}
?>
