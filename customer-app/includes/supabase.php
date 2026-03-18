<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Supabase\CreateClient;


function getSupabaseClient() {
    
    // Look for .env in customer-app/ first, then fall back to workspace root
    $envPath = file_exists(__DIR__ . '/../.env') ? __DIR__ . '/../.env' : __DIR__ . '/../../.env';
    if (file_exists($envPath)) {
        try {
            $envContent = file_get_contents($envPath);
            $lines = explode("\n", $envContent);
            
            foreach ($lines as $line) {
                $line = trim($line);
               
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                   
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    
                    $_ENV[$key] = $value;
                }
            }
        } catch (Exception $e) {
            error_log("Error loading .env file: " . $e->getMessage());
        }
    }
    
    
    $supabaseUrl = $_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL');
    $supabaseKey = $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? $_ENV['SUPABASE_KEY'] ?? $_ENV['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_SERVICE_ROLE_KEY') ?? getenv('SUPABASE_ANON_KEY') ?? getenv('SUPABASE_KEY');
    
    
    if (empty($supabaseUrl) || empty($supabaseKey)) {
        throw new RuntimeException(
            'Supabase credentials are not configured. ' .
            'Please set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in your .env file.'
        );
    }
    

    return new class($supabaseUrl, $supabaseKey) {
        private $url;
        private $key;
        private $restUrl;
        
        public function __construct($url, $key) {
            $this->url = $url;
            $this->key = $key;
            $this->restUrl = $url . '/rest/v1';
        }
        
        public function getRestUrl() {
            return $this->restUrl;
        }
        
        public function getApiKey() {
            return $this->key;
        }
        
        public function getUrl() {
            return $this->url;
        }
        
        public function from($table) {
            return new class($this->restUrl, $this->key, $table) {
                private $restUrl;
                private $key;
                private $table;
                private $selectFields = '*';
                private $limitValue = null;
                private $conditions = [];
                private $orderBy = null;
                private $extraQueryParams = [];
                private $single = false;
                
                public function __construct($restUrl, $key, $table) {
                    $this->restUrl = $restUrl;
                    $this->key = $key;
                    $this->table = $table;
                }
                
                public function select($fields = '*', $extra = []) {
                    $this->selectFields = $fields;

                    // Allow passing additional query parameters (e.g. count=exact)
                    // Usage: ->select('id', ['count' => 'exact'])
                    if (is_array($extra)) {
                        $this->extraQueryParams = $extra;
                    } elseif (!empty($extra)) {
                        // Support raw string param injection as well
                        $this->extraQueryParams = ['__raw' => $extra];
                    }

                    return $this;
                }
                
                public function limit($limit) {
                    $this->limitValue = $limit;
                    return $this;
                }

                public function single() {
                    $this->single = true;
                    // single() implies limit=1
                    $this->limitValue = 1;
                    return $this;
                }
                
                public function eq($column, $value) {
                    $this->conditions[] = urlencode($column) . '=eq.' . urlencode($value);
                    return $this;
                }

                public function gte($column, $value) {
                    $this->conditions[] = urlencode($column) . '=gte.' . urlencode($value);
                    return $this;
                }

                public function lte($column, $value) {
                    $this->conditions[] = urlencode($column) . '=lte.' . urlencode($value);
                    return $this;
                }

                public function order($column, $options = []) {
                    $ascending = isset($options['ascending']) ? $options['ascending'] : true;
                    $direction = $ascending ? 'asc' : 'desc';
                    $this->orderBy = urlencode($column) . '.' . $direction;
                    return $this;
                }

                private function buildUrl($includeSelect = true) {
                    $url = $this->restUrl . '/' . $this->table;
                    $queryParts = [];

                    if ($includeSelect) {
                        $queryParts[] = 'select=' . urlencode($this->selectFields);
                    }

                    if (!empty($this->conditions)) {
                        $queryParts[] = implode('&', $this->conditions);
                    }

                    if ($this->orderBy !== null) {
                        $queryParts[] = 'order=' . $this->orderBy;
                    }

                    if ($this->limitValue !== null) {
                        $queryParts[] = 'limit=' . $this->limitValue;
                    }

                    if (!empty($this->extraQueryParams)) {
                        foreach ($this->extraQueryParams as $k => $v) {
                            if ($k === '__raw') {
                                $queryParts[] = $v;
                            } else {
                                $queryParts[] = urlencode($k) . '=' . urlencode($v);
                            }
                        }
                    }

                    if (!empty($queryParts)) {
                        $url .= '?' . implode('&', $queryParts);
                    }

                    return $url;
                }

                private function buildResponse($httpCode, $response) {
                    $decoded = json_decode($response, true);

                    if ($this->single) {
                        $row = $decoded[0] ?? null;
                        if (is_array($row)) {
                            $row = json_decode(json_encode($row));
                        }
                        return (object)[
                            'status' => $httpCode,
                            'data' => $row,
                        ];
                    }

                    return (object)[
                        'status' => $httpCode,
                        'data' => $decoded,
                    ];
                }

                public function execute() {
                    $url = $this->buildUrl();

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . $this->key,
                        'Authorization: Bearer ' . $this->key,
                        'Content-Type: application/json'
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode >= 200 && $httpCode < 300) {
                        return $this->buildResponse($httpCode, $response);
                    } else {
                        throw new Exception('HTTP ' . $httpCode . ': ' . $response);
                    }
                }

                public function insert($data) {
                    $url = $this->restUrl . '/' . $this->table;

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . $this->key,
                        'Authorization: Bearer ' . $this->key,
                        'Content-Type: application/json',
                        'Prefer: return=representation'
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode >= 200 && $httpCode < 300) {
                        return $this->buildResponse($httpCode, $response);
                    }

                    throw new Exception('HTTP ' . $httpCode . ': ' . $response);
                }

                public function update($data) {
                    $url = $this->buildUrl(false);

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . $this->key,
                        'Authorization: Bearer ' . $this->key,
                        'Content-Type: application/json',
                        'Prefer: return=representation'
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode >= 200 && $httpCode < 300) {
                        return $this->buildResponse($httpCode, $response);
                    }

                    throw new Exception('HTTP ' . $httpCode . ': ' . $response);
                }

                public function delete() {
                    $url = $this->buildUrl(false);

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . $this->key,
                        'Authorization: Bearer ' . $this->key,
                        'Content-Type: application/json'
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode >= 200 && $httpCode < 300) {
                        return $this->buildResponse($httpCode, $response);
                    }

                    throw new Exception('HTTP ' . $httpCode . ': ' . $response);
                }
            };
        }
    };
}


function safeQuery($table, $select = '*', $conditions = []) {
    try {
        $supabase = getSupabaseClient();
        $query = $supabase->from($table)->select($select);
        
        foreach ($conditions as $column => $value) {
            $query = $query->eq($column, $value);
        }
        
        $response = $query->execute();
        
        return [
            'success' => true,
            'data' => $response->data ?? []
        ];
    } catch (Exception $e) {
        error_log("Database query error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'data' => []
        ];
    }
}


function logDatabaseError($operation, $error) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] Database Error - $operation: " . $error . "\n";
    
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    error_log($logMessage, 3, $logDir . '/database_errors.log');
    
    return 'A database error occurred. Please try again later.';
}
?>