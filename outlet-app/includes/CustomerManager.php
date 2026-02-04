<?php
class CustomerManager {
    private $supabase;

    public function __construct($supabase) {
        $this->supabase = $supabase;
    }

    public function findOrCreateCustomer($companyId, $name, $phone, $email, $address, $customerType = 'guest') {
        $existingCustomer = $this->searchExistingCustomer($companyId, $phone, $email);
        if ($existingCustomer) {
            return $existingCustomer;
        }

        switch ($customerType) {
            case 'guest':
                return $this->createGuestCustomer($companyId, $name, $phone, $email, $address);
            case 'business':
                return $this->createBusinessCustomer($companyId, $name, $phone, $email, $address);
            case 'registered':
                throw new Exception('Registered customers must sign up through the customer portal first');
            default:
                return $this->createGuestCustomer($companyId, $name, $phone, $email, $address);
        }
    }

    private function searchExistingCustomer($companyId, $phone, $email) {
        $filters = ["company_id=eq.$companyId"];

        if (!empty($phone)) {
            $filters[] = "phone=eq." . urlencode($phone);
        }
        if (!empty($email)) {
            $filters[] = "email=ilike." . urlencode(strtolower($email));
        }

        $query = implode('&', $filters);

        $customers = $this->supabase->get('customers', $query);
        if (!empty($customers)) {
            return [
                'id' => $customers[0]['id'],
                'type' => 'registered',
                'data' => $customers[0]
            ];
        }

        $guestCustomers = $this->supabase->get('guest_customers', $query);
        if (!empty($guestCustomers)) {
            return [
                'id' => $guestCustomers[0]['id'],
                'type' => 'guest',
                'data' => $guestCustomers[0]
            ];
        }

        return null;
    }

    private function createGuestCustomer($companyId, $name, $phone, $email, $address) {
        $guestId = $this->generateUUID();

        $guestData = [
            'id' => $guestId,
            'company_id' => $companyId,
            'customer_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'customer_type' => 'guest',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'can_upgrade' => true
        ];

        $this->supabase->post('guest_customers', $guestData);

        return [
            'id' => $guestId,
            'type' => 'guest',
            'data' => $guestData
        ];
    }

    private function createBusinessCustomer($companyId, $name, $phone, $email, $address) {
        $businessId = $this->generateUUID();

        $businessData = [
            'id' => $businessId,
            'company_id' => $companyId,
            'business_name' => $name,
            'contact_person' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'customer_type' => 'business',
            'status' => 'active',
            'credit_limit' => 0,
            'payment_terms' => 'prepaid',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->supabase->post('business_customers', $businessData);

        return [
            'id' => $businessId,
            'type' => 'business',
            'data' => $businessData
        ];
    }

    public function upgradeGuestToRegistered($guestId, $authUserId) {
        try {
            $guest = $this->supabase->get('guest_customers', "id=eq.$guestId");
            if (empty($guest)) {
                throw new Exception('Guest customer not found');
            }

            $guestData = $guest[0];

            $profileData = [
                'id' => $authUserId,
                'full_name' => $guestData['customer_name'],
                'role' => 'customer',
                'company_id' => $guestData['company_id'],
                'phone' => $guestData['phone']
            ];
            $this->supabase->post('profiles', $profileData);

            $customerData = [
                'id' => $authUserId,
                'company_id' => $guestData['company_id'],
                'customer_name' => $guestData['customer_name'],
                'phone' => $guestData['phone'],
                'email' => $guestData['email'],
                'address' => $guestData['address'],
                'status' => 'active'
            ];
            $this->supabase->post('customers', $customerData);

            $this->supabase->put('parcels',
                ['sender_id' => $authUserId],
                "sender_phone=eq." . urlencode($guestData['phone']) . "&sender_id=is.null"
            );

            $this->supabase->put('parcels',
                ['receiver_id' => $authUserId],
                "receiver_phone=eq." . urlencode($guestData['phone']) . "&receiver_id=is.null"
            );

            $parcels = $this->supabase->get('parcels', "receiver_id=eq.$authUserId");
            foreach ($parcels as $parcel) {
                $existingDelivery = $this->supabase->get('deliveries', "parcel_id=eq." . $parcel['id']);
                if (empty($existingDelivery)) {
                    $this->createDeliveryForParcel($parcel, $authUserId);
                }
            }

            $this->supabase->put('guest_customers',
                ['status' => 'upgraded', 'upgraded_to' => $authUserId],
                "id=eq.$guestId"
            );

            return true;

        } catch (Exception $e) {
            throw new Exception('Failed to upgrade customer: ' . $e->getMessage());
        }
    }

    private function createDeliveryForParcel($parcel, $recipientId) {
        $deliveryData = [
            'id' => $this->generateUUID(),
            'parcel_id' => $parcel['id'],
            'sender_id' => $parcel['sender_id'],
            'recipient_id' => $recipientId,
            'outlet_id' => $parcel['origin_outlet_id'],
            'company_id' => $parcel['company_id'],
            'tracking_number' => $parcel['track_number'],
            'delivery_status' => 'pending',
            'delivery_date' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'delivery_address' => $parcel['receiver_address'],
            'delivery_fee' => $parcel['delivery_fee'],
            'weight' => $parcel['parcel_weight'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->supabase->post('deliveries', $deliveryData);
    }

    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
?>
