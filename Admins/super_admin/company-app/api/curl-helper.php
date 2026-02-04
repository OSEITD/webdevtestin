<?php
/**
 * Secure cURL helper to prevent man-in-the-middle attacks.
 * SSL verification is gated by APP_ENV environment variable.
 * 
 * Usage:
 *   $response = CurlHelper::request('GET', $url, $payload, $headers);
 */

class CurlHelper {
    /**
     * Make a cURL request with secure SSL verification by default.
     * SSL verification can only be disabled in development via APP_ENV=development env var.
     * 
     * @param string $method GET, POST, PATCH, etc.
     * @param string $url Full URL to request
     * @param string|null $payload Request body (for POST/PATCH)
     * @param array $headers HTTP headers
     * @return mixed Response from curl_exec
     */
    public static function request($method, $url, $payload = null, $headers = []) {
        $ch = curl_init($url);
        
        // Always verify SSL in production; allow bypass only in development via APP_ENV env var
        $isProduction = (getenv('APP_ENV') ?: 'production') !== 'development';
        
        if ($isProduction) {
            // Production: enforce SSL verification (default behavior)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // Verify hostname matches certificate
        } else {
            // Development: allow SSL verification bypass (local dev often uses self-signed certs)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        
        // Standard cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        if ($payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }

    /**
     * Convenience method for GET requests.
     * 
     * @param string $url
     * @param array $headers
     * @return mixed
     */
    public static function get($url, $headers = []) {
        return self::request('GET', $url, null, $headers);
    }

    /**
     * Convenience method for POST requests.
     * 
     * @param string $url
     * @param string $payload JSON payload
     * @param array $headers
     * @return mixed
     */
    public static function post($url, $payload, $headers = []) {
        return self::request('POST', $url, $payload, $headers);
    }

    /**
     * Convenience method for PATCH requests.
     * 
     * @param string $url
     * @param string $payload JSON payload
     * @param array $headers
     * @return mixed
     */
    public static function patch($url, $payload, $headers = []) {
        return self::request('PATCH', $url, $payload, $headers);
    }
}
?>
