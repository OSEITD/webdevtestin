<?php
// Temporary debug endpoint - remove when finished
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$dump = [
    'time' => date('c'),
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'server' => [
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'http_host' => $_SERVER['HTTP_HOST'] ?? null,
    ],
];

echo json_encode($dump, JSON_PRETTY_PRINT);
