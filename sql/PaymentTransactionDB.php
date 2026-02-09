<?php

require_once __DIR__ . '/../../includes/supabase.php';

class PaymentTransactionDB {
    private $supabase;
    
    public function __construct() {
        $this->supabase = getSupabaseClient();
    }
    
    /**
     * Create a new payment transaction record
     * 
     * @param array $data Transaction data
     * @return array Result with success status and transaction ID
     */
    public function createTransaction($data) {
        try {
            // Calculate commission if not provided
            $commissionPercentage = $data['commission_percentage'] ?? 0;
            $amount = $data['amount'];
            $commissionAmount = $data['commission_amount'] ?? ($amount * $commissionPercentage / 100);
            $netAmount = $amount - $commissionAmount;
            
            // Calculate VAT (16% for Zambia)
            $vatPercentage = $data['vat_percentage'] ?? 16.00;
            $vatAmount = $data['vat_amount'] ?? ($amount * $vatPercentage / 100);
            
            $transactionData = [
                'tx_ref' => $data['tx_ref'],
                'company_id' => $data['company_id'],
                'outlet_id' => $data['outlet_id'] ?? null,
                'user_id' => $data['user_id'],
                'parcel_id' => $data['parcel_id'] ?? null,
                'amount' => $amount,
                'transaction_fee' => $data['transaction_fee'] ?? 0,
                'commission_percentage' => $commissionPercentage,
                'commission_amount' => $commissionAmount,
                'net_amount' => $netAmount,
                'total_amount' => $data['total_amount'],
                'currency' => $data['currency'] ?? 'ZMW',
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'original_amount' => $data['original_amount'] ?? null,
                'original_currency' => $data['original_currency'] ?? null,
                'payment_method' => $data['payment_method'],
                'payment_type' => $data['payment_type'] ?? null,
                'mobile_network' => $data['mobile_network'] ?? null,
                'mobile_number' => $this->maskMobileNumber($data['mobile_number'] ?? null),
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'payment_link' => $data['payment_link'] ?? null,
                'redirect_url' => $data['redirect_url'] ?? null,
                'vat_percentage' => $vatPercentage,
                'vat_amount' => $vatAmount,
                'receipt_number' => $data['receipt_number'] ?? null,
                'fiscal_year' => $data['fiscal_year'] ?? date('Y'),
                'accounting_period' => $data['accounting_period'] ?? date('Y-m'),
                'metadata' => json_encode($data['metadata'] ?? []),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'device_fingerprint' => $data['device_fingerprint'] ?? null,
                'geolocation' => isset($data['geolocation']) ? json_encode($data['geolocation']) : null,
                'status' => 'pending',
                'settlement_status' => 'pending',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
            ];
            
            $response = $this->supabase
                ->from('payment_transactions')
                ->insert($transactionData)
                ->select()
                ->single()
                ->execute();
            
            if ($response->status === 201) {
                return [
                    'success' => true,
                    'transaction_id' => $response->data->id,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to create transaction'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update transaction status after payment verification
     * 
     * @param string $txRef Transaction reference
     * @param array $verificationData Data from Flutterwave verification
     * @return array Result with success status
     */
    public function verifyTransaction($txRef, $verificationData) {
        try {
            $updateData = [
                'flutterwave_tx_id' => $verificationData['transaction_id'],
                'flutterwave_tx_ref' => $verificationData['tx_ref'] ?? null,
                'flutterwave_status' => $verificationData['status'],
                'processor_response' => $verificationData['processor_response'] ?? null,
                'auth_model' => $verificationData['auth_model'] ?? null,
                'payment_type' => $verificationData['payment_type'] ?? null,
                'verified_at' => date('Y-m-d H:i:s'),
                'signature_verified' => true,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Set status based on Flutterwave status
            if ($verificationData['status'] === 'successful') {
                $updateData['status'] = 'successful';
                $updateData['paid_at'] = date('Y-m-d H:i:s');
            } else if ($verificationData['status'] === 'failed') {
                $updateData['status'] = 'failed';
                $updateData['failed_at'] = date('Y-m-d H:i:s');
                $updateData['error_message'] = $verificationData['error_message'] ?? 'Payment failed';
            }
            
            // Add card details if available
            if (isset($verificationData['card'])) {
                $updateData['card_last4'] = $verificationData['card']['last_4digits'] ?? null;
                $updateData['card_type'] = $verificationData['card']['type'] ?? null;
                $updateData['card_bin'] = $verificationData['card']['first_6digits'] ?? null;
            }
            
            $response = $this->supabase
                ->from('payment_transactions')
                ->update($updateData)
                ->eq('tx_ref', $txRef)
                ->select()
                ->single()
                ->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to update transaction'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update transaction status (for COD, cash, or manual status updates)
     * 
     * @param string $txRef Transaction reference (or parcel_id for COD lookup)
     * @param string $newStatus New status (successful, failed, cancelled, etc.)
     * @param array $additionalData Additional fields to update
     * @return array Result with success status
     */
    public function updateTransactionStatus($txRef, $newStatus, $additionalData = []) {
        try {
            $updateData = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Set paid_at or failed_at based on status
            if ($newStatus === 'successful' && !isset($additionalData['paid_at'])) {
                $updateData['paid_at'] = date('Y-m-d H:i:s');
            } else if ($newStatus === 'failed' && !isset($additionalData['failed_at'])) {
                $updateData['failed_at'] = date('Y-m-d H:i:s');
            } else if ($newStatus === 'cancelled') {
                $updateData['error_message'] = $additionalData['error_message'] ?? 'Payment cancelled';
            }
            
            // Merge additional data
            $updateData = array_merge($updateData, $additionalData);
            
            $response = $this->supabase
                ->from('payment_transactions')
                ->update($updateData)
                ->eq('tx_ref', $txRef)
                ->select()
                ->single()
                ->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to update transaction status'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update COD payment status when parcel is delivered
     * 
     * @param string $parcelId Parcel UUID
     * @return array Result with success status
     */
    public function markCODPaymentAsCollected($parcelId) {
        try {
            $updateData = [
                'status' => 'successful',
                'paid_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'settlement_status' => 'pending',
                'metadata' => json_encode(['payment_note' => 'COD payment collected on delivery'])
            ];
            
            $response = $this->supabase
                ->from('payment_transactions')
                ->update($updateData)
                ->eq('parcel_id', $parcelId)
                ->eq('payment_method', 'cod')
                ->eq('status', 'pending')
                ->select()
                ->execute();
            
            if ($response->status === 200 && count($response->data) > 0) {
                return [
                    'success' => true,
                    'data' => $response->data[0] ?? null,
                    'message' => 'COD payment marked as collected'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No pending COD payment found for this parcel'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get transaction by reference
     * 
     * @param string $txRef Transaction reference
     * @return array Transaction data or error
     */
    public function getTransactionByRef($txRef) {
        try {
            $response = $this->supabase
                ->from('payment_transactions')
                ->select('*')
                ->eq('tx_ref', $txRef)
                ->single()
                ->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Transaction not found'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get transaction by Flutterwave transaction ID
     * 
     * @param string $flwTxId Flutterwave transaction ID
     * @return array Transaction data or error
     */
    public function getTransactionByFlutterwaveId($flwTxId) {
        try {
            $response = $this->supabase
                ->from('payment_transactions')
                ->select('*')
                ->eq('flutterwave_tx_id', $flwTxId)
                ->single()
                ->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Transaction not found'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user payment history
     * 
     * @param string $userId User UUID
     * @param int $limit Number of records to return
     * @return array Payment history
     */
    public function getUserPaymentHistory($userId, $limit = 10) {
        try {
            $response = $this->supabase
                ->from('payment_transactions')
                ->select('*, parcels(track_number)')
                ->eq('user_id', $userId)
                ->order('created_at', ['ascending' => false])
                ->limit($limit)
                ->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch payment history'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get company payment statistics
     * 
     * @param string $companyId Company UUID
     * @param string $startDate Start date (Y-m-d H:i:s)
     * @param string $endDate End date (Y-m-d H:i:s)
     * @return array Statistics
     */
    public function getCompanyStats($companyId, $startDate = null, $endDate = null) {
        try {
            if (!$startDate) {
                $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
            }
            if (!$endDate) {
                $endDate = date('Y-m-d H:i:s');
            }
            
            $response = $this->supabase->rpc(
                'get_company_payment_stats',
                [
                    'comp_id' => $companyId,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            )->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch statistics'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update transaction retry count
     * 
     * @param string $txRef Transaction reference
     * @return array Result
     */
    public function incrementRetryCount($txRef) {
        try {
            $response = $this->supabase
                ->from('payment_transactions')
                ->select('retry_count')
                ->eq('tx_ref', $txRef)
                ->single()
                ->execute();
            
            if ($response->status === 200) {
                $currentCount = $response->data->retry_count ?? 0;
                
                $updateResponse = $this->supabase
                    ->from('payment_transactions')
                    ->update(['retry_count' => $currentCount + 1])
                    ->eq('tx_ref', $txRef)
                    ->execute();
                
                return [
                    'success' => true,
                    'retry_count' => $currentCount + 1
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Transaction not found'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update settlement status
     * 
     * @param string $txRef Transaction reference
     * @param array $settlementData Settlement information
     * @return array Result
     */
    public function updateSettlementStatus($txRef, $settlementData) {
        try {
            $updateData = [
                'settlement_status' => $settlementData['status'],
                'settlement_date' => $settlementData['settlement_date'] ?? date('Y-m-d H:i:s'),
                'settlement_reference' => $settlementData['reference'] ?? null,
                'settlement_amount' => $settlementData['amount'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $response = $this->supabase
                ->from('payment_transactions')
                ->update($updateData)
                ->eq('tx_ref', $txRef)
                ->select()
                ->single()
                ->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to update settlement status'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate receipt number
     * 
     * @param string $companyId Company UUID
     * @return string Receipt number
     */
    public function generateReceiptNumber($companyId) {
        $year = date('Y');
        $month = date('m');
        
        // Get count of transactions this month for this company
        try {
            $response = $this->supabase
                ->from('payment_transactions')
                ->select('id', ['count' => 'exact'])
                ->eq('company_id', $companyId)
                ->gte('created_at', date('Y-m-01 00:00:00'))
                ->lte('created_at', date('Y-m-t 23:59:59'))
                ->execute();
            
            $count = $response->count ?? 0;
            $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);
            
            return "RCP-{$year}{$month}-{$sequence}";
        } catch (Exception $e) {
            return "RCP-{$year}{$month}-" . uniqid();
        }
    }
    
    /**
     * Get commission report
     * 
     * @param string $companyId Company UUID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Commission data
     */
    public function getCommissionReport($companyId, $startDate = null, $endDate = null) {
        try {
            if (!$startDate) {
                $startDate = date('Y-m-01 00:00:00');
            }
            if (!$endDate) {
                $endDate = date('Y-m-t 23:59:59');
            }
            
            $response = $this->supabase
                ->from('payment_transactions')
                ->select('
                    payment_method,
                    mobile_network,
                    count:id.count(),
                    amount.sum(),
                    commission_amount.sum(),
                    commission_percentage.avg(),
                    net_amount.sum(),
                    vat_amount.sum()
                ')
                ->eq('company_id', $companyId)
                ->eq('status', 'successful')
                ->gte('created_at', $startDate)
                ->lte('created_at', $endDate)
                ->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch commission report'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get pending settlements
     * 
     * @param string $companyId Company UUID
     * @return array Pending settlement transactions
     */
    public function getPendingSettlements($companyId) {
        try {
            $response = $this->supabase
                ->from('payment_transactions')
                ->select('*')
                ->eq('company_id', $companyId)
                ->eq('status', 'successful')
                ->eq('settlement_status', 'pending')
                ->order('created_at', ['ascending' => false])
                ->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data,
                    'total_amount' => array_sum(array_column($response->data, 'settlement_amount'))
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch pending settlements'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Mask mobile number for security
     * 
     * @param string $phoneNumber Phone number
     * @return string Masked phone number
     */
    private function maskMobileNumber($phoneNumber) {
        if (!$phoneNumber) return null;
        
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (strlen($cleaned) >= 10) {
            return substr($cleaned, 0, 3) . '****' . substr($cleaned, -3);
        }
        return $phoneNumber;
    }
    
    /**
     * Get daily revenue for a company
     * 
     * @param string $companyId Company UUID
     * @param string $date Date in Y-m-d format
     * @return array Revenue data
     */
    public function getDailyRevenue($companyId, $date = null) {
        try {
            if (!$date) {
                $date = date('Y-m-d');
            }
            
            $response = $this->supabase->rpc(
                'get_daily_revenue',
                [
                    'comp_id' => $companyId,
                    'target_date' => $date
                ]
            )->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch revenue data'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get payment method distribution
     * 
     * @param string $companyId Company UUID
     * @param int $daysBack Number of days to look back
     * @return array Distribution data
     */
    public function getPaymentMethodDistribution($companyId, $daysBack = 30) {
        try {
            $response = $this->supabase->rpc(
                'get_payment_method_distribution',
                [
                    'comp_id' => $companyId,
                    'days_back' => $daysBack
                ]
            )->execute();
            
            if ($response->status === 200) {
                return [
                    'success' => true,
                    'data' => $response->data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch distribution data'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
