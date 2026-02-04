<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Supabase\CreateClient;


function getSupabaseClient() {
    
    $envPath = __DIR__ . '/../../.env';
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
       
        $supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
        $supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';
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
                
                public function __construct($restUrl, $key, $table) {
                    $this->restUrl = $restUrl;
                    $this->key = $key;
                    $this->table = $table;
                }
                
                public function select($fields = '*') {
                    $this->selectFields = $fields;
                    return $this;
                }
                
                public function limit($limit) {
                    $this->limitValue = $limit;
                    return $this;
                }
                
                public function eq($column, $value) {
                    $this->conditions[] = urlencode($column) . '=eq.' . urlencode($value);
                    return $this;
                }
                
                public function order($column, $options = []) {
                    $ascending = isset($options['ascending']) ? $options['ascending'] : true;
                    $direction = $ascending ? 'asc' : 'desc';
                    $this->orderBy = urlencode($column) . '.' . $direction;
                    return $this;
                }
                
                public function execute() {
                    $url = $this->restUrl . '/' . $this->table . '?select=' . urlencode($this->selectFields);
                    
                    if (!empty($this->conditions)) {
                        $url .= '&' . implode('&', $this->conditions);
                    }
                    
                    if ($this->orderBy !== null) {
                        $url .= '&order=' . $this->orderBy;
                    }
                    
                    if ($this->limitValue !== null) {
                        $url .= '&limit=' . $this->limitValue;
                    }
                    
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
                        return (object)['data' => json_decode($response, true)];
                    } else {
                        throw new Exception('HTTP ' . $httpCode . ': ' . $response);
                    }
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