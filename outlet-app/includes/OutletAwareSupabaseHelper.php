<?php
class OutletAwareSupabaseHelper {

    private $url;
    private $key;
    private $companyId;
    private $outletId;
    private $userId;
    private $role;
    private $tablesWithoutCompanyFilter = [
        'trip_stops',
        'parcels',
        'profiles',
        'outlets',
        'delivery_events',
        'push_subscriptions',
        'notification_logs'
    ];

    public function __construct() {
        // Load .env if not already loaded
        if (!class_exists('EnvLoader')) {
            require_once __DIR__ . '/env.php';
        }
        EnvLoader::load();

        $this->url = getenv('SUPABASE_URL');
        $this->key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');

        if (empty($this->url) || empty($this->key)) {
            throw new RuntimeException(
                'Supabase credentials are not configured. ' .
                'Please set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in outlet-app/.env'
            );
        }

        $this->companyId = $_SESSION['company_id'] ?? null;
        $this->outletId = $_SESSION['outlet_id'] ?? null;
        $this->userId = $_SESSION['user_id'] ?? null;
        $this->role = $_SESSION['role'] ?? null;

        // Only log context when company ID is present (for debugging authenticated requests)
        if ($this->companyId) {
            error_log('SupabaseHelper context - Company: ' . $this->companyId . ', User: ' . $this->userId . ', Role: ' . $this->role);
        }
    }

    public function get($table, $filters = '', $select = '*') {
        $applyCompanyFilter = $this->shouldApplyCompanyFilter($table);
        
        // Warn if company ID is missing for tables that require it
        if ($applyCompanyFilter && !$this->companyId) {
            error_log("Warning: Company ID is missing for table '$table' which requires company filtering");
        }
        
        $companyFilter = $applyCompanyFilter ? 'company_id=eq.' . $this->companyId : '';
        if ($companyFilter && $filters) {
            $finalFilters = $companyFilter . '&' . $filters;
        } elseif ($companyFilter) {
            $finalFilters = $companyFilter;
        } else {
            $finalFilters = $filters;
        }

        $queryParts = [
            'select=' . urlencode($select)
        ];
        if ($finalFilters) {
            $queryParts[] = $finalFilters;
        }

        $url = $this->url . '/rest/v1/' . $table . '?' . implode('&', $queryParts);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    "apikey: " . $this->key,
                    "Authorization: Bearer " . $this->key,
                    "Content-Type: application/json"
                ],
                'ignore_errors' => true,
                'timeout' => 8 // Reduced timeout from 10s to 8s for faster failure detection
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
        $applyCompanyFilter = $this->shouldApplyCompanyFilter($table);
        
        // Warn if company ID is missing for tables that require it
        if ($applyCompanyFilter && !$this->companyId) {
            error_log("Warning: Company ID is missing for table '$table' which requires company filtering");
        }
        
        $companyFilter = $applyCompanyFilter ? 'company_id=eq.' . $this->companyId : '';
        if ($companyFilter && $filters) {
            $finalFilters = $companyFilter . '&' . $filters;
        } elseif ($companyFilter) {
            $finalFilters = $companyFilter;
        } else {
            $finalFilters = $filters;
        }

        $url = $this->url . '/rest/v1/' . $table;
        if ($finalFilters) {
            $url .= '?' . $finalFilters;
        }
        $options = [
            'http' => [
                'method' => 'PATCH',
                'header' => 'Authorization: Bearer ' . $this->key . "\r\n" .
                           'apikey: ' . $this->key . "\r\n" .
                           'Content-Type: application/json',
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        error_log("Supabase update request for $table: URL=$url, Data=" . json_encode($data));
        error_log("Supabase update response for $table: " . ($response ?: 'empty/false'));

        $httpResponseHeader = $http_response_header ?? [];
        $statusLine = $httpResponseHeader[0] ?? 'Unknown status';
        error_log("HTTP response status: $statusLine");

        if ($response === false) {
            error_log("Supabase update failed for $table with filters: $finalFilters");
            return false;
        }

        // Detect Supabase/PostgREST error responses
        if (!empty($response)) {
            $decoded = @json_decode($response, true);
            if (is_array($decoded) && isset($decoded['code']) && isset($decoded['message'])) {
                error_log("Supabase update error for $table: code={$decoded['code']}, message={$decoded['message']}");
                return false;
            }
        }

        return true;
    }

    public function insert($table, $data) {
        // Warn if inserting data that should have company_id but doesn't
        if ($this->shouldApplyCompanyFilter($table) && !$this->companyId && !isset($data['company_id'])) {
            error_log("Warning: Company ID is missing for table '$table' which requires company filtering");
        }
        
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

    public function delete($table, $filters = '') {
        $applyCompanyFilter = $this->shouldApplyCompanyFilter($table);
        if ($applyCompanyFilter && !$this->companyId) {
            error_log("Warning: Company ID is missing for table '$table' which requires company filtering");
        }

        $companyFilter = $applyCompanyFilter ? 'company_id=eq.' . $this->companyId : '';
        if ($companyFilter && $filters) {
            $finalFilters = $companyFilter . '&' . $filters;
        } elseif ($companyFilter) {
            $finalFilters = $companyFilter;
        } else {
            $finalFilters = $filters;
        }

        $url = $this->url . '/rest/v1/' . $table;
        if ($finalFilters) {
            $url .= '?' . $finalFilters;
        }
        $options = [
            'http' => [
                'method' => 'DELETE',
                'header' => 'Authorization: Bearer ' . $this->key . "\r\n" .
                           'apikey: ' . $this->key . "\r\n" .
                           'Content-Type: application/json',
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        error_log("Supabase delete request for $table: URL=$url");
        error_log("Supabase delete response for $table: " . ($response ?: 'empty/false'));

        $httpResponseHeader = $http_response_header ?? [];
        $statusLine = $httpResponseHeader[0] ?? 'Unknown status';
        error_log("HTTP response status: $statusLine");

        return $response !== false;
    }

    public function getContextInfo() {
        return [
            'company_id' => $this->companyId,
            'outlet_id' => $this->outletId,
            'user_id' => $this->userId,
            'role' => $this->role
        ];
    }

    public function getUrl(): string { return $this->url; }
    public function getKey(): string { return $this->key; }

    private function shouldApplyCompanyFilter($table) {
        return !in_array($table, $this->tablesWithoutCompanyFilter, true);
    }
}
?>