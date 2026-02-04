<?php
class SupabaseHelper {
    private $baseUrl;
    private $apiKey;

    public function __construct() {
        $configFile = __DIR__ . '/../config/supabase_config.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            $this->baseUrl = SUPABASE_URL . '/rest/v1';
            $this->apiKey = SUPABASE_SERVICE_ROLE_KEY;
        } else {
            $this->baseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co/rest/v1';
            $this->apiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
        }
    }

    public function get($table, $filter = '') {
        $url = $this->baseUrl . '/' . $table;
        if (!empty($filter)) {
            $url .= '?' . $filter;
        }

        return $this->makeRequest('GET', $url);
    }

    public function post($table, $data) {
        $url = $this->baseUrl . '/' . $table;

        return $this->makeRequest('POST', $url, $data, [
            'Prefer' => 'return=representation'
        ]);
    }



    public function insert($table, $data) {
        $url = $this->baseUrl . '/' . $table;

        $payload = is_array($data) && array_values($data) === $data ? $data : [$data];
        return $this->makeRequest('POST', $url, $payload, [
            'Prefer' => 'return=representation'
        ]);
    }

    public function patch($table, $data, $filter = '') {
        $url = $this->baseUrl . '/' . $table;
        if (!empty($filter)) {
            $url .= '?' . $filter;
        }

        return $this->makeRequest('PATCH', $url, $data);
    }

    public function delete($table, $filter) {
        $url = $this->baseUrl . '/' . $table . '?' . $filter;

        return $this->makeRequest('DELETE', $url);
    }

    private function makeRequest($method, $url, $data = null, $additionalHeaders = []) {
        $ch = curl_init();

        $headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        foreach ($additionalHeaders as $key => $value) {
            $headers[] = "$key: $value";
        }


        error_log("SupabaseHelper: request start - method={$method} url={$url}");


        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_LOW_SPEED_LIMIT => 10,
            CURLOPT_LOW_SPEED_TIME => 10,
            CURLOPT_FAILONERROR => false,
            CURLOPT_FORBID_REUSE => true
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMsg = isset($errorData['message']) ? $errorData['message'] : 'HTTP Error ' . $httpCode;

            if (isset($errorData['hint'])) {
                $errorMsg .= ' - Hint: ' . $errorData['hint'];
            }
            if (isset($errorData['details'])) {
                $errorMsg .= ' - Details: ' . $errorData['details'];
            }

            throw new Exception($errorMsg . ' (Response: ' . $response . ')');
        }

        return json_decode($response, true);
    }
}
