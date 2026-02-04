<?php
// Turn off display errors for production, but enable logging
ini_set("display_errors", "0");
error_reporting(E_ALL);
ini_set("log_errors", "1");

// Basic Supabase client setup for PHP API calls
global $supabaseUrl, $supabaseKey, $supabaseServiceKey;

$supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
// Anonymous key for public operations
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';
// Service role key for admin operations
$supabaseServiceKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';

class SupabaseClient {
    private $url;
    private $key;

    public function __construct($url, $key) {
        if (empty($key)) {
            throw new Exception("API key cannot be empty");
        }
        $this->url = rtrim($url, '/');
        $this->key = trim($key);
    }

    private function cleanJsonResponse($response) {
        error_log("Raw response: " . $response);
        
        // If response is already valid JSON, return it
        $decoded = json_decode($response);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $response;
        }
        
        // Find the first occurrence of { or [
        $start = strpos($response, '{');
        if ($start === false) {
            $start = strpos($response, '[');
        }
        
        if ($start === false) {
            error_log("No JSON start marker found in response");
            return false;
        }

        // Find the last occurrence of } or ]
        $end = strrpos($response, '}');
        if ($end === false) {
            $end = strrpos($response, ']');
        }

        if ($end === false) {
            error_log("No JSON end marker found in response");
            return false;
        }

        // Extract the JSON part
        $jsonPart = substr($response, $start, $end - $start + 1);
        error_log("Extracted JSON part: " . $jsonPart);
        return $jsonPart;
    }

    public function makeRequest($endpoint, $method = 'GET', $data = null, $customHeaders = []) {
        try {
            if (strpos($endpoint, 'auth/') === 0) {
                $url = $this->url . '/' . $endpoint;
            } else {
                $url = $this->url . '/rest/v1/' . ltrim($endpoint, '/');
            }

            // Add Range header for potentially large datasets
            $headers = [
                "apikey: {$this->key}",
                "Authorization: Bearer {$this->key}",
                "Content-Type: application/json",
                "Accept: application/json",
                "Prefer: return=representation",
                "Range: 0-999" // Limit to 1000 records per request
            ];

            foreach ($customHeaders as $header) {
                $headers[] = $header;
            }

            error_log("Making request to: " . $url);
            error_log("Request headers: " . json_encode($headers));

            $ch = curl_init();
            // Gate SSL verification by APP_ENV: enforce verification in production, allow bypass in development
            $verifySsl = (getenv('APP_ENV') ?: 'production') === 'production';
            $verifyHost = $verifySsl ? 2 : 0;
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_SSL_VERIFYPEER => $verifySsl,
                CURLOPT_SSL_VERIFYHOST => $verifyHost,
                CURLOPT_VERBOSE => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => 30
            ]);

            if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
                $jsonData = json_encode($data);
                if ($jsonData === false) {
                    throw new Exception("Failed to encode request data: " . json_last_error_msg());
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                error_log("Request data: " . $jsonData);
            }

            $response = curl_exec($ch);
            
            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new Exception("cURL error: " . $error);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            error_log("Response HTTP code: " . $httpCode);
            error_log("Raw response: " . substr($response, 0, 1000)); // Log first 1000 chars

            if ($httpCode >= 400) {
                curl_close($ch);
                throw new Exception("HTTP error " . $httpCode . ": " . $response);
            }

            curl_close($ch);

            // Clean and validate JSON response
            if (!empty($response)) {
                $cleanedResponse = $this->cleanJsonResponse($response);
                if ($cleanedResponse !== false) {
                    $decodedResponse = json_decode($cleanedResponse, true);
                    if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Invalid JSON response: " . json_last_error_msg());
                    }
                    return $decodedResponse;
                }
            }
            
            throw new Exception("Empty or invalid response received");
        } catch (Exception $e) {
            error_log("SupabaseClient error: " . $e->getMessage());
            throw $e;
        }
    }

    public function get($endpoint, $query = '', $customHeaders = []) {
        $url = ltrim($endpoint, '/') . ($query ? '?' . $query : '');
        return $this->makeRequest($url, 'GET', null, $customHeaders);
    }

    public function insert($endpoint, $data) {
        if (!is_array($data)) {
            throw new Exception('Data must be an array');
        }

        $cleanData = array_map(function($value) {
            return is_string($value) ? trim($value) : $value;
        }, $data);

        return $this->makeRequest($endpoint, 'POST', $cleanData);
    }

    public function update($endpoint, $data, $query = '') {
        $url = ltrim($endpoint, '/') . ($query ? '?' . $query : '');
        return $this->makeRequest($url, 'PATCH', $data);
    }

    public function delete($endpoint, $query = '') {
        $url = ltrim($endpoint, '/') . ($query ? '?' . $query : '');
        return $this->makeRequest($url, 'DELETE');
    }
}

// Global helper functions
function callSupabase($endpoint, $query = '') {
    global $supabaseUrl, $supabaseKey;
    $client = new SupabaseClient($supabaseUrl, $supabaseKey);
    return $client->get($endpoint, $query);
}

function callSupabaseWithServiceKey($endpoint, $method = 'GET', $data = null, $customHeaders = []) {
    global $supabaseUrl, $supabaseServiceKey;

    $client = new SupabaseClient($supabaseUrl, $supabaseServiceKey);

    try {
        // Build query string for GET requests
        $query = '';
        if ($method === 'GET' && is_array($data) && !empty($data)) {
            $params = [];
            
            // Handle select parameter
            if (isset($data['select'])) {
                $params[] = 'select=' . urlencode($data['select']);
            }
            
            // Handle filters
            if (isset($data['filters']) && is_array($data['filters'])) {
                foreach ($data['filters'] as $key => $value) {
                    $params[] = urlencode($key) . '=eq.' . urlencode($value);
                }
            }
            
            // Handle order
            if (isset($data['order'])) {
                $params[] = 'order=' . urlencode($data['order']);
            }
            
            // Handle limit
            if (isset($data['limit'])) {
                $params[] = 'limit=' . intval($data['limit']);
            }
            
            $query = implode('&', $params);
        }

        switch (strtoupper($method)) {
            case 'POST':
                return $client->insert($endpoint, $data);
            case 'PATCH':
                return $client->update($endpoint, $data);
            case 'DELETE':
                return $client->delete($endpoint);
            default:
                return $client->get($endpoint, $query, $customHeaders);
        }
    } catch (Exception $e) {
        error_log("Error in callSupabaseWithServiceKey for endpoint {$endpoint}: " . $e->getMessage());
        if (isset($query)) {
            error_log("Query parameters: " . $query);
        }
        throw $e;
    }
}