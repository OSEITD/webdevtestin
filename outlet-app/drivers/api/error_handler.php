<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true;
});
set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An internal error occurred'
    ]);
    exit;
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}");
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'error' => 'A fatal error occurred'
        ]);
    }
});
