<?php
class EnhancedParcelDeliveryManager {
    private $supabase;
    private $debug;

    public function __construct($debug = false) {
        require_once __DIR__ . '/supabase-helper.php';
        $this->supabase = new SupabaseHelper();
        $this->debug = $debug;
    }

    public function createParcelWithDelivery($parcelData) {
        try {
            $parcelResult = $this->supabase->post('parcels', $parcelData);
            if (empty($parcelResult) || !isset($parcelResult[0]['id'])) {
                return [
                    'success' => false,
                    'message' => 'Failed to create parcel record',
                    'parcel' => null,
                    'delivery' => null
                ];
            }
            $parcel = $parcelResult[0];

            $deliveryData = [
                'shipment_id' => $parcel['id'], 
                'company_id' => $parcelData['company_id'],
                'status' => 'pending',
                'event_timestamp' => date('c'),
                'updated_by' => $parcelData['created_by'] ?? null
            ];
            $deliveryResult = $this->supabase->post('delivery_events', $deliveryData); 
            if (empty($deliveryResult) || !isset($deliveryResult[0]['id'])) {
                return [
                    'success' => false,
                    'message' => 'Failed to create delivery event record',
                    'parcel' => $parcel,
                    'delivery' => null
                ];
            }
            $delivery = $deliveryResult[0];

            return [
                'success' => true,
                'parcel' => $parcel,
                'delivery' => $delivery
            ];
        } catch (Exception $e) {
            if ($this->debug) error_log('EnhancedParcelDeliveryManager error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'parcel' => null,
                'delivery' => null
            ];
        }
    }
}
