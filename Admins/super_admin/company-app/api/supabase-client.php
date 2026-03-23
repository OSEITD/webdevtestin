<?php
class SupabaseClient {
    private $supabaseUrl;
    private $supabaseKey;
    private $serviceRoleKey;
    private $defaultHeaders;

    public function getUrl() {
        return $this->supabaseUrl;
    }

    public function getKey() {
        return $this->supabaseKey;
    }

    public function __construct() {
        // Ensure env vars are loaded so we can correctly pick up the service role key
        if (!class_exists('EnvLoader')) {
            // Ensure we load the shared env loader used by the super_admin app.
            $envPath = __DIR__ . '/../../includes/env.php';
            if (file_exists($envPath)) {
                require_once $envPath;
            }
        }

        // If EnvLoader is available, load it.
        if (class_exists('EnvLoader')) {
            EnvLoader::load();
        }

        $this->supabaseUrl = EnvLoader::get('SUPABASE_URL');
        $this->supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');

        // Prefer service role key when available for elevated access to restricted tables.
        $this->serviceRoleKey = EnvLoader::get('SUPABASE_SERVICE_ROLE_KEY');
        if (empty($this->serviceRoleKey)) {
            $this->serviceRoleKey = EnvLoader::get('SUPABASE_SERVICE_KEY');
        }

        $this->defaultHeaders = [
            'apikey: ' . $this->supabaseKey,
            'Content-Type: application/json'
        ];
    }

    /**
     * Safely parse response returned by makeRequest.
     * makeRequest may return an array (already parsed) or a JSON string.
     */
    private function parseResponse($response) {
        if (is_array($response)) {
            return $response;
        }
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            // Return raw string if not valid JSON
            return $response;
        }
        // Unknown type - return as-is
        return $response;
    }

    /**
     * Authenticate user with email and password
     */
    public function signIn($email, $password) {
        $authUrl = "{$this->supabaseUrl}/auth/v1/token?grant_type=password";
        $payload = json_encode([
            'email' => $email,
            'password' => $password
        ]);

        $response = $this->makeRequest('POST', $authUrl, $payload);
        return $this->parseResponse($response);
    }

    /**
     * Get user profile by ID
     */
    public function getProfile($userId, $accessToken) {
        $url = "{$this->supabaseUrl}/rest/v1/profiles?select=*&id=eq.{$userId}";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        
        $response = $this->makeRequest('GET', $url, null, $headers);
        return $this->parseResponse($response);
    }

    /**
     * Get company by ID
     */
    public function getCompany($companyId, $accessToken) {
        $url = "{$this->supabaseUrl}/rest/v1/companies?id=eq.{$companyId}";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        
        $response = $this->makeRequest('GET', $url, null, $headers);
        return $this->parseResponse($response);
    }

    /**
     * Get all outlets for a company
     */
    public function getCompanyOutlets($companyId, $accessToken) {
        $url = "{$this->supabaseUrl}/rest/v1/outlets?company_id=eq.{$companyId}&deleted_at=is.null&select=*";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        
        $response = $this->makeRequest('GET', $url, null, $headers);
        return $this->parseResponse($response);
    }

    /**
     * Get all drivers for a company
     */
    public function getCompanyDrivers($companyId, $accessToken) {
        $url = "{$this->supabaseUrl}/rest/v1/drivers?company_id=eq.{$companyId}&deleted_at=is.null&select=*";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        
        $response = $this->makeRequest('GET', $url, null, $headers);
        return $this->parseResponse($response);
    }

    
    public static function normalizeDriverStatus(string $status): string {
        $s = strtolower(trim($status));
        $map = [
            'active' => 'available',
            'inactive' => 'unavailable',
            'busy' => 'unavailable',
            'offline' => 'unavailable',
            'available' => 'available',
            'unavailable' => 'unavailable'
        ];
        return $map[$s] ?? 'unavailable';
    }

    /**
     * Get deliveries for a company with optional filters
     */
    public function getDeliveries($companyId, $accessToken, $filters = []) {
        $query = "company_id=eq.{$companyId}";
        
        if (!empty($filters['status'])) {
            $query .= "&status=eq.{$filters['status']}";
        }
        // 'date' is a legacy key meaning created_at >= date
        if (!empty($filters['date'])) {
            $query .= "&created_at=gte.{$filters['date']}";
        }
        // support explicit start_date and end_date
        if (!empty($filters['start_date'])) {
            $query .= "&created_at=gte.{$filters['start_date']}";
        }
        if (!empty($filters['end_date'])) {
            $query .= "&created_at=lte.{$filters['end_date']}";
        }
        if (!empty($filters['driver_id'])) {
            $query .= "&driver_id=eq.{$filters['driver_id']}";
        }
        // Support filtering by origin_outlet_id (preferred) or legacy outlet_id
        if (!empty($filters['origin_outlet_id'])) {
            $query .= "&origin_outlet_id=eq.{$filters['origin_outlet_id']}";
        } elseif (!empty($filters['outlet_id'])) {
            // legacy param - interpret as origin_outlet_id to match table schema
            $query .= "&origin_outlet_id=eq.{$filters['outlet_id']}";
        }

        $url = "{$this->supabaseUrl}/rest/v1/deliveries?{$query}&deleted_at=is.null&select=*";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        
        $response = $this->makeRequest('GET', $url, null, $headers);
        return $this->parseResponse($response);
    }

    /**
     * Get parcels for a company with optional filters
     */
    public function getParcels($companyId, $accessToken, $filters = []) {
        $query = "company_id=eq.{$companyId}";

        if (!empty($filters['status'])) {
            $query .= "&status=eq.{$filters['status']}";
        }
        
        if (!empty($filters['exclude_statuses']) && is_array($filters['exclude_statuses'])) {
            $ex_statuses = implode(',', array_map('urlencode', $filters['exclude_statuses']));
            $query .= "&status=not.in.({$ex_statuses})";
        }

        // 'date' is a legacy key meaning created_at >= date
        if (!empty($filters['date'])) {
            $query .= "&created_at=gte.{$filters['date']}";
        }
        // support explicit start_date and end_date
        if (!empty($filters['start_date'])) {
            $query .= "&created_at=gte.{$filters['start_date']}";
        }
        if (!empty($filters['end_date'])) {
            $query .= "&created_at=lte.{$filters['end_date']}";
        }
        if (!empty($filters['driver_id'])) {
            $query .= "&driver_id=eq.{$filters['driver_id']}";
        }
        // Support filtering by origin_outlet_id (preferred) or legacy outlet_id
        if (!empty($filters['origin_outlet_id'])) {
            $query .= "&origin_outlet_id=eq.{$filters['origin_outlet_id']}";
        } elseif (!empty($filters['outlet_id'])) {
            // legacy param - interpret as origin_outlet_id to match table schema
            $query .= "&origin_outlet_id=eq.{$filters['outlet_id']}";
        }

        // Support filtering by multiple destination_outlet_ids
        if (!empty($filters['destination_outlet_ids']) && is_array($filters['destination_outlet_ids'])) {
            // e.g. &destination_outlet_id=in.(id1,id2,id3)
            $dest_ids = implode(',', $filters['destination_outlet_ids']);
            $query .= "&destination_outlet_id=in.({$dest_ids})";
        } elseif (!empty($filters['destination_outlet_id'])) {
            $query .= "&destination_outlet_id=eq.{$filters['destination_outlet_id']}";
        }

        $url = "{$this->supabaseUrl}/rest/v1/parcels?{$query}&deleted_at=is.null&select=*";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);

        $response = $this->makeRequest('GET', $url, null, $headers);
        return $this->parseResponse($response);
    }

    /**
     * Create a new outlet
     */
    public function createOutlet($outletData, $accessToken) {
        $url = "{$this->supabaseUrl}/rest/v1/outlets";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        
        $response = $this->makeRequest('POST', $url, json_encode($outletData), $headers);
        return $this->parseResponse($response);
    }

    /**
     * Create a new driver
     */
    public function createDriver($driverData, $accessToken) {
        $url = "{$this->supabaseUrl}/rest/v1/drivers";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        
        $response = $this->makeRequest('POST', $url, json_encode($driverData), $headers);
        return $this->parseResponse($response);
    }

    /**
     * Create a new delivery
     */
    public function createDelivery($deliveryData, $accessToken) {
        $url = "{$this->supabaseUrl}/rest/v1/deliveries";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        
        $response = $this->makeRequest('POST', $url, json_encode($deliveryData), $headers);
        return $this->parseResponse($response);
    }

    /**
     * Update delivery status
     */
    public function updateDeliveryStatus($deliveryId, $status, $accessToken) {
        $url = "{$this->supabaseUrl}/rest/v1/deliveries?id=eq.{$deliveryId}";
        $headers = array_merge($this->defaultHeaders, [
            'Authorization: Bearer ' . $accessToken,
            'Prefer: return=minimal'
        ]);
        
        $payload = json_encode(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->makeRequest('PATCH', $url, $payload, $headers);
    }

    /**
     * Get notifications for a company
     */
    public function getNotifications($companyId, $accessToken, $limit = 20) {
        $url = "{$this->supabaseUrl}/rest/v1/notifications?company_id=eq.{$companyId}&order=created_at.desc&limit={$limit}";
        $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        
        $response = $this->makeRequest('GET', $url, null, $headers);
        return $this->parseResponse($response);
    }

    /**
     * Create a new trip
     */
    public function createTrip($tripData) {
        $url = "{$this->supabaseUrl}/rest/v1/trips";
        try {
            error_log("=== Creating Trip Request ===");
            error_log("URL: " . $url);
            error_log("Data: " . json_encode($tripData, JSON_PRETTY_PRINT));
            
            // Prefer using the service role key for server-side trusted inserts when available
            $authKey = $this->serviceRoleKey ? $this->serviceRoleKey : $this->supabaseKey;
            if ($this->serviceRoleKey) {
                error_log('createTrip: using SERVICE ROLE key for trip creation');
            } else {
                error_log('createTrip: using anon/server key for trip creation (service role not configured)');
            }

            // Add all necessary headers including CORS and auth
            $headers = [
                'apikey: ' . $this->supabaseKey,
                'Authorization: Bearer ' . $authKey,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ];
            
            $jsonData = json_encode($tripData);
            error_log("Request Headers: " . print_r($headers, true));
            
            $response = $this->makeRequest('POST', $url, $jsonData, $headers);
            error_log("Raw Response: " . print_r($response, true));
            
            // Try to parse the response safely
            $decodedResponse = $this->parseResponse($response);
            if (is_string($decodedResponse)) {
                error_log("Response is not valid JSON: " . $decodedResponse);
            } else {
                error_log("Decoded Response: " . print_r($decodedResponse, true));
            }

            // Check if we got an error response
            if (is_array($decodedResponse) && isset($decodedResponse['error'])) {
                throw new Exception($decodedResponse['error']);
            }

            // Log the created trip ID if available (avoid accessing missing keys on input)
            $createdId = is_array($decodedResponse) && isset($decodedResponse[0]['id']) ? $decodedResponse[0]['id'] : null;
            error_log("Trip creation successful for ID: " . ($createdId ?? 'unknown'));
            return $decodedResponse ?? true;
            
        } catch (Exception $e) {
            error_log("Error in createTrip method: " . $e->getMessage());
            error_log("Request URL: " . $url);
            error_log("Request Data: " . json_encode($tripData, JSON_PRETTY_PRINT));
            throw new Exception("Failed to create trip: " . $e->getMessage());
        }
    }

    
    public function createTripStop($stopData) {
        $url = "{$this->supabaseUrl}/rest/v1/trip_stops";
        $authKey = $this->serviceRoleKey ? $this->serviceRoleKey : $this->supabaseKey;
        $headers = [
            'apikey: ' . $this->supabaseKey,
            'Authorization: Bearer ' . $authKey,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ];
        $response = $this->makeRequest('POST', $url, json_encode($stopData), $headers);
        return $this->parseResponse($response);
    }

    
    public function createParcelListAssignment($parcelData) {
    $url = "{$this->supabaseUrl}/rest/v1/parcel_list";
        $authKey = $this->serviceRoleKey ? $this->serviceRoleKey : $this->supabaseKey;
        $headers = [
            'apikey: ' . $this->supabaseKey,
            'Authorization: Bearer ' . $authKey,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ];
        $response = $this->makeRequest('POST', $url, json_encode($parcelData), $headers);
        return $this->parseResponse($response);
    }

    public function put($path, $data) {
        $url = "{$this->supabaseUrl}/rest/v1/{$path}";
       
        $authKey = $this->serviceRoleKey ? $this->serviceRoleKey : $this->supabaseKey;
        if ($this->serviceRoleKey) {
            error_log("Supabase PUT using service role key for path: {$path}");
        }
        $headers = array_merge($this->defaultHeaders, [
            'Authorization: Bearer ' . $authKey,
            'Prefer: return=minimal'
        ]);
        $response = $this->makeRequest('PATCH', $url, json_encode($data), $headers);
        return $this->parseResponse($response);
    }

    
    public function post($path, $data, $useServiceRole = false) {
        $url = "{$this->supabaseUrl}/rest/v1/{$path}";
        $authKey = ($useServiceRole && $this->serviceRoleKey) ? $this->serviceRoleKey : $this->supabaseKey;
        
        $headers = array_merge($this->defaultHeaders, [
            'Authorization: Bearer ' . $authKey,
            'Prefer: return=representation'
        ]);
        
        $response = $this->makeRequest('POST', $url, json_encode($data), $headers);
        return $this->parseResponse($response);
    }

    
    public function get($endpoint) {
        $url = "{$this->supabaseUrl}/rest/v1/{$endpoint}";
        $response = $this->makeRequest('GET', $url);
        
        $result = new stdClass();
        $result->data = null;
        
        
        if (is_array($response)) {
            $result->data = $response;
            return $result;
        }
        
        
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result->data = $decoded;
                return $result;
            }
        }
        
        error_log("Unexpected response type in get() method: " . gettype($response));
        return $result;
    }

  
    public function getRecord($endpoint, $useServiceRole = false) {
        $url = "{$this->supabaseUrl}/rest/v1/{$endpoint}";
        
        $authKey = ($useServiceRole && $this->serviceRoleKey) ? $this->serviceRoleKey : $this->supabaseKey;
        $headers = array_merge($this->defaultHeaders, [
            'Authorization: Bearer ' . $authKey
        ]);
        $response = $this->makeRequest('GET', $url, null, $headers);
        return $this->parseResponse($response);
    }

    
    public function delete($endpoint, $accessToken = null) {
        $url = "{$this->supabaseUrl}/rest/v1/{$endpoint}";
        
        
        if ($accessToken) {
             $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $accessToken]);
        } else {
             $authKey = $this->serviceRoleKey ?: $this->supabaseKey;
             $headers = array_merge($this->defaultHeaders, ['Authorization: Bearer ' . $authKey]);
        }
        
        $response = $this->makeRequest('DELETE', $url, null, $headers);
        return $this->parseResponse($response);
    }

    /**
   
     *
     * @param string      $endpoint    
     * @param string|null $accessToken 
     * @param string|null $deletedBy   
     * @return mixed Parsed response
     */
    public function softDelete($endpoint, $accessToken = null, $deletedBy = null) {
        $data = ['deleted_at' => date('c')]; 
        if ($deletedBy) {
            $data['deleted_by'] = $deletedBy;
        }

        $url = "{$this->supabaseUrl}/rest/v1/{$endpoint}";

        if ($accessToken) {
            $headers = array_merge($this->defaultHeaders, [
                'Authorization: Bearer ' . $accessToken,
                'Prefer: return=minimal'
            ]);
        } else {
            $authKey = $this->serviceRoleKey ?: $this->supabaseKey;
            $headers = array_merge($this->defaultHeaders, [
                'Authorization: Bearer ' . $authKey,
                'Prefer: return=minimal'
            ]);
        }

        $response = $this->makeRequest('PATCH', $url, json_encode($data), $headers);
        return $this->parseResponse($response);
    }

    /**
     * Restore a soft-deleted record: sets deleted_at and deleted_by back to NULL.
     *
     * @param string      $endpoint    PostgREST endpoint with filters, e.g. "outlets?id=eq.123"
     * @param string|null $accessToken JWT access token
     * @return mixed Parsed response
     */
    public function restore($endpoint, $accessToken = null) {
        $data = ['deleted_at' => null, 'deleted_by' => null];

        $url = "{$this->supabaseUrl}/rest/v1/{$endpoint}";

        if ($accessToken) {
            $headers = array_merge($this->defaultHeaders, [
                'Authorization: Bearer ' . $accessToken,
                'Prefer: return=minimal'
            ]);
        } else {
            $authKey = $this->serviceRoleKey ?: $this->supabaseKey;
            $headers = array_merge($this->defaultHeaders, [
                'Authorization: Bearer ' . $authKey,
                'Prefer: return=minimal'
            ]);
        }

        $response = $this->makeRequest('PATCH', $url, json_encode($data), $headers);
        return $this->parseResponse($response);
    }

   
    public function getWithToken($endpoint, $accessToken) {
        $url = "{$this->supabaseUrl}/rest/v1/{$endpoint}";
        $headers = array_merge($this->defaultHeaders, [
            'Authorization: Bearer ' . $accessToken
        ]);
        $response = $this->makeRequest('GET', $url, null, $headers);
        return $this->parseResponse($response);
    }

    /**
     * Helper method to make HTTP requests
     */
    private function makeRequest($method, $url, $data = null, $headers = null, $retry = true): mixed {
        error_log("=== Starting HTTP Request ===");
        error_log("Method: {$method}");
        error_log("URL: {$url}");
        if ($data) {
            error_log("Request data: " . $data);
        }
        
        $ch = curl_init($url);
        
        // Set CURL options for debugging
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        
        // Create a stream for CURL debug info
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        
        $finalHeaders = $headers !== null ? $headers : $this->defaultHeaders;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);
        
        error_log("Request headers: " . print_r($finalHeaders, true));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        // If curl_exec failed, capture the verbose log and throw a helpful exception
        if ($response === false) {
            // capture debug info
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            error_log("=== CURL Exec Failed ===\n" . $verboseLog);
            fclose($verbose);
            $errMsg = $error ?: 'Unknown cURL error';
            curl_close($ch);
            throw new Exception("cURL exec failed: {$errMsg}");
        }

        // Separate headers and body
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        curl_close($ch);

        error_log("=== Response Information ===");
        error_log("Status Code: " . $statusCode);
        error_log("Headers: " . $responseHeaders);
        error_log("Body: " . $responseBody);

        // Get CURL debug information
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("=== CURL Debug Information ===");
        error_log($verboseLog);
        fclose($verbose);

        if ($error) {
            error_log("=== CURL Error ===");
            error_log($error);
            throw new Exception("cURL Error: $error");
        }

        if ($statusCode === 401 && $retry && (stripos($responseBody, 'JWT expired') !== false || stripos($responseBody, 'invalid_token') !== false)) {
            // Attempt to refresh the access token once and retry the request.
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $refreshToken = $_SESSION['refresh_token'] ?? null;
            if (!empty($refreshToken)) {
                try {
                    $newTokens = $this->refreshToken($refreshToken);
                    if (!empty($newTokens['access_token'])) {
                        $_SESSION['access_token'] = $newTokens['access_token'];
                        if (!empty($newTokens['refresh_token'])) {
                            $_SESSION['refresh_token'] = $newTokens['refresh_token'];
                        }
                        // Update request Authorization header if present
                        foreach ($finalHeaders as &$hdr) {
                            if (stripos($hdr, 'Authorization: Bearer ') === 0) {
                                $hdr = 'Authorization: Bearer ' . $newTokens['access_token'];
                                break;
                            }
                        }
                        // Retry once with refreshed token
                        curl_close($ch);
                        return $this->makeRequest($method, $url, $data, $finalHeaders, false);
                    }
                } catch (Exception $refreshEx) {
                    error_log('SupabaseClient refreshToken failed: ' . $refreshEx->getMessage());
                }
            }
        }

        if ($statusCode >= 400) {
            error_log("=== HTTP Error ===");
            $errorData = json_decode($responseBody, true);
            $errorMessage = isset($errorData['message']) ? $errorData['message'] : $responseBody;
            error_log("Error Message: " . $errorMessage);
            throw new Exception("HTTP Error {$statusCode}: {$errorMessage}");
        }

        // For 2xx status codes, consider it successful
        if ($statusCode >= 200 && $statusCode < 300) {
            if (empty($responseBody)) {
                // Empty response with success status code is valid
                return true;
            }
            
            // Try to parse response as JSON if there is a body
            $parsed = json_decode($responseBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
            
            // If not JSON but successful status code, just return true
            return true;
        }

        error_log("=== Unexpected Response ===");
        error_log("Status Code: " . $statusCode);
        error_log("Response Body: " . $responseBody);
        return null;
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken($refreshToken) {
        $url = "{$this->supabaseUrl}/auth/v1/token?grant_type=refresh_token";
        $payload = json_encode(['refresh_token' => $refreshToken]);
        
        $response = $this->makeRequest('POST', $url, $payload);
        return $this->parseResponse($response);
    }
}

// Example usage:
/*
try {
    $supabase = new SupabaseClient();
    
    // Sign in
    $auth = $supabase->signIn('user@example.com', 'password');
    $accessToken = $auth['access_token'];
    
    // Get company outlets
    $outlets = $supabase->getCompanyOutlets($companyId, $accessToken);
    
    // Create a new delivery
    $deliveryData = [
        'company_id' => $companyId,
        'outlet_id' => $outletId,
        'status' => 'pending',
        // ... other delivery data
    ];
    $delivery = $supabase->createDelivery($deliveryData, $accessToken);
    
} catch (Exception $e) {
    // Handle error
    error_log($e->getMessage());
}
*/
?>
