<?php
/**
 * Front controller for the company-app folder.
 *
 * Historically this file unconditionally redirected every request to
 * `./pages/dashboard.php`. That's problematic when the project uses an
 * Apache rewrite rule to route unknown requests to index.php (see
 * .htaccess). Asset requests that do not exist (for example a bad CSS
 * href) would be rewritten to this file and then redirected to the
 * dashboard — producing a redirect loop and repeated `/pages/...` paths
 * in the browser.
 *
 * Fix: only redirect when the request is explicitly for the app root or
 * for this index.php itself. For any other rewritten requests return a
 * 404 so browsers won't follow a redirect to HTML for an asset URL.
 */

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$normalized = rtrim($requestPath, "/");

// Known root paths that should send users to the dashboard
$shouldRedirect = in_array($normalized, [
	'',
	'/super_admin/company-app',
	'/super_admin/company-app/index.php',
	'/company-app',
	'/company-app/index.php'
], true);

if ($shouldRedirect) {
	header('Location: ./pages/dashboard.php');
	exit();
}

// For any other request that reached this front controller (usually via
// the rewrite rule) respond with 404 instead of redirecting. This will
// stop redirect loops for assets and make issues easier to diagnose.
http_response_code(404);
echo 'Not Found';
exit();
?>
