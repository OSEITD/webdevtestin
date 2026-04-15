<?php
require_once __DIR__ . '/../includes/env.php';

try {
    EnvLoader::load(__DIR__ . '/../../../.env');
} catch (Exception $e) {
    error_log("Failed to load .env: " . $e->getMessage());
}

// Force clear opcode cache for this critical config file to avoid stale runtime behavior on Render.
if (function_exists('opcache_reset')) {
    opcache_reset();
}

error_log('DEBUG: opcache_reset called? ' . (function_exists('opcache_reset') ? 'yes' : 'not available'));


$supabaseUrl = getenv('SUPABASE_URL') ?: EnvLoader::get('SUPABASE_URL');

$supabaseKey = getenv('SUPABASE_ANON_KEY') ?: EnvLoader::get('SUPABASE_ANON_KEY');

// Prefer explicit Render/production env vars over local .env (EnvLoader)
$supabaseServiceKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');
if (empty($supabaseServiceKey)) {
    $supabaseServiceKey = EnvLoader::get('SUPABASE_SERVICE_ROLE_KEY');
}
if (empty($supabaseServiceKey)) {
    $supabaseServiceKey = EnvLoader::get('SUPABASE_SERVICE_KEY');
}

// final fallback to anon key if no service key available (improves resilience)
if (empty($supabaseServiceKey) && !empty($supabaseKey)) {
    $supabaseServiceKey = $supabaseKey;
    $serviceKeyName = 'SUPABASE_ANON_KEY';
    error_log('WARNING: No service key defined; falling back to anon key for Supabase operations.');
}

if (empty($supabaseServiceKey)) {
    error_log('ERROR: SUPABASE_SERVICE_KEY not set in Render env and no fallback found.');
}


error_log('DEBUG: Supabase URL from env=' . ($supabaseUrl ?: '[missing]'));
error_log('DEBUG: Supabase key source: ' . (getenv('SUPABASE_SERVICE_ROLE_KEY') ? 'SERVICE_ROLE_KEY env' : (getenv('SUPABASE_SERVICE_KEY') ? 'SERVICE_KEY env' : 'EnvLoader')));
error_log('DEBUG: Supabase URL is set: ' . (!empty($supabaseUrl) ? 'yes' : 'no'));
error_log('DEBUG: Supabase service key present: ' . (!empty($supabaseServiceKey) ? 'yes' : 'no'));


$outletEnvPath = dirname(__DIR__, 3) . '/outlet-app/.env';
$rootEnvPath = dirname(__DIR__, 3) . '/.env';
$isSuperAdminContext = (strpos(realpath(__DIR__), DIRECTORY_SEPARATOR . 'Admins' . DIRECTORY_SEPARATOR . 'super_admin') !== false);
if (!$isSuperAdminContext && file_exists($outletEnvPath)) {
    $outletVars = [];
    $lines = file($outletEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
            $value = $m[2];
        }
        $outletVars[$key] = $value;
    }

    if (!empty($outletVars['SUPABASE_URL'])) {
        $supabaseUrl = $outletVars['SUPABASE_URL'];
        error_log('Supabase: overriding SUPABASE_URL from outlet-app .env');
    }
    if (!empty($outletVars['SUPABASE_SERVICE_ROLE_KEY'])) {
        $supabaseServiceKey = $outletVars['SUPABASE_SERVICE_ROLE_KEY'];
        error_log('Supabase: overriding SUPABASE_SERVICE_ROLE_KEY from outlet-app .env');
    } elseif (!empty($outletVars['SUPABASE_SERVICE_KEY'])) {
        $supabaseServiceKey = $outletVars['SUPABASE_SERVICE_KEY'];
        error_log('Supabase: overriding SUPABASE_SERVICE_KEY from outlet-app .env');
    }
    if (!empty($outletVars['SUPABASE_ANON_KEY'])) {
        $supabaseKey = $outletVars['SUPABASE_ANON_KEY'];
        error_log('Supabase: overriding SUPABASE_ANON_KEY from outlet-app .env');
    }

}

if (empty($supabaseServiceKey)) {
    error_log('SUPABASE_SERVICE_KEY/SERVICE_ROLE_KEY missing; falling back to SUPABASE_ANON_KEY for service operations.');
    $supabaseServiceKey = $supabaseKey;
}

$serviceKeyName = 'SUPABASE_ANON_KEY';
if (!empty(EnvLoader::get('SUPABASE_SERVICE_ROLE_KEY'))) {
    $serviceKeyName = 'SUPABASE_SERVICE_ROLE_KEY';
} elseif (!empty(EnvLoader::get('SUPABASE_SERVICE_KEY'))) {
    $serviceKeyName = 'SUPABASE_SERVICE_KEY';
} elseif (!empty($supabaseKey)) {
    $serviceKeyName = 'SUPABASE_ANON_KEY';
}

function mask_key_for_log($k) {
    if (!is_string($k) || strlen($k) <= 8) return '***';
    return substr($k, 0, 4) . str_repeat('*', max(0, strlen($k) - 8)) . substr($k, -4);
}

function decode_jwt_payload($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return null;
    }
    $b64 = $parts[1];
    $b64 = str_replace(['-', '_'], ['+', '/'], $b64);
    $b64 = str_pad($b64, strlen($b64) + (4 - strlen($b64) % 4) % 4, '=', STR_PAD_RIGHT);
    $decoded = base64_decode($b64);
    if ($decoded === false) {
        return null;
    }
    $json = json_decode($decoded, true);
    return is_array($json) ? $json : null;
}

$supabaseJwtPayload = decode_jwt_payload($supabaseServiceKey);
$expectedRef = null;
if (preg_match('#https?://([^/.]+)\.supabase\.co#i', $supabaseUrl, $m)) {
    $expectedRef = $m[1];
}

if (is_array($supabaseJwtPayload) && isset($supabaseJwtPayload['ref']) && $expectedRef !== null) {
    $actualRef = $supabaseJwtPayload['ref'];
    if ($actualRef !== $expectedRef) {
        $msg = "Supabase service key mismatch: your SUPABASE_SERVICE_ROLE_KEY (ref={$actualRef}) " .
            "does not match the project ref in SUPABASE_URL (ref={$expectedRef}). " .
            "Please update SUPABASE_SERVICE_ROLE_KEY in your .env to the service role key for " .
            "the project '{$expectedRef}' (Supabase dashboard -> Settings -> API -> Service Role Key).";
        error_log($msg);

        if (!empty(EnvLoader::get('SUPABASE_SERVICE_KEY'))) {
            $supabaseServiceKey = EnvLoader::get('SUPABASE_SERVICE_KEY');
            $serviceKeyName = 'SUPABASE_SERVICE_KEY';
            error_log("Supabase: falling back to SUPABASE_SERVICE_KEY because the service_role key ref mismatched.");
        } elseif (!empty($supabaseKey)) {
            $supabaseServiceKey = $supabaseKey;
            $serviceKeyName = 'SUPABASE_ANON_KEY';
            error_log("Supabase: falling back to SUPABASE_ANON_KEY because no matching service key was found.");
        }
    }
}

error_log("Supabase: using key from {$serviceKeyName}: " . mask_key_for_log($supabaseServiceKey));

foreach (['SUPABASE_URL' => $supabaseUrl, 'SUPABASE_SERVICE_KEY' => $supabaseServiceKey] as $name => $value) {
    if (is_string($value) && preg_match('/your[-_ ]/i', $value)) {
        error_log("WARNING: Supabase configuration placeholder-like value detected for {$name}. Continuing with caution.");
        // Do not throw in production--use log + fallback in case env vars are still being applied
        continue;
    }
}

$placeholderPatterns = [
    '/your[-_ ]?project[-_ ]?ref/i',
    '/your[-_ ]?supabase/i',
    '/your[-_ ]?service[-_ ]?key/i',
    '/your[-_ ]?anon[-_ ]?key/i',
];

foreach (['SUPABASE_URL' => $supabaseUrl, 'SUPABASE_SERVICE_KEY' => $supabaseServiceKey] as $name => $value) {
    if (is_string($value) && preg_match('/your[-_ ]/i', $value)) {
        error_log("WARNING: Supabase configuration placeholder-like value detected for {$name} (second check). Continuing.");
        continue;
    }
}

class SupabaseClient {
    private $url;
    private $key;

    public function __construct($url, $key) {
        if (empty($key)) {
            error_log("SupabaseClient constructed with empty API key; requests may fail if a key is required.");
        }
        $this->url = rtrim($url, '/');
        $this->key = trim($key);
    }

    private function cleanJsonResponse($response) {
        error_log("Raw response: " . $response);
        
        
        $decoded = json_decode($response);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $response;
        }
        
        $start = strpos($response, '{');
        if ($start === false) {
            $start = strpos($response, '[');
        }
        
        if ($start === false) {
            error_log("No JSON start marker found in response");
            return false;
        }

        $end = strrpos($response, '}');
        if ($end === false) {
            $end = strrpos($response, ']');
        }

        if ($end === false) {
            error_log("No JSON end marker found in response");
            return false;
        }
        $jsonPart = substr($response, $start, $end - $start + 1);
        error_log("Extracted JSON part: " . $jsonPart);
        return $jsonPart;
    }

    private function closeCurlHandle(&$handle) {
        if (!is_resource($handle) && !is_object($handle)) {
            return;
        }

        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID < 80500) {
            curl_close($handle);
        }

        $handle = null;
    }

    public function makeRequest($endpoint, $method = 'GET', $data = null, $customHeaders = []) {
        try {
            if (strpos($endpoint, 'auth/') === 0) {
                $url = $this->url . '/' . $endpoint;
            } else {
                $url = $this->url . '/rest/v1/' . ltrim($endpoint, '/');
            }

            $headers = [
                "apikey: {$this->key}",
                "Authorization: Bearer {$this->key}",
                "Content-Type: application/json",
                "Accept: application/json",
                "Prefer: return=representation",
                "Range: 0-999"
            ];

            foreach ($customHeaders as $header) {
                $headers[] = $header;
            }

            // Masking sensitive API keys in logs  to avoid leaking them
            $maskedHeaders = array_map(function($header) {
                if (stripos($header, 'apikey:') === 0 || stripos($header, 'authorization:') === 0) {
                    $parts = explode(' ', $header, 2);
                    if (count($parts) === 2) {
                        $key = trim($parts[1]);
                        $masked = substr($key, 0, 4) . str_repeat('*', max(0, strlen($key) - 8)) . substr($key, -4);
                        return $parts[0] . ' ' . $masked;
                    }
                }
                return $header;
            }, $headers);
            error_log("Making request to: " . $url);
            error_log("Request headers: " . json_encode($maskedHeaders));

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
                CURLOPT_TIMEOUT => 60, // Increased timeout
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // Force HTTP/1.1 to avoid h2 stream resets
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Force IPv4 to avoid IPv6 timeouts
                CURLOPT_TCP_KEEPALIVE => 1, // Enable TCP keep-alive
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
                $this->closeCurlHandle($ch);
                throw new Exception("cURL error: " . $error);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            error_log("Response HTTP code: " . $httpCode);
            error_log("Raw response: " . substr($response, 0, 1000)); 

            if ($httpCode >= 400) {
                $this->closeCurlHandle($ch);

               
                $message = "HTTP error {$httpCode}: {$response}";
                if ($httpCode === 401) {
                    $message = "Supabase 401 Unauthorized (invalid API key). " .
                        "Please verify SUPABASE_SERVICE_KEY is correct and has not expired. " .
                        "Check Supabase project -> Settings -> API -> Service role key.";
                } elseif ($httpCode === 404 && stripos($response, 'relation') !== false) {
                    $message = "Supabase 404 (table/view missing). " .
                        "Ensure the required table or view exists in your database schema.";
                }

                throw new Exception($message);
            }

            $this->closeCurlHandle($ch);

         
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
            
            if (in_array($method, ['PATCH', 'DELETE', 'PUT'])) {
                return true;
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

    /**
    
     *
     * @param string $endpoint
     * @param array  $data
     * @param string $query
     * @return mixed
     */
    public function put($endpoint, $data, $query = '') {
        return $this->update($endpoint, $data, $query);
    }

    public function delete($endpoint, $query = '') {
        $url = ltrim($endpoint, '/') . ($query ? '?' . $query : '');
        return $this->makeRequest($url, 'DELETE');
    }

    /**
    
     * @param string      $endpoint  
     * @param string      $query    
     * @param string|null $deletedBy
     * @return mixed 
     */
    public function softDelete($endpoint, $query = '', $deletedBy = null) {
        $data = ['deleted_at' => date('c')]; 
        if ($deletedBy) {
            $data['deleted_by'] = $deletedBy;
        }
        $url = ltrim($endpoint, '/') . ($query ? '?' . $query : '');
        return $this->makeRequest($url, 'PATCH', $data);
    }

    /**
     
     * @param string $endpoint Table name, e.g. "outlets"
     * @param string $query    PostgREST filter, e.g. "id=eq.123"
     * @return mixed Parsed response
     */
    public function restore($endpoint, $query = '') {
        $data = ['deleted_at' => null, 'deleted_by' => null];
        $url = ltrim($endpoint, '/') . ($query ? '?' . $query : '');
        return $this->makeRequest($url, 'PATCH', $data);
    }
}

// Global helper functions
function callSupabase($endpoint, $query = '') {
    global $supabaseUrl, $supabaseKey;
    $client = new SupabaseClient($supabaseUrl, $supabaseKey);
    return $client->get($endpoint, $query);
}

function callSupabaseWithServiceKey($endpoint, $method = 'GET', $data = null, $customHeaders = []) {
    global $supabaseUrl, $supabaseServiceKey, $supabaseKey;

    if (empty($supabaseServiceKey)) {
        error_log('callSupabaseWithServiceKey: SUPABASE_SERVICE_KEY/SERVICE_ROLE_KEY missing; falling back to SUPABASE_ANON_KEY for this request.');
        $supabaseServiceKey = $supabaseKey;
    }

    $client = new SupabaseClient($supabaseUrl, $supabaseServiceKey);

    try {
        
        $query = '';
        if ($method === 'GET' && is_array($data) && !empty($data)) {
            $params = [];
          
            if (isset($data['select'])) {
                $params[] = 'select=' . urlencode($data['select']);
            }
            if (isset($data['filters']) && is_array($data['filters'])) {
                foreach ($data['filters'] as $key => $value) {
                    $params[] = urlencode($key) . '=eq.' . urlencode($value);
                }
            }
            
            if (isset($data['order'])) {
                $params[] = 'order=' . urlencode($data['order']);
            }
            
           
            if (isset($data['limit'])) {
                $params[] = 'limit=' . intval($data['limit']);
            }
            
            if (isset($data['offset'])) {
                $params[] = 'offset=' . intval($data['offset']);
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