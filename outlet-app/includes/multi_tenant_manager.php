<?php
class MultiTenantManager {
    private $url;
    private $key;
    private $companyId;

    public function __construct($companyId = null) {
        $this->url = "https://xerpchdsykqafrsxbqef.supabase.co";
        $this->key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
        $this->companyId = $companyId ?? $_SESSION['company_id'] ?? null;

        if (!$this->companyId) {
            throw new Exception('Company ID is required for multi-tenant operations');
        }

        $this->setRLSContext();
    }

    private function setRLSContext() {
        $this->executeRLS("SET app.current_company = '" . $this->companyId . "'");
    }

    private function executeRLS($command) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json',
                    'Prefer: return=minimal'
                ],
                'content' => json_encode(['query' => $command])
            ]
        ]);

        file_get_contents($this->url . "/rest/v1/rpc/execute_sql", false, $context);
    }

    public function post($table, $data) {
        $data['company_id'] = $this->companyId;

        $this->validateTenantTable($table);

        $this->validateCrossTenantReferences($data, $table);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ],
                'content' => json_encode($data)
            ]
        ]);

        $response = file_get_contents($this->url . "/rest/v1/" . $table, false, $context);

        if ($response === false) {
            $error = error_get_last();
            error_log("Supabase POST failed for table: $table - " . $error['message']);
            throw new Exception("Failed to create record in $table");
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from database");
        }

        return $result;
    }

    public function get($table, $filters = '', $select = '*') {
        $this->validateTenantTable($table);

        $companyFilter = "company_id=eq." . $this->companyId;
        $finalFilters = $filters ? "$companyFilter&$filters" : $companyFilter;

        $url = $this->url . "/rest/v1/" . $table . "?select=" . urlencode($select) . "&" . $finalFilters;

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
            $error = error_get_last();
            error_log("Supabase GET failed for table: $table - " . $error['message']);
            return [];
        }

        $result = json_decode($response, true);
        return $result ?? [];
    }

    public function update($table, $data, $filters) {
        if (isset($data['company_id']) && $data['company_id'] !== $this->companyId) {
            throw new Exception('Cannot modify company_id in multi-tenant environment');
        }

        $companyFilter = "company_id=eq." . $this->companyId;
        $finalFilters = $filters ? "$companyFilter&$filters" : $companyFilter;

        $url = $this->url . "/rest/v1/" . $table . "?" . $finalFilters;

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

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            error_log("Supabase UPDATE failed for table: $table - " . $error['message']);
            throw new Exception("Failed to update record in $table");
        }

        return json_decode($response, true);
    }

    public function delete($table, $filters) {
        $companyFilter = "company_id=eq." . $this->companyId;
        $finalFilters = $filters ? "$companyFilter&$filters" : $companyFilter;

        $url = $this->url . "/rest/v1/" . $table . "?" . $finalFilters;

        $context = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'header' => [
                    'Authorization: Bearer ' . $this->key,
                    'apikey: ' . $this->key,
                    'Content-Type: application/json'
                ]
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        return $response !== false;
    }

    private function validateCrossTenantReferences($data, $table) {
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
                'parcel_id' => 'parcels',
                'outlet_id' => 'outlets'
            ],
            'delivery_events' => [
                'parcel_id' => 'parcels',
                'outlet_id' => 'outlets'
            ]
        ];

        if (!isset($validationRules[$table])) {
            return true;
        }

        foreach ($validationRules[$table] as $field => $refTable) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!$this->validateEntityBelongsToTenant($refTable, $data[$field])) {
                    throw new Exception("Referenced {$refTable} entity '{$data[$field]}' does not belong to current tenant");
                }
            }
        }

        return true;
    }

    private function validateEntityBelongsToTenant($table, $entityId) {
        $result = $this->get($table, "id=eq.$entityId", 'id,company_id');
        return !empty($result) && isset($result[0]) && $result[0]['company_id'] === $this->companyId;
    }

    private function validateTenantTable($table) {
        $tenantTables = [
            'parcels', 'parcel_list', 'trips', 'trip_stops', 'vehicle', 'payments',
            'delivery_events', 'outlets', 'notifications', 'customers', 'guest_customers',
            'billing_configs', 'customer_interactions', 'drivers', 'help'
        ];

        if (!in_array($table, $tenantTables)) {
            throw new Exception("Table '$table' is not configured for multi-tenancy");
        }
    }

    public function getCompanyId() {
        return $this->companyId;
    }

    public function createParcel($parcelData) {
        $existingParcel = $this->get('parcels', "track_number=eq." . urlencode($parcelData['track_number']), 'id');
        if (!empty($existingParcel)) {
            throw new Exception('Tracking number already exists for this company');
        }

        return $this->post('parcels', $parcelData);
    }

    public function createParcelListAssignment($assignmentData) {
        $validations = [
            'parcel_id' => 'parcels',
            'trip_id' => 'trips',
            'outlet_id' => 'outlets'
        ];

        foreach ($validations as $field => $table) {
            if (isset($assignmentData[$field])) {
                $entity = $this->get($table, "id=eq." . $assignmentData[$field], 'id,company_id');
                if (empty($entity)) {
                    throw new Exception("Referenced {$table} entity does not exist in current tenant");
                }
            }
        }

        if (!empty($assignmentData['trip_stop_id'])) {
            $tripStop = $this->get('trip_stops', "id=eq." . $assignmentData['trip_stop_id'], 'id,company_id,trip_id');
            if (empty($tripStop)) {
                throw new Exception('Trip stop does not exist in current tenant');
            }

            if (isset($assignmentData['trip_id']) && $tripStop[0]['trip_id'] !== $assignmentData['trip_id']) {
                throw new Exception('Trip stop does not belong to the specified trip');
            }
        }

        return $this->post('parcel_list', $assignmentData);
    }

    public function createPayment($paymentData) {
        return $this->post('payments', $paymentData);
    }

    public function getTrips($status = null, $limit = 50) {
        $filters = $status ? "status=eq.$status&limit=$limit" : "limit=$limit";
        return $this->get('trips', $filters, '*');
    }

    public function getParcels($status = null, $limit = 50) {
        $filters = $status ? "status=eq.$status&limit=$limit" : "limit=$limit";
        return $this->get('parcels', $filters, '*');
    }

    public function getOutlets() {
        return $this->get('outlets', '', 'id,outlet_name,address,contact_phone');
    }

    public function searchParcelByTrackingNumber($trackingNumber) {
        return $this->get('parcels', "track_number=ilike.*$trackingNumber*", '*');
    }

    public function getParcelDetails($parcelId) {
        $parcel = $this->get('parcels', "id=eq.$parcelId", '*');
        if (empty($parcel)) {
            return null;
        }

        $parcel = $parcel[0];

        $assignments = $this->get('parcel_list', "parcel_id=eq.$parcelId", '*');
        $parcel['assignments'] = $assignments;

        $payments = $this->get('payments', "parcel_id=eq.$parcelId", '*');
        $parcel['payments'] = $payments;

        $events = $this->get('delivery_events', "parcel_id=eq.$parcelId", '*');
        $parcel['events'] = $events;

        return $parcel;
    }

    public function updateParcelStatus($parcelId, $status, $notes = null) {
        $updateData = ['status' => $status];
        if ($notes) {
            $updateData['notes'] = $notes;
        }

        return $this->update('parcels', $updateData, "id=eq.$parcelId");
    }

    public function createDeliveryEvent($eventData) {
        return $this->post('delivery_events', $eventData);
    }

    public function getCompanyStats() {
        $stats = [];

        $parcels = $this->get('parcels', '', 'status');
        $stats['total_parcels'] = count($parcels);
        $stats['parcel_status_breakdown'] = array_count_values(array_column($parcels, 'status'));

        $activeTrips = $this->get('trips', 'status=neq.completed', 'id');
        $stats['active_trips'] = count($activeTrips);

        $payments = $this->get('payments', '', 'amount');
        $stats['total_revenue'] = array_sum(array_column($payments, 'amount'));

        return $stats;
    }
}
?>
