<?php
class SupabaseHelperWithBypass {
    private $baseUrl;
    private $apiKey;
    private $useBypassHeaders;

    public function __construct($useBypassHeaders = true) {
        $configFile = __DIR__ . '/../config/supabase_config.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            $this->baseUrl = SUPABASE_URL . '/rest/v1';
            $this->apiKey = SUPABASE_SERVICE_ROLE_KEY;
        } else {
            $this->baseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co/rest/v1';
            $this->apiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
        }

        $this->useBypassHeaders = $useBypassHeaders;
    }

    public function post($table, $data, $options = []) {
        $url = $this->baseUrl . '/' . $table;

        if (!isset($options['select'])) {
            $url .= '?select=*';
        } else {
            $url .= '?select=' . $options['select'];
        }

        return $this->makeRequest('POST', $url, $data, $options);
    }

    public function get($table, $filter = '') {
        $url = $this->baseUrl . '/' . $table;
        if (!empty($filter)) {
            $url .= '?' . $filter;
        }

        return $this->makeRequest('GET', $url);
    }

    public function delete($table, $filter) {
        $url = $this->baseUrl . '/' . $table . '?' . $filter;

        return $this->makeRequest('DELETE', $url);
    }

    private function makeRequest($method, $url, $data = null, $options = []) {
        $ch = curl_init();

        $headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        if ($this->useBypassHeaders) {
            $headers[] = 'X-Client-Info: supabase-js/2.0.0';
            $headers[] = 'X-Supabase-Auth: ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_VERBOSE => false
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMsg = 'HTTP Error ' . $httpCode;

            if (isset($errorData['message'])) {
                $errorMsg .= ': ' . $errorData['message'];
            } elseif (isset($errorData['error'])) {
                $errorMsg .= ': ' . $errorData['error'];
            } elseif (isset($errorData['hint'])) {
                $errorMsg .= ': ' . $errorData['hint'];
            }

            if (!empty($response) && strlen($response) < 500) {
                $errorMsg .= ' | Response: ' . $response;
            }

            throw new Exception($errorMsg);
        }

        $decoded = json_decode($response, true);

        if (empty($response)) {
            throw new Exception('Empty response from database - possible RLS blocking');
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg() . ' | Response: ' . substr($response, 0, 200));
        }

        return $decoded;
    }

    public function testConnection() {
        try {
            $result = $this->get('parcels', 'select=id&limit=1');
            return ['success' => true, 'message' => 'Connection successful', 'data' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    public function testInsert() {
        $testData = [
            'track_number' => 'BYPASS_TEST_' . time(),
            'company_id' => '7db2389c-b34d-4892-b93e-3023555ddd82',
            'sender_name' => 'Bypass Test',
            'receiver_name' => 'Test Receiver',
            'status' => 'pending'
        ];

        try {
            $result = $this->post('parcels', $testData);

            if (!empty($result) && isset($result[0]['id'])) {
                $this->delete('parcels', 'id=eq.' . $result[0]['id']);
                return ['success' => true, 'message' => 'INSERT test successful', 'data' => $result];
            } else {
                return ['success' => false, 'message' => 'INSERT returned empty result'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'INSERT test failed: ' . $e->getMessage()];
        }
    }
}
?>
