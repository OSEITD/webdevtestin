<?php
require_once __DIR__ . '/supabase.php';

class SecureParcelTracker {
    private $supabase;

    public function __construct() {
        $this->supabase = getSupabaseClient();
    }
    private function callSupabase($table, $method = 'GET', $params = [], $data = null) {
        $url = $this->supabase->getRestUrl() . '/' . $table;
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $headers = [
            'apikey: ' . $this->supabase->getApiKey(),
            'Authorization: Bearer ' . $this->supabase->getApiKey(),
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error $httpCode: " . $response);
        }
        return json_decode($response, true);
    }
    
    public function verifyAndTrackParcel($trackNumber, $phoneNumber, $nrc, $companySubdomain = null) {
        try {
            if (empty($trackNumber) || empty($phoneNumber) || empty($nrc)) {
                return $this->errorResponse('All fields are required');
            }
            $trackNumber = trim(strtoupper($trackNumber));
            $phoneNumber = $this->normalizePhoneNumber($phoneNumber);
            $nrc = trim(strtoupper($nrc));
            $parcel = $this->findParcel($trackNumber, $companySubdomain);
            if (!$parcel) {
                return $this->errorResponse('Parcel not found');
            }
            $customerRole = $this->verifyCustomerIdentity($parcel, $phoneNumber, $nrc);
            if (!$customerRole) {
                return $this->errorResponse('Customer verification failed');
            }
            return [
                'success' => true,
                'data' => $this->sanitizeParcelData($parcel, $customerRole),
                'customer_role' => $customerRole
            ];
        } catch (Exception $e) {
            error_log("Tracking error: " . $e->getMessage());
            return $this->errorResponse('System error occurred');
        }
    }

    private function findParcel($trackNumber, $companySubdomain = null) {
        try {
            $parcel = $this->getParcelWithDetails($trackNumber);
            if ($parcel) {
                return $parcel;
            }
            return null;
        } catch (Exception $e) {
            error_log("Error finding parcel: " . $e->getMessage());
            return null;
        }
    }
    
    
    private function getParcelWithDetails($trackNumber) {
        try {
           
            $params = [
                'track_number' => 'eq.' . $trackNumber,
                'select' => '*'
            ];
            $response = $this->callSupabase('parcels', 'GET', $params);
            $parcel = $response[0] ?? null;
            if (!$parcel) {
                return null;
            }
            $parcel['delivery_events'] = $this->getDeliveryEvents($parcel['id']);
            $parcel['payment_info'] = $this->getPaymentInfo($parcel['id']);
            $parcel['origin_outlet_details'] = $this->getOutletDetails($parcel['origin_outlet_id']);
            $parcel['destination_outlet_details'] = $this->getOutletDetails($parcel['destination_outlet_id']);
            $parcel['driver_details'] = $this->getDriverDetails($parcel['driver_id']);
            $parcel['global_sender_details'] = $this->getGlobalCustomer($parcel['global_sender_id']);
            $parcel['global_receiver_details'] = $this->getGlobalCustomer($parcel['global_receiver_id']);
            $parcel['company_details'] = $this->getCompanyDetails($parcel['company_id']);
            return $parcel;
        } catch (Exception $e) {
            error_log("Error getting parcel details: " . $e->getMessage());
            return null;
        }
    }
    
    private function verifyCustomerIdentity($parcel, $phoneNumber, $nrc) {
        // For sender verification
        $senderPhone = $parcel['sender_phone'] ?? null;
        $senderNrc = $parcel['nrc'] ?? $parcel['sender_nrc'] ?? null; // Database has single 'nrc' field
        if ($senderPhone && $senderNrc && $this->matchCustomerData($senderPhone, $senderNrc, $phoneNumber, $nrc)) {
            return 'sender';
        }
        // For receiver verification - check if there's a separate receiver_nrc or use global_receiver_id
        $receiverPhone = $parcel['receiver_phone'] ?? null;
        $receiverNrc = $parcel['receiver_nrc'] ?? null;
        if ($receiverPhone && $receiverNrc && $this->matchCustomerData($receiverPhone, $receiverNrc, $phoneNumber, $nrc)) {
            return 'receiver';
        }
        // Check global_receiver_id for additional verification
        if (!empty($parcel['global_receiver_id'])) {
            $globalReceiver = $this->getGlobalCustomer($parcel['global_receiver_id']);
            if ($globalReceiver && $this->matchCustomerData($globalReceiver['phone'], $globalReceiver['nrc'], $phoneNumber, $nrc)) {
                return 'receiver';
            }
        }
        // Check global_sender_id for additional verification
        if (!empty($parcel['global_sender_id'])) {
            $globalSender = $this->getGlobalCustomer($parcel['global_sender_id']);
            if ($globalSender && $this->matchCustomerData($globalSender['phone'], $globalSender['nrc'], $phoneNumber, $nrc)) {
                return 'sender';
            }
        }
        return null;
    }
    
    private function getGlobalCustomer($globalCustomerId) {
        try {
            if (empty($globalCustomerId)) {
                return null;
            }
            $params = [
                'id' => 'eq.' . $globalCustomerId,
                'select' => '*'
            ];
            $response = $this->callSupabase('global_customers', 'GET', $params);
            return $response[0] ?? null;
        } catch (Exception $e) {
            error_log("Error getting global customer: " . $e->getMessage());
            return null;
        }
    }
    
    private function getDeliveryEvents($parcelId) {
        try {
            if (empty($parcelId)) {
                return [];
            }
            
            $params = [
                'shipment_id' => 'eq.' . $parcelId,
                'select' => '*',
                'order' => 'event_timestamp.desc'
            ];
            
            $response = $this->callSupabase('delivery_events', 'GET', $params);
            
            return $response ?? [];
            
        } catch (Exception $e) {
            error_log("Error getting delivery events: " . $e->getMessage());
            return [];
        }
    }
    
    private function getPaymentInfo($parcelId) {
        try {
            if (empty($parcelId)) {
                return null;
            }
            
            $params = [
                'parcel_id' => 'eq.' . $parcelId,
                'select' => '*',
                'order' => 'created_at.desc'
            ];
            
            $response = $this->callSupabase('payments', 'GET', $params);
            
            return $response[0] ?? null;
            
        } catch (Exception $e) {
            error_log("Error getting payment info: " . $e->getMessage());
            return null;
        }
    }
    
    private function getOutletDetails($outletId) {
        try {
            if (empty($outletId)) {
                return null;
            }
            
            $params = [
                'id' => 'eq.' . $outletId,
                'select' => '*'
            ];
            
            try {
                $response = $this->callSupabase('outlets', 'GET', $params);
            } catch (Exception $e) {
                error_log("Error calling Supabase for outlet details: " . $e->getMessage());
                $response = [];
            }
            
            return $response[0] ?? null;
            
        } catch (Exception $e) {
            error_log("Error getting outlet details: " . $e->getMessage());
            return null;
        }
    }
    
   
    private function getDriverDetails($driverId) {
        try {
            if (empty($driverId)) {
                return null;
            }
            
            $params = [
                'id' => 'eq.' . $driverId,
                'select' => '*'
            ];
            
            $response = $this->callSupabase('drivers', 'GET', $params);
            $driverData = $response[0] ?? null;
            
            if ($driverData) {
                // Checking for recent GPS location to determine if GPS tracking is available
                $driverData['gps_available'] = $this->checkGPSAvailability($driverId);
            }
            
            return $driverData;
            
        } catch (Exception $e) {
            error_log("Error getting driver details: " . $e->getMessage());
            return null;
        }
    }
    
    private function checkGPSAvailability($driverId) {
        try {
            if (empty($driverId)) {
                return false;
            }
            
        
            $params = [
                'driver_id' => 'eq.' . $driverId,
                'timestamp' => 'gte.' . date('c', strtotime('-24 hours')),
                'select' => 'id',
                'limit' => '1'
            ];
            
            $response = $this->callSupabase('driver_locations', 'GET', $params);
            
            
            if (empty($response)) {
                // Checking if driver exists in drivers table
                $driverParams = [
                    'id' => 'eq.' . $driverId,
                    'select' => 'id',
                    'limit' => '1'
                ];
                $driverResponse = $this->callSupabase('drivers', 'GET', $driverParams);
                return !empty($driverResponse); // Return true if driver exists, even without GPS data
            }
            
            return !empty($response);
            
        } catch (Exception $e) {
            error_log("Error checking GPS availability: " . $e->getMessage());
            
            return true;
        }
    }
    
    private function getCompanyDetails($companyId) {
        try {
            if (empty($companyId)) {
                return null;
            }
            
            $params = [
                'id' => 'eq.' . $companyId,
                'select' => '*'
            ];
            
            $response = $this->callSupabase('companies', 'GET', $params);
            
            return $response[0] ?? null;
            
        } catch (Exception $e) {
            error_log("Error getting company details: " . $e->getMessage());
            return null;
        }
    }
    
    private function matchCustomerData($storedPhone, $storedNrc, $inputPhone, $inputNrc) {
        return $this->normalizePhoneNumber($storedPhone) === $this->normalizePhoneNumber($inputPhone) &&
               $this->normalizeNrc($storedNrc) === $this->normalizeNrc($inputNrc);
    }
    
    private function normalizePhoneNumber($phone) {
        if (is_numeric($phone)) {
            $phone = (string)$phone;
        }
        
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 9 && substr($phone, 0, 1) === '9') {
            $phone = '260' . $phone; // Add country code
        } elseif (strlen($phone) === 10 && substr($phone, 0, 2) === '09') {
            $phone = '260' . substr($phone, 1); // Replace 0 with 260
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '260') {
            // Already has country code
        } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '9') {
            $phone = '260' . $phone; // Add country code to 10-digit starting with 9
        }
        
        return $phone;
    }
    
    private function normalizeNrc($nrc) {
        return strtoupper(trim(preg_replace('/[^a-zA-Z0-9]/', '', $nrc)));
    }
    
    private function sanitizeParcelData($parcel, $customerRole) {
        $baseData = [
            'id' => $parcel['id'],
            'track_number' => $parcel['track_number'],
            'status' => $parcel['status'],
            'created_at' => $parcel['created_at'],
            'updated_at' => $parcel['updated_at'],
            'delivery_date' => $parcel['delivery_date'] ?? null,
            'estimated_delivery_date' => $parcel['estimated_delivery_date'] ?? null,
            'delivered_at' => $parcel['delivered_at'] ?? null
        ];
        
        // Role-specific information
        if ($customerRole === 'sender') {
            $baseData['receiver_name'] = $parcel['receiver_name'];
            $baseData['receiver_address'] = $parcel['receiver_address'];
            $baseData['receiver_phone'] = $parcel['receiver_phone'];
            $baseData['your_role'] = 'Sender';
        } elseif ($customerRole === 'receiver') {
            $baseData['sender_name'] = $parcel['sender_name'];
            $baseData['sender_address'] = $parcel['sender_address'];
            $baseData['sender_phone'] = $parcel['sender_phone'];
            $baseData['sender_email'] = $parcel['sender_email'];
            $baseData['your_role'] = 'Receiver';
        }
        
        // Parcel physical details
        $baseData['parcel_weight'] = $parcel['parcel_weight'] ?? $parcel['weight'] ?? null;
        $baseData['parcel_length'] = $parcel['parcel_length'] ?? null;
        $baseData['parcel_width'] = $parcel['parcel_width'] ?? null;
        $baseData['parcel_height'] = $parcel['parcel_height'] ?? null;
        $baseData['package_details'] = $parcel['package_details'] ?? null;
        $baseData['service_type'] = $parcel['service_type'] ?? $parcel['delivery_option'] ?? null;
        $baseData['special_instructions'] = $parcel['special_instructions'] ?? null;
        
        // Financial information
        $baseData['parcel_value'] = $parcel['parcel_value'] ?? $parcel['declared_value'] ?? null;
        $baseData['delivery_fee'] = $parcel['delivery_fee'] ?? null;
        $baseData['insurance_amount'] = $parcel['insurance_amount'] ?? null;
        $baseData['cod_amount'] = $parcel['cod_amount'] ?? null;
        $baseData['payment_status'] = $parcel['payment_status'] ?? null;
        
        // Images
        $baseData['photo_urls'] = $parcel['photo_urls'] ?? [];
        $baseData['barcode_url'] = $parcel['barcode_url'] ?? null;
        
        // Delivery events timeline
        $baseData['delivery_events'] = $parcel['delivery_events'] ?? [];
        
        // Payment information
        $baseData['payment_info'] = $parcel['payment_info'] ?? null;
        
        // Outlet information
        $baseData['origin_outlet'] = $parcel['origin_outlet_details'] ?? null;
        $baseData['destination_outlet'] = $parcel['destination_outlet_details'] ?? null;
        
        // Driver information (limited for security)
        if ($parcel['driver_details']) {
            $baseData['driver_info'] = [
                'driver_name' => $parcel['driver_details']['driver_name'] ?? $parcel['driver_details']['name'] ?? null,
                'driver_phone' => $parcel['driver_details']['driver_phone'] ?? $parcel['driver_details']['phone'] ?? null,
                'current_location' => $parcel['driver_details']['current_location'] ?? null,
                'status' => $parcel['driver_details']['status'] ?? null,
                'gps_available' => $parcel['driver_details']['gps_available'] ?? false
            ];
        }
        
        // Company information
        if ($parcel['company_details']) {
            $baseData['company_info'] = [
                'company_name' => $parcel['company_details']['company_name'] ?? null,
                'contact_email' => $parcel['company_details']['contact_email'] ?? null,
                'contact_phone' => $parcel['company_details']['contact_phone'] ?? null,
                'address' => $parcel['company_details']['address'] ?? null
            ];
        }
        
        // Always include global customer details for proper name display
        if ($parcel['global_sender_details']) {
            $baseData['global_sender_details'] = [
                'full_name' => $parcel['global_sender_details']['full_name'] ?? null,
                'phone' => $parcel['global_sender_details']['phone'] ?? null,
                'email' => $parcel['global_sender_details']['email'] ?? null,
                'address' => $parcel['global_sender_details']['address'] ?? null
            ];
        }
        
        if ($parcel['global_receiver_details']) {
            $baseData['global_receiver_details'] = [
                'full_name' => $parcel['global_receiver_details']['full_name'] ?? null,
                'phone' => $parcel['global_receiver_details']['phone'] ?? null,
                'email' => $parcel['global_receiver_details']['email'] ?? null,
                'address' => $parcel['global_receiver_details']['address'] ?? null
            ];
        }
        
        // Role-specific customer details (backward compatibility)
        if ($customerRole === 'sender' && $parcel['global_receiver_details']) {
            $baseData['receiver_details'] = [
                'full_name' => $parcel['global_receiver_details']['full_name'],
                'phone' => $parcel['global_receiver_details']['phone'],
                'email' => $parcel['global_receiver_details']['email'],
                'address' => $parcel['global_receiver_details']['address']
            ];
        } elseif ($customerRole === 'receiver' && $parcel['global_sender_details']) {
            $baseData['sender_details'] = [
                'full_name' => $parcel['global_sender_details']['full_name'],
                'phone' => $parcel['global_sender_details']['phone'],
                'email' => $parcel['global_sender_details']['email'],
                'address' => $parcel['global_sender_details']['address']
            ];
        }
        
        return $baseData;
    }
    
    private function errorResponse($message) {
        return [
            'success' => false,
            'error' => $message
        ];
    }
}
