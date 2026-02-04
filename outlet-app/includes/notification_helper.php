<?php
class NotificationHelper {
    private $supabaseUrl;
    private $supabaseKey;
    
    public function __construct() {
        $this->supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
        $this->supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
    }
    
    public function createParcelCreatedNotification($parcelData, $recipientId = null) {
        $title = "New Parcel Created";
        $message = "Parcel {$parcelData['track_number']} has been created for delivery to {$parcelData['receiver_name']}";
        
        return $this->createNotification([
            'company_id' => $parcelData['company_id'],
            'outlet_id' => $parcelData['origin_outlet_id'],
            'recipient_id' => $recipientId ?: $parcelData['sender_id'],
            'sender_id' => $parcelData['sender_id'],
            'title' => $title,
            'message' => $message,
            'notification_type' => 'parcel_created',
            'parcel_id' => $parcelData['id'],
            'priority' => 'medium',
            'data' => json_encode([
                'parcel_track_number' => $parcelData['track_number'],
                'sender_name' => $parcelData['sender_name'],
                'receiver_name' => $parcelData['receiver_name'],
                'weight' => $parcelData['parcel_weight'] ?? null
            ])
        ]);
    }
    
    public function createParcelStatusNotification($parcelData, $oldStatus, $newStatus, $recipientId = null) {
        $title = "Parcel Status Updated";
        $message = "Parcel {$parcelData['track_number']} status changed from '{$oldStatus}' to '{$newStatus}'";
        
        $priority = 'medium';
        if (in_array($newStatus, ['dispatched', 'delivered'])) {
            $priority = 'high';
        }
        
        return $this->createNotification([
            'company_id' => $parcelData['company_id'],
            'outlet_id' => $parcelData['origin_outlet_id'],
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'parcel_status_change',
            'parcel_id' => $parcelData['id'],
            'priority' => $priority,
            'data' => json_encode([
                'parcel_track_number' => $parcelData['track_number'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'sender_name' => $parcelData['sender_name'],
                'receiver_name' => $parcelData['receiver_name']
            ])
        ]);
    }
    
    public function createDeliveryAssignedNotification($deliveryData, $driverData, $recipientId = null) {
        $title = "Delivery Assigned";
        $message = "Parcel {$deliveryData['tracking_number']} has been assigned to driver {$driverData['driver_name']}";
        
        return $this->createNotification([
            'company_id' => $deliveryData['company_id'],
            'outlet_id' => $deliveryData['outlet_id'],
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'delivery_assigned',
            'delivery_id' => $deliveryData['id'],
            'parcel_id' => $deliveryData['parcel_id'],
            'priority' => 'high',
            'data' => json_encode([
                'tracking_number' => $deliveryData['tracking_number'],
                'driver_name' => $driverData['driver_name'],
                'driver_phone' => $driverData['driver_number'],
                'estimated_delivery' => $deliveryData['estimated_delivery_date']
            ])
        ]);
    }
    
    public function createDeliveryCompletedNotification($deliveryData, $recipientId = null) {
        $title = "Delivery Completed";
        $message = "Parcel {$deliveryData['tracking_number']} has been successfully delivered";
        
        return $this->createNotification([
            'company_id' => $deliveryData['company_id'],
            'outlet_id' => $deliveryData['outlet_id'],
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'delivery_completed',
            'delivery_id' => $deliveryData['id'],
            'parcel_id' => $deliveryData['parcel_id'],
            'priority' => 'high',
            'data' => json_encode([
                'tracking_number' => $deliveryData['tracking_number'],
                'delivery_date' => $deliveryData['actual_delivery_date'],
                'delivery_address' => $deliveryData['delivery_address']
            ])
        ]);
    }
    
    public function createUrgentDeliveryNotification($parcelData, $reason, $recipientId = null) {
        $title = "Urgent Delivery Alert";
        $message = "URGENT: Parcel {$parcelData['track_number']} requires immediate attention. Reason: {$reason}";
        
        return $this->createNotification([
            'company_id' => $parcelData['company_id'],
            'outlet_id' => $parcelData['origin_outlet_id'],
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'urgent_delivery',
            'parcel_id' => $parcelData['id'],
            'priority' => 'urgent',
            'data' => json_encode([
                'parcel_track_number' => $parcelData['track_number'],
                'reason' => $reason,
                'sender_name' => $parcelData['sender_name'],
                'receiver_name' => $parcelData['receiver_name']
            ])
        ]);
    }
    
    public function createDriverUnavailableNotification($deliveryData, $driverData, $recipientId = null) {
        $title = "Driver Unavailable";
        $message = "Driver {$driverData['driver_name']} is unavailable for delivery {$deliveryData['tracking_number']}";
        
        return $this->createNotification([
            'company_id' => $deliveryData['company_id'],
            'outlet_id' => $deliveryData['outlet_id'],
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'driver_unavailable',
            'delivery_id' => $deliveryData['id'],
            'parcel_id' => $deliveryData['parcel_id'],
            'priority' => 'high',
            'data' => json_encode([
                'tracking_number' => $deliveryData['tracking_number'],
                'driver_name' => $driverData['driver_name'],
                'driver_id' => $driverData['id']
            ])
        ]);
    }
    
    public function createPaymentReceivedNotification($paymentData, $parcelData, $recipientId = null) {
        $title = "Payment Received";
        $message = "Payment of {$paymentData['amount']} received for parcel {$parcelData['track_number']}";
        
        return $this->createNotification([
            'company_id' => $parcelData['company_id'],
            'outlet_id' => $parcelData['origin_outlet_id'],
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'payment_received',
            'parcel_id' => $parcelData['id'],
            'priority' => 'medium',
            'data' => json_encode([
                'parcel_track_number' => $parcelData['track_number'],
                'amount' => $paymentData['amount'],
                'payment_method' => $paymentData['method'],
                'transaction_ref' => $paymentData['transaction_ref']
            ])
        ]);
    }
    
    public function createSystemAlertNotification($title, $message, $companyId, $outletId = null, $recipientId = null, $priority = 'medium') {
        return $this->createNotification([
            'company_id' => $companyId,
            'outlet_id' => $outletId,
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'system_alert',
            'priority' => $priority,
            'data' => json_encode(['alert_type' => 'system'])
        ]);
    }
    
    public function notifyOutletStaff($notificationData, $outletId, $companyId) {
        $staffUrl = "{$this->supabaseUrl}/rest/v1/profiles?outlet_id=eq.$outletId&company_id=eq.$companyId&role=in.(outlet_manager,staff)&select=id";
        $staffResponse = $this->makeSupabaseRequest($staffUrl, 'GET');
        
        if ($staffResponse['status_code'] === 200 && !empty($staffResponse['data'])) {
            foreach ($staffResponse['data'] as $staff) {
                $notificationData['recipient_id'] = $staff['id'];
                $this->createNotification($notificationData);
            }
        }
    }
    
    public function notifyCompanyAdmins($notificationData, $companyId) {
        $adminUrl = "{$this->supabaseUrl}/rest/v1/profiles?company_id=eq.$companyId&role=eq.admin&select=id";
        $adminResponse = $this->makeSupabaseRequest($adminUrl, 'GET');
        
        if ($adminResponse['status_code'] === 200 && !empty($adminResponse['data'])) {
            foreach ($adminResponse['data'] as $admin) {
                $notificationData['recipient_id'] = $admin['id'];
                $this->createNotification($notificationData);
            }
        }
    }
    
    private function createNotification($data) {
        $url = "{$this->supabaseUrl}/rest/v1/notifications";
        
        $notificationData = array_merge([
            'status' => 'unread',
            'created_at' => date('c'),
            'expires_at' => null
        ], $data);
        
        $response = $this->makeSupabaseRequest($url, 'POST', $notificationData);
        
        return [
            'success' => $response['status_code'] >= 200 && $response['status_code'] < 300,
            'data' => $response['data'],
            'status_code' => $response['status_code']
        ];
    }
    
    private function makeSupabaseRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: {$this->supabaseKey}",
                "Authorization: Bearer {$this->supabaseKey}",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ],
            CURLOPT_CUSTOMREQUEST => $method
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'data' => json_decode($response, true),
            'status_code' => $status_code
        ];
    }
}

function createNotification($type, $data, $recipientId = null) {
    $notificationHelper = new NotificationHelper();
    
    switch ($type) {
        case 'parcel_created':
            return $notificationHelper->createParcelCreatedNotification($data, $recipientId);
            
        case 'parcel_status_change':
            return $notificationHelper->createParcelStatusNotification(
                $data['parcel'], 
                $data['old_status'], 
                $data['new_status'], 
                $recipientId
            );
            
        case 'delivery_assigned':
            return $notificationHelper->createDeliveryAssignedNotification(
                $data['delivery'], 
                $data['driver'], 
                $recipientId
            );
            
        case 'delivery_completed':
            return $notificationHelper->createDeliveryCompletedNotification($data, $recipientId);
            
        case 'urgent_delivery':
            return $notificationHelper->createUrgentDeliveryNotification(
                $data['parcel'], 
                $data['reason'], 
                $recipientId
            );
            
        case 'driver_unavailable':
            return $notificationHelper->createDriverUnavailableNotification(
                $data['delivery'], 
                $data['driver'], 
                $recipientId
            );
            
        case 'payment_received':
            return $notificationHelper->createPaymentReceivedNotification(
                $data['payment'], 
                $data['parcel'], 
                $recipientId
            );
            
        case 'system_alert':
            return $notificationHelper->createSystemAlertNotification(
                $data['title'], 
                $data['message'], 
                $data['company_id'], 
                $data['outlet_id'] ?? null, 
                $recipientId, 
                $data['priority'] ?? 'medium'
            );
            
        default:
            return ['success' => false, 'error' => 'Invalid notification type'];
    }
}
