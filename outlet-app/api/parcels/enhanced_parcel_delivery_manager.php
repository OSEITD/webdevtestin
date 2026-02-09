<?php

class EnhancedParcelDeliveryManager {
    private $supabaseUrl;
    private $supabaseKey;
    private $debugMode;
    
    public function __construct($debugMode = false) {
        $this->supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
        $this->supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
        $this->debugMode = $debugMode;
    }
    
    
    public function createParcelWithDelivery($parcelData) {
        
        $requiredFields = ['track_number', 'sender_name', 'receiver_name', 'receiver_address', 'company_id', 'origin_outlet_id'];
        
        foreach ($requiredFields as $field) {
            if (empty($parcelData[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        try {
            
            $parcelPayload = $this->prepareParcelPayload($parcelData);
            $parcelResult = $this->makeApiCall('POST', '/rest/v1/parcels', $parcelPayload);
            error_log('[EnhancedParcelDeliveryManager] Parcel API response: ' . json_encode($parcelResult));
            if (empty($parcelResult) || !isset($parcelResult[0]['id'])) {
                throw new Exception("Failed to create parcel record");
            }
            $parcelId = $parcelResult[0]['id'];
            $this->log("Parcel created successfully with ID: " . $parcelId);
            
            $deliveryPayload = $this->prepareDeliveryEventPayload($parcelData, $parcelId);
            $deliveryResult = $this->makeApiCall('POST', '/rest/v1/delivery_events', $deliveryPayload);
            error_log('[EnhancedParcelDeliveryManager] Delivery Event API response: ' . json_encode($deliveryResult));
            if (empty($deliveryResult) || !isset($deliveryResult[0]['id'])) {
                
                
                $this->cleanupFailedParcel($parcelId);
                throw new Exception("Failed to create delivery event record, parcel creation rolled back");
            }
            $deliveryEventId = $deliveryResult[0]['id'];
            $this->log("Delivery event created successfully with ID: " . $deliveryEventId);
            
            $this->createCustomerInteraction($parcelData, $parcelId);
            return [
                'success' => true,
                'parcel' => $parcelResult[0],
                'delivery_event' => $deliveryResult[0],
                'tracking_number' => $parcelData['track_number'],
                'message' => 'Parcel and delivery event records created successfully'
            ];
            
        } catch (Exception $e) {
            $this->log("Error in createParcelWithDelivery: " . $e->getMessage());
            throw $e;
        }
    }
    
    
    private function prepareParcelPayload($data) {
        $estimatedDelivery = $this->calculateEstimatedDelivery($data['delivery_option'] ?? 'standard');
        
        return [
            'track_number' => $data['track_number'],
            'status' => 'pending', 
            'company_id' => $data['company_id'],
            'origin_outlet_id' => $data['origin_outlet_id'],
            'destination_outlet_id' => $data['destination_outlet_id'] ?? null,
            'sender_name' => $data['sender_name'],
            'sender_email' => $data['sender_email'] ?? null,
            'sender_phone' => $data['sender_phone'] ?? null,
            'sender_address' => $data['sender_address'] ?? null,
            'receiver_name' => $data['receiver_name'],
            'receiver_address' => $data['receiver_address'],
            'receiver_phone' => $data['receiver_phone'] ?? null,
            'package_details' => $data['package_details'] ?? '',
            'parcel_weight' => $data['parcel_weight'] ?? 0,
            'delivery_option' => $data['delivery_option'] ?? 'standard',
            'delivery_fee' => $data['delivery_fee'] ?? 0,
            'declared_value' => $data['declared_value'] ?? 0,
            'parcel_value' => $data['parcel_value'] ?? $data['declared_value'] ?? 0,
            'insurance_amount' => $data['insurance_amount'] ?? 0,
            'cod_amount' => $data['cod_amount'] ?? 0,
            'special_instructions' => $data['special_instructions'] ?? null,
            'payment_status' => $data['payment_status'] ?? 'pending',
            'parcel_length' => $data['parcel_length'] ?? null,
            'parcel_width' => $data['parcel_width'] ?? null,
            'parcel_height' => $data['parcel_height'] ?? null,
            'estimated_delivery_date' => $estimatedDelivery,
            'photo_urls' => $data['photo_urls'] ?? null,
            'global_sender_id' => $data['global_sender_id'] ?? null,
            'global_receiver_id' => $data['global_receiver_id'] ?? null
        ];
    }
    
    
    private function prepareDeliveryEventPayload($data, $parcelId) {
        
        return [
            
            'shipment_id' => $parcelId, 
            'status' => 'pending',
            'event_timestamp' => date('c'),
            'updated_by' => $_SESSION['user_id'] ?? null,
            'company_id' => $data['company_id']
        ];
    }
    
    
    private function calculateEstimatedDelivery($deliveryOption) {
        $daysToAdd = 1; 
        
        switch (strtolower($deliveryOption)) {
            case 'sameday':
                $daysToAdd = 0;
                break;
            case 'express':
                $daysToAdd = 1;
                break;
            case 'standard':
            default:
                $daysToAdd = 2;
                break;
        }
        
        return date('Y-m-d', strtotime("+$daysToAdd days"));
    }
    
    
    private function determineRecipientType($data) {
        if (!empty($data['global_receiver_id'])) {
            return 'registered';
        } elseif (!empty($data['business_recipient_id'])) {
            return 'business';
        } else {
            return 'guest';
        }
    }
    
    
    private function createCustomerInteraction($data, $parcelId) {
        try {
            $interactionPayload = [
                'company_id' => $data['company_id'],
                'customer_id' => $data['global_sender_id'],
                'customer_type' => $this->determineRecipientType($data),
                'interaction_type' => 'parcel_created',
                'channel' => 'web',
                'description' => 'Parcel created via outlet application',
                'parcel_id' => $parcelId,
                'staff_id' => $_SESSION['user_id'] ?? null
            ];
            
            $this->makeApiCall('POST', '/rest/v1/customer_interactions', $interactionPayload);
            $this->log("Customer interaction recorded successfully");
            
        } catch (Exception $e) {
            
            $this->log("Failed to create customer interaction: " . $e->getMessage());
        }
    }
    
    
    private function cleanupFailedParcel($parcelId) {
        try {
            $this->makeApiCall('DELETE', "/rest/v1/parcels?id=eq.$parcelId");
            $this->log("Cleaned up failed parcel record: " . $parcelId);
        } catch (Exception $e) {
            $this->log("Failed to cleanup parcel record: " . $e->getMessage());
        }
    }
    
    
    public function assignParcelToDriver($parcelId, $driverId, $assignmentData = []) {
        try {
            $currentDateTime = date('c'); 
            
            
            $parcelUpdate = [
                'status' => 'Out for Delivery',
                'driver_id' => $driverId
            ];
            
            $this->makeApiCall('PATCH', "/rest/v1/parcels?id=eq.$parcelId", $parcelUpdate);
            
            
            $deliveryUpdate = [
                'delivery_status' => 'out_for_delivery',
                'driver_id' => $driverId,
                'pickup_date' => $currentDateTime,
                'delivery_notes' => isset($assignmentData['notes']) 
                    ? ($assignmentData['notes'] . "\n" . ($this->getDeliveryNotes($parcelId) ?? ''))
                    : $this->getDeliveryNotes($parcelId)
            ];
            
            $this->makeApiCall('PATCH', "/rest/v1/deliveries?parcel_id=eq.$parcelId", $deliveryUpdate);
            
            $this->log("Parcel $parcelId assigned to driver $driverId successfully");
            
            return [
                'success' => true,
                'message' => 'Parcel assigned to driver successfully',
                'parcel_id' => $parcelId,
                'driver_id' => $driverId
            ];
            
        } catch (Exception $e) {
            $this->log("Error assigning parcel to driver: " . $e->getMessage());
            throw $e;
        }
    }
    
    
    public function markParcelAsDelivered($parcelId, $deliveryProof = []) {
        try {
            $currentDateTime = date('c');
            $currentDate = date('Y-m-d');
            
            
            $parcelUpdate = [
                'status' => 'delivered',
                'delivery_date' => $currentDate
            ];
            
            $this->makeApiCall('PATCH', "/rest/v1/parcels?id=eq.$parcelId", $parcelUpdate);
            
            
            $deliveryUpdate = [
                'delivery_status' => 'delivered',
                'delivery_date' => $currentDateTime,
                'actual_delivery_date' => $currentDateTime,
                'signature_proof' => $deliveryProof['signature'] ?? null,
                'photo_proof' => $deliveryProof['photo'] ?? null,
                'delivery_notes' => isset($deliveryProof['notes']) 
                    ? ($this->getDeliveryNotes($parcelId) . "\nDelivery: " . $deliveryProof['notes'])
                    : $this->getDeliveryNotes($parcelId)
            ];
            
            $this->makeApiCall('PATCH', "/rest/v1/deliveries?parcel_id=eq.$parcelId", $deliveryUpdate);
            
            // Update COD payment status to 'successful' if payment method is COD
            try {
                require_once __DIR__ . '/../../../sql/PaymentTransactionDB.php';
                $paymentDB = new PaymentTransactionDB();
                $codPaymentResult = $paymentDB->markCODPaymentAsCollected($parcelId);
                
                if ($codPaymentResult['success']) {
                    $this->log("COD payment for parcel $parcelId marked as collected");
                } else {
                    // Not an error if there's no COD payment - could be cash or online payment
                    $this->log("No COD payment to update for parcel $parcelId: " . ($codPaymentResult['error'] ?? 'N/A'));
                }
            } catch (Exception $e) {
                // Don't fail delivery if payment update fails
                $this->log("Warning: Failed to update COD payment status: " . $e->getMessage());
            }
            
            $this->log("Parcel $parcelId marked as delivered successfully");
            
            return [
                'success' => true,
                'message' => 'Parcel marked as delivered successfully',
                'parcel_id' => $parcelId,
                'delivery_date' => $currentDateTime
            ];
            
        } catch (Exception $e) {
            $this->log("Error marking parcel as delivered: " . $e->getMessage());
            throw $e;
        }
    }
    
    
    private function getDeliveryNotes($parcelId) {
        try {
            $response = $this->makeApiCall('GET', "/rest/v1/deliveries?parcel_id=eq.$parcelId&select=delivery_notes");
            return $response[0]['delivery_notes'] ?? '';
        } catch (Exception $e) {
            return '';
        }
    }
    
    
    public function getParcelWithDeliveryInfo($parcelId) {
        try {
            
            $parcelResponse = $this->makeApiCall('GET', "/rest/v1/parcels?id=eq.$parcelId&select=*");
            $parcel = $parcelResponse[0] ?? null;
            
            if (!$parcel) {
                throw new Exception("Parcel not found");
            }
            
            
            $deliveryResponse = $this->makeApiCall('GET', "/rest/v1/deliveries?parcel_id=eq.$parcelId&select=*");
            $delivery = $deliveryResponse[0] ?? null;
            
            return [
                'parcel' => $parcel,
                'delivery' => $delivery,
                'has_delivery_record' => !empty($delivery)
            ];
            
        } catch (Exception $e) {
            $this->log("Error getting parcel with delivery info: " . $e->getMessage());
            throw $e;
        }
    }
    
    
    private function makeApiCall($method, $endpoint, $data = null) {
        $url = $this->supabaseUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                "apikey: {$this->supabaseKey}",
                "Authorization: Bearer {$this->supabaseKey}",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true) ?: [];
        } else {
            $errorDetails = json_decode($response, true);
            $errorMessage = $errorDetails['message'] ?? $response;
            throw new Exception("API call failed: HTTP $httpCode - $errorMessage");
        }
    }
    
    
    private function log($message) {
        if ($this->debugMode) {
            error_log("[EnhancedParcelDeliveryManager] " . $message);
        }
    }
    
    
    public function getDashboardMetrics($companyId, $outletId) {
        
        if (empty($companyId) || empty($outletId)) {
            throw new Exception("Missing required companyId or outletId parameters");
        }
        
        
        $encodedCompanyId = urlencode($companyId);
        $encodedOutletId = urlencode($outletId);
        
        $today = date('Y-m-d');
        $todayStart = $today . 'T00:00:00Z';
        $todayEnd = $today . 'T23:59:59Z';
        
        try {
            
            
            $parcelsDeliveredToday = $this->makeApiCall('GET', 
                "/rest/v1/parcels?origin_outlet_id=eq.$encodedOutletId&created_at=gte." . urlencode($todayStart) . "&created_at=lte." . urlencode($todayEnd) . "&status=eq.delivered&select=id,track_number,delivery_fee,status");
            
            $parcelsAtOutletToday = $this->makeApiCall('GET', 
                "/rest/v1/parcels?origin_outlet_id=eq.$encodedOutletId&created_at=gte." . urlencode($todayStart) . "&created_at=lte." . urlencode($todayEnd) . "&status=eq." . urlencode('At Outlet') . "&select=id,track_number,delivery_fee,status");
            
            
            $deliveriesCompletedToday = $this->makeApiCall('GET', 
                "/rest/v1/deliveries?origin_outlet_id=eq.$encodedOutletId&created_at=gte." . urlencode($todayStart) . "&created_at=lte." . urlencode($todayEnd) . "&delivery_status=eq.delivered&select=id,delivery_fee");
            
            $deliveriesReadyToday = $this->makeApiCall('GET', 
                "/rest/v1/deliveries?origin_outlet_id=eq.$encodedOutletId&created_at=gte." . urlencode($todayStart) . "&created_at=lte." . urlencode($todayEnd) . "&delivery_status=eq.ready&select=id,delivery_fee");
            
            
            $allReceivedToday = array_merge($parcelsDeliveredToday, $parcelsAtOutletToday);
            $totalReceivedCount = count($allReceivedToday) + count($deliveriesCompletedToday) + count($deliveriesReadyToday);
            
            
            $receivedRevenue = array_sum(array_column($parcelsDeliveredToday, 'delivery_fee')) +
                              array_sum(array_column($parcelsAtOutletToday, 'delivery_fee')) +
                              array_sum(array_column($deliveriesCompletedToday, 'delivery_fee')) +
                              array_sum(array_column($deliveriesReadyToday, 'delivery_fee'));
            
            
            $parcelsPendingParcelsTable = $this->makeApiCall('GET',
                "/rest/v1/parcels?origin_outlet_id=eq.$encodedOutletId&status=eq.pending&select=id,track_number");
            
            $parcelsAtOutletParcelsTable = $this->makeApiCall('GET',
                "/rest/v1/parcels?origin_outlet_id=eq.$encodedOutletId&status=eq." . urlencode('At Outlet') . "&select=id,track_number");
            
            $deliveriesPendingTable = $this->makeApiCall('GET',
                "/rest/v1/deliveries?origin_outlet_id=eq.$encodedOutletId&delivery_status=eq.pending&select=id,tracking_number,parcel_id");
            
            $deliveriesScheduledTable = $this->makeApiCall('GET',
                "/rest/v1/deliveries?origin_outlet_id=eq.$encodedOutletId&delivery_status=eq.scheduled&select=id,tracking_number,parcel_id");
            
            
            $allPendingParcels = array_merge($parcelsPendingParcelsTable, $parcelsAtOutletParcelsTable);
            $totalPendingCount = count($allPendingParcels) + count($deliveriesPendingTable) + count($deliveriesScheduledTable);
            
            
            $parcelsDispatchedParcelsTable = $this->makeApiCall('GET',
                "/rest/v1/parcels?origin_outlet_id=eq.$encodedOutletId&status=eq." . urlencode('Out for Delivery') . "&select=id,track_number");
            
            $deliveriesOutForDeliveryTable = $this->makeApiCall('GET',
                "/rest/v1/deliveries?origin_outlet_id=eq.$encodedOutletId&delivery_status=eq.out_for_delivery&select=id,tracking_number,parcel_id");
            
            $deliveriesInTransitTable = $this->makeApiCall('GET',
                "/rest/v1/deliveries?origin_outlet_id=eq.$encodedOutletId&delivery_status=eq.in_transit&select=id,tracking_number,parcel_id");
            
            
            $allDispatchedParcels = $parcelsDispatchedParcelsTable;
            $totalDispatchedCount = count($allDispatchedParcels) + count($deliveriesOutForDeliveryTable) + count($deliveriesInTransitTable);
            
            
            $activeDeliveries = $this->makeApiCall('GET',
                "/rest/v1/deliveries?origin_outlet_id=eq.$encodedOutletId&delivery_status=in.(out_for_delivery,in_transit)&select=id,tracking_number");
            
            $completedDeliveries = $this->makeApiCall('GET',
                "/rest/v1/deliveries?origin_outlet_id=eq.$encodedOutletId&delivery_status=eq.delivered&actual_delivery_date=gte." . urlencode($todayStart) . "&actual_delivery_date=lte." . urlencode($todayEnd) . "&select=id,delivery_fee");
            
            return [
                'parcels' => [
                    'created_today' => $totalReceivedCount, 
                    'pending' => $totalPendingCount, 
                    'in_transit' => $totalDispatchedCount, 
                    'delivered_today' => count($parcelsDeliveredToday),
                    'at_outlet_today' => count($parcelsAtOutletToday),
                    'revenue_today' => $receivedRevenue 
                ],
                'deliveries' => [
                    'active_deliveries' => count($activeDeliveries),
                    'completed_today' => count($completedDeliveries),
                    'completion_revenue' => array_sum(array_column($completedDeliveries, 'delivery_fee')),
                    'delivered_via_deliveries_table' => count($deliveriesCompletedToday),
                    'ready_via_deliveries_table' => count($deliveriesReadyToday)
                ],
                'summary' => [
                    'total_parcels_today' => $totalReceivedCount, 
                    'total_revenue_today' => $receivedRevenue, 
                    'completion_rate' => $totalReceivedCount > 0 ? round((count($parcelsDeliveredToday) / $totalReceivedCount) * 100, 2) : 0 
                ]
            ];
            
        } catch (Exception $e) {
            $this->log("Error getting dashboard metrics: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
