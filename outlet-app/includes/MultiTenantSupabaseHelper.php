<?php
class MultiTenantSupabaseHelper {
    private $url;
    private $key;
    private $companyId;
    
    
    private $tablesWithoutCompanyFilter = [
        'profiles',
        'global_customers',
        'push_subscriptions',
        'notification_logs'
    ];

    public function getUrl() {
        return $this->url;
    }

    public function getKey() {
        return $this->key;
    }

    public function __construct($companyId = null) {
        $this->url = getenv('SUPABASE_URL') ?: 'https://xerpchdsykqafrsxbqef.supabase.co';
        $this->key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
        $this->companyId = $companyId ?? $_SESSION['company_id'] ?? null;

        if (!$this->companyId) {
            throw new Exception('Company ID is required for multi-tenant operations');
        }

        $this->setTenantContext();
    }

    private function setTenantContext() {
        return true;
    }

    private function executeRLS($command) {
        return true;
    }

    public function post($table, $data) {
        if (!in_array($table, $this->tablesWithoutCompanyFilter)) {
            $data['company_id'] = $this->companyId;
        }
        $this->validateTenantTable($table);

        if ($table === 'parcels') {
            return $this->postParcelAlternative($data);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json'
                ],
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($this->url . "/rest/v1/" . $table, false, $context);

        if ($response === false) {
            error_log("Supabase POST failed for table: $table");
            error_log("Data sent: " . json_encode($data));
            throw new Exception("Database operation failed for table: $table");
        }

        error_log("Supabase POST response for table $table: " . $response);

        if (empty($response) || trim($response) === '') {
            error_log("Empty response from Supabase POST for table: $table - assuming success");
            if (in_array($table, ['trips', 'trip_stops', 'parcel_list']) && isset($data['id'])) {
                usleep(100000);
                try {
                    $createdRecord = $this->get($table, "id=eq." . $data['id']);
                    if (!empty($createdRecord)) {
                        return $createdRecord;
                    }
                } catch (Exception $e) {
                    error_log("Could not fetch created record: " . $e->getMessage());
                }
            }
            return [$data];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from database: " . $response);
        }

        if (is_array($decoded) && isset($decoded['code']) && isset($decoded['message'])) {
            error_log("Supabase error response: " . $response);
            throw new Exception("Database error: " . $decoded['message']);
        }

        return $decoded;
    }

    public function postGlobal($table, $data) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json'
                ],
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($this->url . "/rest/v1/" . $table, false, $context);

        if ($response === false) {
            error_log("Supabase POST failed for global table: $table");
            error_log("Data sent: " . json_encode($data));
            throw new Exception("Database operation failed for global table: $table");
        }

        error_log("Supabase response for global table $table: " . $response);

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from database: " . $response);
        }

        if (is_array($decoded) && isset($decoded['code']) && isset($decoded['message'])) {
            error_log("Supabase error response: " . $response);
            throw new Exception("Database error: " . $decoded['message']);
        }

        return $decoded;
    }

    public function getGlobal($table, $filters = '', $select = '*') {
        $url = $this->url . "/rest/v1/" . $table . "?select=" . $select;
        if ($filters) {
            $url .= "&" . $filters;
        }

        error_log("getGlobal URL: $url");

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json'
                ],
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            error_log("Supabase GET failed for global table: $table");
            return [];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON response from global table $table: " . $response);
            return [];
        }

        if (is_array($decoded) && isset($decoded['code']) && isset($decoded['message'])) {
            error_log("Supabase error response from global table $table: " . $response);
            return [];
        }

        return $decoded;
    }

    private function postParcelAlternative($data) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    "Authorization: Bearer {$this->key}",
                    "apikey: {$this->key}",
                    "Content-Type: application/json",
                    "Prefer: return=representation"
                ],
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents("{$this->url}/rest/v1/parcels", false, $context);

        if ($response === false) {
            throw new Exception("Failed to create parcel - network error");
        }

        if (empty($response) || trim($response) === '') {
            return [
                [
                    'success' => true,
                    'track_number' => $data['track_number'],
                    'company_id' => $data['company_id'],
                    'status' => 'created_successfully'
                ]
            ];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if (!empty($response)) {
                return [
                    [
                        'success' => true,
                        'track_number' => $data['track_number'],
                        'company_id' => $data['company_id'],
                        'response' => $response,
                        'status' => 'created'
                    ]
                ];
            }
            throw new Exception("Invalid response from database: " . $response);
        }

        if (isset($decoded['code']) && isset($decoded['message'])) {
            if ($decoded['code'] === '42P10') {
                throw new Exception("Database trigger error - ON CONFLICT issue in parcels table triggers. Contact database administrator to fix triggers.");
            }
            throw new Exception("Database error: " . $decoded['message']);
        }

        return $decoded ?: [
            [
                'success' => true,
                'track_number' => $data['track_number'],
                'company_id' => $data['company_id'],
                'status' => 'created'
            ]
        ];
    }

    public function get($table, $filters = '', $select = '*') {
        $this->validateTenantTable($table);

        
        if (in_array($table, $this->tablesWithoutCompanyFilter)) {
            $finalFilters = $filters;
        } else {
            $companyFilter = "company_id=eq." . $this->companyId;
            $finalFilters = $filters ? "$companyFilter&$filters" : $companyFilter;
        }

        $url = $this->url . "/rest/v1/" . $table . "?select=" . urlencode($select);
        if ($finalFilters) {
            $url .= "&" . $finalFilters;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json'
                ]
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            error_log("Supabase GET failed for table: $table");
            return [];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON response from table $table: " . $response);
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }

    public function put($endpoint, $data) {
        if (isset($data['company_id']) && $data['company_id'] !== $this->companyId) {
            throw new Exception('Cannot modify company_id in multi-tenant environment');
        }

        $endpoint = $this->addCompanyFilter($endpoint);

        $context = stream_context_create([
            'http' => [
                'method' => 'PATCH',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ],
                'content' => json_encode($data)
            ]
        ]);

        $response = file_get_contents($this->url . "/rest/v1/" . $endpoint, false, $context);

        if ($response === false) {
            error_log("Supabase PUT failed for endpoint: $endpoint");
            error_log("PUT data was: " . json_encode($data));
            return false;
        }

        $decoded = json_decode($response, true);
        error_log("PUT response for endpoint $endpoint: " . $response);

        return $decoded;
    }

    public function validateCrossTenantReferences($data, $table) {
        $validationRules = [
            'parcels' => [
                'origin_outlet_id' => 'outlets',
                'destination_outlet_id' => 'outlets'
            ],
            'parcel_list' => [
                'parcel_id' => 'parcels',
                'trip_id' => 'trips',
                'outlet_id' => 'outlets',
                'trip_stop_id' => 'trip_stops'
            ],
            'trip_stops' => [
                'trip_id' => 'trips',
                'outlet_id' => 'outlets'
            ],
            'payments' => [
                'parcel_id' => 'parcels'
            ]
        ];

        if (!isset($validationRules[$table])) {
            return true;
        }

        foreach ($validationRules[$table] as $field => $refTable) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!$this->validateEntityBelongsToTenant($refTable, $data[$field])) {
                    throw new Exception("Referenced {$refTable} does not belong to current tenant");
                }
            }
        }

        return true;
    }

    private function validateEntityBelongsToTenant($table, $entityId) {
        $result = $this->get($table, "id=eq.$entityId", 'id');
        if ($result === null || $result === false) {
            return false;
        }
        return is_array($result) && !empty($result);
    }

    private function validateTenantTable($table) {
        $tenantTables = [
            'parcels', 'parcel_list', 'trips', 'trip_stops', 'vehicle', 'payments',
            'delivery_events', 'outlets', 'notifications', 'customers', 'guest_customers',
            'billing_configs', 'customer_interactions', 'drivers', 'help', 'profiles',
            'global_customers', 'push_subscriptions', 'notification_logs'
        ];

        if (!in_array($table, $tenantTables)) {
            throw new Exception("Table $table is not configured for multi-tenancy");
        }
    }

    private function addCompanyFilter($endpoint) {
        $separator = strpos($endpoint, '?') !== false ? '&' : '?';
        return $endpoint . $separator . "company_id=eq." . $this->companyId;
    }

    public function getCompanyId() {
        return $this->companyId;
    }

    public function createParcel($parcelData) {
        $skipTrackingCheck = isset($parcelData['_skip_tracking_check']) && $parcelData['_skip_tracking_check'];

        if (isset($parcelData['_skip_tracking_check'])) {
            unset($parcelData['_skip_tracking_check']);
        }

        $this->validateCrossTenantReferences($parcelData, 'parcels');

        if (!$skipTrackingCheck) {
            try {
                $existingParcel = $this->get('parcels', "track_number=eq." . $parcelData['track_number'], 'id');
                if ($existingParcel !== false && !empty($existingParcel)) {
                    throw new Exception('Tracking number already exists for this company');
                }
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'tracking number') !== false) {
                    throw $e;
                }
                error_log("Warning: Could not validate tracking number uniqueness: " . $e->getMessage());
            }
        }

        return $this->post('parcels', $parcelData);
    }

    public function createParcelListAssignment($assignmentData) {
        $this->validateCrossTenantReferences($assignmentData, 'parcel_list');

        $parcel = $this->get('parcels', "id=eq." . $assignmentData['parcel_id'], 'company_id');
        $trip = $this->get('trips', "id=eq." . $assignmentData['trip_id'], 'company_id');
        $outlet = $this->get('outlets', "id=eq." . $assignmentData['outlet_id'], 'company_id');

        if (!is_array($parcel) || empty($parcel) || !is_array($trip) || empty($trip) || !is_array($outlet) || empty($outlet)) {
            throw new Exception('Referenced entities do not exist in current tenant');
        }

        if (!empty($assignmentData['trip_stop_id'])) {
            $tripStop = $this->get('trip_stops', "id=eq." . $assignmentData['trip_stop_id'], 'company_id,trip_id');
            if ($tripStop === false || $tripStop === null || empty($tripStop) || !is_array($tripStop) || $tripStop[0]['trip_id'] !== $assignmentData['trip_id']) {
                throw new Exception('Trip stop does not belong to the specified trip');
            }
        }

        return $this->post('parcel_list', $assignmentData);
    }

    public function createPayment($paymentData) {
        $this->validateCrossTenantReferences($paymentData, 'payments');
        return $this->post('payments', $paymentData);
    }

    public function createTrip($tripData) {
        if (!empty($tripData['vehicle_id'])) {
            $vehicle = $this->get('vehicle', "id=eq." . $tripData['vehicle_id'], 'company_id');
            if ($vehicle === false || empty($vehicle)) {
                throw new Exception('Vehicle does not exist in current tenant');
            }
        }

        if (!empty($tripData['outlet_manager_id'])) {
            $manager = $this->get('profiles', "id=eq." . $tripData['outlet_manager_id'], 'company_id');
            if ($manager === false || empty($manager)) {
                throw new Exception('Outlet manager does not exist in current tenant');
            }
        }

        return $this->post('trips', $tripData);
    }

    public function createTripStop($tripStopData) {
        if (!empty($tripStopData['trip_id'])) {
            $trip = $this->get('trips', "id=eq." . $tripStopData['trip_id'], 'company_id');
            if ($trip === null || $trip === false || !is_array($trip) || empty($trip)) {
                throw new Exception('Trip does not exist in current tenant');
            }
        }

        if (!empty($tripStopData['outlet_id'])) {
            $outlet = $this->get('outlets', "id=eq." . $tripStopData['outlet_id'], 'company_id');
            if (empty($outlet)) {
                throw new Exception('Outlet does not exist in current tenant');
            }
        }

        return $this->post('trip_stops', $tripStopData);
    }
}
