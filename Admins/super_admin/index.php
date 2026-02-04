<?php
/**
 * Front controller for super_admin folder
 * Routes requests to appropriate pages based on whether user is logged in
 */

// Include session initialization
require_once __DIR__ . '/company-app/includes/init.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Get request path
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$normalized = rtrim($requestPath, "/");

// Routes that should display the dashboard
$dashboardRoutes = [
    '',
    '/super_admin',
    '/super_admin/index.php',
    '/super_admin/'
];

if (in_array($normalized, $dashboardRoutes, true)) {
    if ($isLoggedIn) {
        // Include the dashboard page
        include __DIR__ . '/pages/dashboard.php';
    } else {
        // Redirect to login (relative path)
        header('Location: auth/login.php');
        exit();
    }
} else {
	// Not a recognized route - return 404
	http_response_code(404);
	echo 'Not Found';
	exit();
}
