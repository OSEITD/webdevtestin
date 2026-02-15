<?php
/**
 * Error Sanitization Helper
 * 
 * Provides secure error handling that:
 * - Returns generic error messages to clients (no stack traces or internals)
 * - Logs full error details server-side for debugging
 * - Prevents information leakage in API responses
 * 
 * Usage:
 *   ErrorHandler::handleException($e, 'fetch_deliveries.php');
 *   ErrorHandler::logError('Custom error message', 'endpoint-name.php', ['context' => $context]);
 */

class ErrorHandler {
    /**
     * Handle an exception by logging full details and returning sanitized error response.
     * 
     * @param Exception $e The exception to handle
     * @param string $endpoint Name of the endpoint (for logging)
     * @param int $defaultHttpCode HTTP status code to return (default 500)
     * @return void (outputs JSON and exits)
     */
    public static function handleException($e, $endpoint, $defaultHttpCode = 500) {
        $code = $e->getCode();
        $httpCode = ($code >= 100 && $code <= 599) ? $code : $defaultHttpCode;
        
        // Log full exception details server-side
        self::logError(
            "Exception in {$endpoint}: " . $e->getMessage(),
            $endpoint,
            [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        );
        
        // Determine if this is a user-friendly error message that can be shown
        $message = $e->getMessage();
        $showActualMessage = false;
        
        // User-friendly errors that should be shown directly
        $userFriendlyPatterns = [
            '/already exists/i',
            '/already registered/i',
            '/not found/i',
            '/invalid email/i',
            '/password/i',
            '/missing required/i',
            '/do not match/i'
        ];
        
        foreach ($userFriendlyPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $showActualMessage = true;
                break;
            }
        }
        
        // Send response to client
        http_response_code($httpCode);
        echo json_encode([
            'success' => false,
            'error' => $showActualMessage ? $message : self::getPublicErrorMessage($httpCode)
        ]);
        exit;
    }
    
    /**
     * Log an error with full context server-side.
     * 
     * @param string $message Error message
     * @param string $endpoint Endpoint name (for logging context)
     * @param array $context Additional context data
     * @return void
     */
    public static function logError($message, $endpoint, $context = []) {
        $logEntry = "[{$endpoint}] {$message}";
        
        if (!empty($context)) {
            $logEntry .= " | Context: " . json_encode($context);
        }
        
        error_log($logEntry);
    }
    
    /**
     * Get a generic, safe error message for a given HTTP code.
     * Never returns internals, stack traces, or sensitive info.
     * 
     * @param int $httpCode HTTP status code
     * @return string Generic error message safe for client display
     */
    private static function getPublicErrorMessage($httpCode) {
        $messages = [
            400 => 'Invalid request. Please check your input and try again.',
            401 => 'You are not authenticated. Please log in and try again.',
            403 => 'You do not have permission to access this resource.',
            404 => 'The requested resource was not found.',
            409 => 'The request conflicts with existing data. Please try again.',
            422 => 'The request could not be processed. Please check your data.',
            500 => 'An internal server error occurred. Please try again later.',
            503 => 'The service is temporarily unavailable. Please try again later.',
        ];
        
        return $messages[$httpCode] ?? 'An error occurred. Please try again later.';
    }
    
    /**
     * Validate request method and return sanitized response on mismatch.
     * 
     * @param string $expected Expected HTTP method (e.g., 'GET', 'POST')
     * @param string $endpoint Endpoint name (for logging)
     * @return void (exits if method does not match)
     */
    public static function requireMethod($expected, $endpoint) {
        $actual = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        if ($actual !== $expected) {
            self::logError(
                "Invalid HTTP method: expected {$expected}, got {$actual}",
                $endpoint
            );
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid request method.'
            ]);
            exit;
        }
    }
    
    /**
     * Validate authentication and return sanitized response if not authenticated.
     * 
     * @param string $endpoint Endpoint name (for logging)
     * @return void (exits if not authenticated)
     */
    public static function requireAuth($endpoint) {
        if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['access_token'])) {
            self::logError(
                "Unauthorized access attempt (missing session data)",
                $endpoint,
                [
                    'has_id' => isset($_SESSION['id']),
                    'has_token' => isset($_SESSION['access_token'])
                ]
            );
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'You are not authenticated. Please log in and try again.'
            ]);
            exit;
        }
    }
}
?>
