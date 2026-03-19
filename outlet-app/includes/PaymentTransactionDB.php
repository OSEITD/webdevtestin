
<?php

require_once __DIR__ . '/supabase-client.php';

class PaymentTransactionDB {
    private $supabase;
    private $supabaseUrl;
    private $supabaseKey;
    private $supabaseServiceKey;

    public function __construct() {
        global $supabaseUrl, $supabaseKey, $supabaseServiceKey;
        $this->supabase = getSupabaseClient();
        $this->supabaseUrl = $supabaseUrl;
        $this->supabaseKey = $supabaseKey;
        $this->supabaseServiceKey = $supabaseServiceKey;
    }

    /**
     * Return the normalized Supabase REST URL (always ends with /rest/v1)
     * regardless of whether SUPABASE_URL already includes /rest or /rest/v1.
     */
    private function getSupabaseRestUrl() {
        $base = rtrim($this->supabaseUrl, '/');
        // Remove any trailing /rest or /rest/v1 to avoid double segments
        $base = preg_replace('#/rest(/v1)?$#', '', $base);
        return $base . '/rest/v1';
    }
    
    public function createTransaction($data) {
        try {
            
            $amount = $data['amount'];

            // Assemble the payload for the DB. The DB trigger/function is responsible for
            // computing all financial fields (fees, commission, net, total, etc.).
            // Do not send those fields from the app; the DB will reject them.
            $transactionData = [
                'tx_ref' => $data['tx_ref'],
                'company_id' => $data['company_id'],
                'outlet_id' => $data['outlet_id'] ?? null,
                'user_id' => $data['user_id'],
                'parcel_id' => $data['parcel_id'] ?? null,
                'amount' => $amount,
                'payment_method' => $data['payment_method'],

                'payment_type' => $data['payment_type'] ?? null,
                'mobile_network' => $data['mobile_network'] ?? null,
                'mobile_number' => $this->maskMobileNumber($data['mobile_number'] ?? null),
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                // Metadata can contain financial fields; avoid sending it on insert.
                // User-facing metadata can be saved/updated after transaction is created.
            ];
            // strip out null entries so database only sees fields we care about
            // We also remove any fields that are calculated on the DB side (financial fields)
            // to avoid triggering the "Financial fields cannot be set manually" rule.
            $forbiddenFields = [
                'transaction_fee',
                'commission_percentage',
                'commission_amount',
                'net_amount',
                'total_amount',
                'currency',
                'exchange_rate',
                'vat_percentage',
                'vat_amount',
            ];

            $transactionData = array_filter($transactionData, function($v, $k) use ($forbiddenFields) {
                if (in_array($k, $forbiddenFields, true)) {
                    return false;
                }
                return $v !== null;
            }, ARRAY_FILTER_USE_BOTH);

            // DEBUG: sanity assert - should never send calculated financial fields
            foreach (['transaction_fee','commission_amount','net_amount','total_amount'] as $f) {
                if (array_key_exists($f, $transactionData)) {
                    error_log("WARNING: forbidden financial field still present: $f");
                }
            }

            // Use raw HTTP request since custom Supabase client doesn't support insert
            $url = $this->getSupabaseRestUrl() . '/payment_transactions';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transactionData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $this->supabaseServiceKey,
                'Authorization: Bearer ' . $this->supabaseServiceKey,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 201) {
                $responseData = json_decode($responseBody, true);
                return [
                    'success' => true,
                    'transaction_id' => $responseData[0]['id'] ?? null,
                    'data' => $responseData[0] ?? null
                ];
            } else {
                error_log('PaymentTransactionDB create transaction failed: HTTP ' . $httpCode . ' - ' . $responseBody);
                error_log('PaymentTransactionDB create payload: ' . json_encode($transactionData));
                return [
                    'success' => false,
                    'error' => 'Failed to create transaction: HTTP ' . $httpCode . ' - ' . $responseBody
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    
    public function verifyTransaction($txRef, $verificationData) {
        try {
            // Check if this transaction is already marked successful to avoid double-crediting the wallet.
            $existingTxn = $this->getTransactionByRef($txRef);
            $alreadySuccessful = false;
            if ($existingTxn['success'] && !empty($existingTxn['data']['status']) && strtolower($existingTxn['data']['status']) === 'successful') {
                $alreadySuccessful = true;
            }

            $updateData = [
                'lenco_tx_id' => $verificationData['transaction_id'],
                'lenco_tx_ref' => $verificationData['tx_ref'] ?? null,
                'lenco_status' => $verificationData['status'],
                'processor_response' => $verificationData['processor_response'] ?? null,
                'auth_model' => $verificationData['auth_model'] ?? null,
                'payment_type' => $verificationData['payment_type'] ?? null,
                'verified_at' => date('Y-m-d H:i:s'),
                'signature_verified' => true,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            
            if ($verificationData['status'] === 'successful') {
                $updateData['status'] = 'successful';
                $updateData['paid_at'] = date('Y-m-d H:i:s');
            } else if ($verificationData['status'] === 'failed') {
                $updateData['status'] = 'failed';
                $updateData['failed_at'] = date('Y-m-d H:i:s');
                $updateData['error_message'] = $verificationData['error_message'] ?? 'Payment failed';
            }
            
            
            if (isset($verificationData['card'])) {
                $updateData['card_last4'] = $verificationData['card']['last_4digits'] ?? null;
                $updateData['card_type'] = $verificationData['card']['type'] ?? null;
                $updateData['card_bin'] = $verificationData['card']['first_6digits'] ?? null;
            }
            
            // Use raw HTTP request for update since custom Supabase client doesn't support update
            $url = $this->getSupabaseRestUrl() . '/payment_transactions?tx_ref=eq.' . urlencode($txRef);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $this->supabaseServiceKey,
                'Authorization: Bearer ' . $this->supabaseServiceKey,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $responseData = json_decode($responseBody, true);

                // If payment was successful, update parcel status and credit the wallet (once).
                if ($verificationData['status'] === 'successful' && isset($responseData[0])) {
                    if (isset($responseData[0]['parcel_id'])) {
                        $this->updateParcelPaymentStatus($responseData[0]['parcel_id']);
                    }

                    // Update wallet ledger only once per successful payment.
                    if (!$alreadySuccessful) {
                        require_once __DIR__ . '/WalletManager.php';
                        $companyId = $responseData[0]['company_id'] ?? null;
                        $amount = isset($responseData[0]['total_amount']) ? (float)$responseData[0]['total_amount'] : null;
                        $commissionPct = isset($responseData[0]['commission_percentage']) ? (float)$responseData[0]['commission_percentage'] : 0;
                        $transactionId = $responseData[0]['id'] ?? null;

                        if ($companyId && $amount > 0) {
                            $walletSuccess = WalletManager::processPaymentReceived($companyId, $amount, $commissionPct, $transactionId);
                            if ($walletSuccess) {
                                error_log("Wallet ledger updated for transaction {$transactionId}");
                            } else {
                                error_log("Wallet ledger update failed for transaction {$transactionId}");
                            }
                        }
                    }
                }

                return [
                    'success' => true,
                    'data' => $responseData[0] ?? null
                ];
            } else {
                error_log("PaymentTransactionDB verify URL: $url");
                error_log("PaymentTransactionDB verify HTTP $httpCode response: $responseBody");
                return [
                    'success' => false,
                    'error' => 'Failed to update transaction: HTTP ' . $httpCode . ' - ' . $responseBody
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    
    public function getTransactionByRef($txRef) {
        try {
            $query = $this->supabase
                ->from('payment_transactions')
                ->select('*')
                ->eq('tx_ref', $txRef)
                ->limit(1);

            $response = $query->execute();

            if (isset($response->data) && !empty($response->data)) {
                return [
                    'success' => true,
                    'data' => $response->data[0]
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
    
    
    public function getTransactionByLencoId($lencoTxId) {
        try {
            $query = $this->supabase
                ->from('payment_transactions')
                ->select('*')
                ->eq('lenco_tx_id', $lencoTxId)
                ->limit(1);

            $response = $query->execute();

            if (isset($response->data) && !empty($response->data)) {
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
    
    
    public function incrementRetryCount($txRef) {
        try {
            // Use raw HTTP request to get current retry count
            $url = $this->getSupabaseRestUrl() . '/payment_transactions?select=retry_count&tx_ref=eq.' . urlencode($txRef);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $this->supabaseServiceKey,
                'Authorization: Bearer ' . $this->supabaseServiceKey,
                'Content-Type: application/json'
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $responseData = json_decode($responseBody, true);
                $currentCount = $responseData[0]['retry_count'] ?? 0;

                // Update retry count
                $updateUrl = $this->getSupabaseRestUrl() . '/payment_transactions?tx_ref=eq.' . urlencode($txRef);
                $ch = curl_init($updateUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['retry_count' => $currentCount + 1]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . $this->supabaseServiceKey,
                    'Authorization: Bearer ' . $this->supabaseServiceKey,
                    'Content-Type: application/json'
                ]);
                curl_exec($ch);
                curl_close($ch);

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
    
    
    public function updateSettlementStatus($txRef, $settlementData) {
        try {
            $updateData = [
                'settlement_status' => $settlementData['status'],
                'settlement_date' => $settlementData['settlement_date'] ?? date('Y-m-d H:i:s'),
                'settlement_reference' => $settlementData['reference'] ?? null,
                'settlement_amount' => $settlementData['amount'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Use raw HTTP request for update
            $url = $this->getSupabaseRestUrl() . '/payment_transactions?tx_ref=eq.' . urlencode($txRef);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $this->supabaseServiceKey,
                'Authorization: Bearer ' . $this->supabaseServiceKey,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $responseData = json_decode($responseBody, true);
                return [
                    'success' => true,
                    'data' => $responseData[0] ?? null
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
    
    
    public function generateReceiptNumber($companyId) {
        $year = date('Y');
        $month = date('m');
        
        
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
    
    
    private function maskMobileNumber($phoneNumber) {
        if (!$phoneNumber) return null;
        
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (strlen($cleaned) >= 10) {
            return substr($cleaned, 0, 3) . '****' . substr($cleaned, -3);
        }
        return $phoneNumber;
    }
    
    
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

    /**
     * Update parcel payment status to 'paid' when payment transaction is successful
     *
     * @param string $parcelId The parcel ID to update
     * @return bool Success status
     */
    private function updateParcelPaymentStatus($parcelId) {
        try {
            if (empty($parcelId)) {
                return false;
            }

            // Update parcel payment status
            $url = $this->getSupabaseRestUrl() . '/parcels?id=eq.' . urlencode($parcelId);

            $updateData = [
                'payment_status' => 'paid',
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $this->supabaseServiceKey,
                'Authorization: Bearer ' . $this->supabaseServiceKey,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                error_log("Parcel payment status updated to 'paid' - Parcel ID: {$parcelId}");
                return true;
            } else {
                error_log("Failed to update parcel payment status - HTTP {$httpCode}: {$responseBody}");
                return false;
            }
        } catch (Exception $e) {
            error_log("Error updating parcel payment status: " . $e->getMessage());
            return false;
        }
    }
}
?>
