<?php
class OutletAwareSupabaseHelper {

    private $url;
    private $key;
    private $companyId;
    private $outletId;
    private $userId;
    private $role;

    public function __construct() {
        $this->url = "https://xerpchdsykqafrsxbqef.supabase.co";
        $this->key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";

        $this->companyId = $_SESSION['company_id'] ?? null;
        $this->outletId = $_SESSION['outlet_id'] ?? null;
        $this->userId = $_SESSION['user_id'] ?? null;
        $this->role = $_SESSION['role'] ?? null;

        if (!$this->companyId) {
            throw new Exception('Company ID is required for multi-tenant operations');
        }
    }

    public function get($table, $filters = '', $select = '*') {
        $companyFilter = "company_id=eq." . $this->companyId;
        $finalFilters = $filters ? "$companyFilter&$filters" : $companyFilter;

        $url = $this->url . "/rest/v1/" . $table . "?select=" . urlencode($select) . "&" . $finalFilters;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    "apikey: " . $this->key,
                    "Authorization: Bearer " . $this->key,
                    "Content-Type: application/json"
                ],
                'ignore_errors' => true,
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log("Supabase GET failed for table: $table");
            return [];
        }

        $decoded = @json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON response from table $table: " . $response);
            return [];
        }

        if (is_array($decoded) && isset($decoded['code']) && isset($decoded['message'])) {
            error_log("Supabase error response from table $table: " . $response);
            return [];
        }

        return $decoded ?: [];
    }

    public function update($table, $data, $filters = '') {
        $companyFilter = "company_id=eq." . $this->companyId;
        $finalFilters = $filters ? "$companyFilter&$filters" : $companyFilter;
        $url = $this->url . "/rest/v1/" . $table . "?" . $finalFilters;
        $options = [
            'http' => [
                'method' => 'PATCH',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json'
                ],
                'content' => json_encode($data)
            ]
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        return $response ? json_decode($response, true) : [];
    }

    public function insert($table, $data) {
        $url = $this->url . "/rest/v1/" . $table;
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ],
                'content' => json_encode([$data])
            ]
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        return $response ? json_decode($response, true) : [];
    }

    public function getContextInfo() {
        return [
            'company_id' => $this->companyId,
            'outlet_id' => $this->outletId,
            'user_id' => $this->userId,
            'role' => $this->role
        ];
    }
}
?>