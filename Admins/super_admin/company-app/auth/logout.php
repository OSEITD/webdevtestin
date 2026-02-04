<?php
// Robust logout handler for company-app (logout2.php)
// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear session data
$_SESSION = [];

// Regenerate and destroy session to remove server-side session file
session_regenerate_id(true);
session_destroy();

// Remove session cookie for root path
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
$secure = !$isLocalhost;
setcookie(session_name(), '', time() - 3600, '/', '', $secure, true);

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Instead of a server-side redirect (which can cause redirect loops when cookies persist),
// return a small HTML page that clears cookies client-side and then navigates to login.
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Logging out…</title>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,RobotoDraft,'Helvetica Neue',Arial;margin:40px;color:#111}</style>
</head>
<body>
    <script>
        // Delete all cookies visible to this page
        (function clearAllCookies() {
            try {
                const cookies = document.cookie.split(';');
                for (let i = 0; i < cookies.length; i++) {
                    const cookie = cookies[i];
                    const eqPos = cookie.indexOf('=');
                    const name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
                    // Remove cookie for various paths
                    document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
                    document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=' + window.location.pathname + '/';
                }
            } catch (e) { console.debug('Failed to clear cookies client-side', e); }
        })();

        // Small delay to ensure cookies cleared before navigation
        setTimeout(function() {
            window.location.href = '../../auth/login.php';
        }, 250);
    </script>
</body>
</html>
