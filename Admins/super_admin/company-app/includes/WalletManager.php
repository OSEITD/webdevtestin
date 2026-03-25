<?php
/**
 * Wallet Manager for Company App
 * Handles operations related to accessing company_wallets, wallet_transactions, and requesting payouts.
 * Uses the local supabase-client.php for database access.
 */
require_once __DIR__ . '/../api/supabase-client.php';

class CompanyWalletManager {

    /**
     * Getting wallet for a company
     * @param string $companyId
     * @return array|null
     */
    public static function getWallet($companyId) {
        try {
            $client = new SupabaseClient();
            $response = $client->getRecord("company_wallets?company_id=eq." . urlencode($companyId) . "&limit=1", true); // useServiceRole=true
            
            $data = static::extractData($response);
            if (is_array($data) && count($data) > 0) {
                return $data[0];
            }
        } catch (Exception $e) {
            error_log("Error getting wallet for company {$companyId}: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Requesting a payout
     * @param string $companyId
     * @param float $amount
     * @param string $payoutMethod
     * @param array $details
     * @return array ['success' => bool, 'message' => string]
     */
    public static function requestPayout($companyId, $amount, $payoutMethod, $details = []) {
        try {
            $client = new SupabaseClient();

            // Only allow payouts when the company has at least one successful payment transaction.
            // This includes cash, mobile money, card, and bank transfer payments.
            if (!self::hasGatewayPayments($companyId)) {
                return [
                    'success' => false,
                    'message' => 'Payouts are only allowed after at least one successful payment transaction (e.g., cash, mobile money, or card).'
                ];
            }

           
            $wallet = self::getWallet($companyId);
            if (!$wallet) {
                return ['success' => false, 'message' => 'Wallet not found'];
            }

            if (floatval($amount) <= 0) {
                return ['success' => false, 'message' => 'Invalid amount'];
            }

            $gatewayEligible = self::getGatewayEligibleBalance($companyId);
            if ($gatewayEligible <= 0) {
                return ['success' => false, 'message' => 'No gateway-eligible balance available for payout.'];
            }

            if (floatval($amount) > $gatewayEligible) {
                return ['success' => false, 'message' => 'Requested amount exceeds your online eligible balance.'];
            }

            $walletAvailable = floatval($wallet['available_balance']);
            if ($walletAvailable < $amount) {
                error_log("WalletManager: available_balance ({$walletAvailable}) is less than requested amount ({$amount}), using gatewayEligible ({$gatewayEligible}) for payout calculation.");
                $walletAvailable = $gatewayEligible;
            }

            $newAvailable = max(0.0, $walletAvailable - floatval($amount));
            $newPending = floatval($wallet['pending_balance']) + floatval($amount);

            $payoutData = [
                'company_id' => $companyId,
                'amount' => floatval($amount),
                'currency' => $details['currency'] ?? null,
                'payout_method' => $payoutMethod,
                'status' => 'pending',
                'requested_by' => $details['requested_by'] ?? null,
                'notes' => $details['notes'] ?? null
            ];

            if ($payoutMethod === 'bank_transfer') {
                $payoutData['bank_name'] = $details['bank_name'] ?? null;
                $payoutData['account_number'] = $details['bank_account_number'] ?? null;
                $payoutData['account_name'] = $details['bank_account_name'] ?? null;
            } elseif ($payoutMethod === 'mobile_money') {
                $payoutData['mobile_number'] = $details['mobile_number'] ?? null;
            }

            $payoutRes = $client->post('company_payouts', $payoutData, true);
            $payoutRecord = static::extractData($payoutRes);
            $payoutId = null;
            if (is_array($payoutRecord) && count($payoutRecord) > 0) {
                $payoutId = $payoutRecord[0]['id'] ?? null;
            }

            try {
                $companyUpdate = ['payout_method' => $payoutMethod];
                if ($payoutMethod === 'bank_transfer') {
                    $companyUpdate['bank_name'] = $details['bank_name'] ?? null;
                    $companyUpdate['bank_account_number'] = $details['bank_account_number'] ?? null;
                    $companyUpdate['bank_account_name'] = $details['bank_account_name'] ?? null;
                } elseif ($payoutMethod === 'mobile_money') {
                    $companyUpdate['mobile_money_number'] = $details['mobile_number'] ?? null;
                }
                $client->put("companies?id=eq.{$companyId}", $companyUpdate);
            } catch (Exception $ignore) {
              
                error_log('Unable to persist payout destination on company record: ' . $ignore->getMessage());
            }

            $transactionData = [
                'company_id' => $companyId,
                'amount' => floatval($amount),
                'transaction_type' => 'payout_debit',
                'running_balance' => $newAvailable,
                'description' => 'Payout request',
                'payout_id' => $payoutId, // Link to payout
                'reference' => $payoutId ?: null,
                'created_by' => $_SESSION['user_id'] ?? null
            ];

            $txResp = $client->post('wallet_transactions', $transactionData, true);
            $txData = static::extractData($txResp);
            if (empty($txData) || count($txData) === 0) {
               
                return ['success' => false, 'message' => 'Unable to record payout transaction. Please try again or contact support.'];
            }

        
            $walletUpdate = [
                'available_balance' => $newAvailable,
                'pending_balance' => $newPending,
                'updated_at' => date('c')
            ];
            $client->put("company_wallets?id=eq.{$companyId}", $walletUpdate, true);

            return ['success' => true, 'message' => 'Payout requested successfully'];

        } catch (Exception $e) {
            error_log("Error requesting payout for company {$companyId}: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while requesting payout: ' . $e->getMessage()];
        }
    }

    
    public static function hasGatewayPayments($companyId) {
        return self::getGatewayEligibleBalance($companyId) > 0;
    }

    public static function getGatewayEligibleBalance($companyId) {
        try {
            $client = new SupabaseClient();

            $gatewayMethods = ['mobile_money', 'bank_transfer'];
            $gatewayMethodsList = implode(',', $gatewayMethods);
            $gatewayResp = $client->getRecord(
                "payment_transactions?company_id=eq." . urlencode($companyId) . "&status=eq.successful&or=(payment_method.in.({$gatewayMethodsList}),payment_type.in.({$gatewayMethodsList}))&select=id",
                true
            );
            $gatewayData = static::extractData($gatewayResp);
            if (empty($gatewayData)) {
               
                try {
                    $debugResp = $client->getRecord(
                        "payment_transactions?company_id=eq.{$companyId}&select=id,status,payment_method,amount&limit=10&order=created_at.desc",
                        true
                    );
                    $debugData = static::extractData($debugResp) ?: [];
                    error_log('WalletManager: gateway payment filter returned none; sample recent payment_transactions: ' . json_encode($debugData));
                } catch (Exception $e) {
                    error_log('WalletManager: failed to fetch payment_transactions debug sample: ' . $e->getMessage());
                }
                return 0.0;
            }

            $ids = array_map(function ($row) { return $row['id'] ?? null; }, $gatewayData);
            $ids = array_filter($ids);
            if (empty($ids)) {
                return 0.0;
            }

            $encodedIds = implode(',', array_map('urlencode', $ids));

            // Sum gateway-derived wallet transactions
            $walletResp = $client->getRecord(
                "wallet_transactions?company_id=eq." . urlencode($companyId) . "&payment_transaction_id=in.({$encodedIds})",
                true
            );
            $walletData = static::extractData($walletResp) ?: [];

            $netGateway = 0.0;
            foreach ($walletData as $tx) {
                $type = $tx['transaction_type'] ?? '';
                $amount = floatval($tx['amount'] ?? 0);
                if ($type === 'payment_credit') {
                    $netGateway += $amount;
                } elseif ($type === 'commission_debit') {
                    $netGateway -= $amount;
                }
            }

            $adjustResp = $client->getRecord(
                "wallet_transactions?company_id=eq.{$companyId}&transaction_type=in.(adjustment_credit,refund_credit)",
                true
            );
            $adjustData = static::extractData($adjustResp) ?: [];
            foreach ($adjustData as $tx) {
                $netGateway += floatval($tx['amount'] ?? 0);
            }

       
            $payoutResp = $client->getRecord(
                "wallet_transactions?company_id=eq.{$companyId}&transaction_type=eq.payout_debit",
                true
            );
            $payoutData = static::extractData($payoutResp) ?: [];
            $payoutTotal = 0.0;
            foreach ($payoutData as $tx) {
                $payoutTotal += floatval($tx['amount'] ?? 0);
            }

            $eligible = $netGateway - $payoutTotal;
            return max(0.0, $eligible);

        } catch (Exception $e) {
            error_log("Error calculating gateway-eligible balance for company {$companyId}: " . $e->getMessage());
        }
        return 0.0;
    }

   
    public static function getTransactions($companyId, $limit = 50) {
        try {
            $client = new SupabaseClient();
            $endpoint = "wallet_transactions?company_id=eq." . urlencode($companyId) . "&order=created_at.desc&limit={$limit}";
            error_log("WalletManager: fetching existing wallet transactions with endpoint: {$endpoint}");
            $response = $client->getRecord($endpoint, true);
            $data = static::extractData($response) ?: [];

            if (count($data) < 5) {
                $paymentTxns = self::getPaymentTransactions($companyId, $limit);
                foreach ($paymentTxns as $txn) {
                    $data[] = [
                        'created_at' => $txn['paid_at'] ?: $txn['created_at'],
                        'description' => 'Gateway payment (' . ($txn['payment_method'] ?? 'unknown') . ')',
                        'amount' => floatval($txn['amount'] ?? 0),
                        'type' => 'payment_credit',
                        'reference' => $txn['tx_ref'] ?? '',
                        'status' => $txn['status'] ?? ''
                    ];
                }
            }

            usort($data, function($a, $b) {
                $aTime = strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
                $bTime = strtotime($b['created_at'] ?? '1970-01-01 00:00:00');
                return $bTime <=> $aTime;
            });

            $data = array_slice($data, 0, $limit);
            error_log("WalletManager: returned " . count($data) . " transaction(s) for company {$companyId}");
            return $data;
        } catch (Exception $e) {
            error_log("Error getting transactions for company {$companyId}: " . $e->getMessage());
        }
        return [];
    }

    /**
     * Fetching successful payment transactions for company to include in wallet history.
     * @param string $companyId
     * @param int $limit
     * @return array
     */
    public static function getPaymentTransactions($companyId, $limit = 50) {
        try {
            $client = new SupabaseClient();
            $endpoint = "payment_transactions?company_id=eq." . urlencode($companyId) . "&status=eq.successful&order=paid_at.desc,created_at.desc&limit={$limit}";
            error_log("WalletManager: fetching payment transactions with endpoint: {$endpoint}");
            $response = $client->getRecord($endpoint, true);
            $data = static::extractData($response) ?: [];
            return $data;
        } catch (Exception $e) {
            error_log("Error getting payment transactions for company {$companyId}: " . $e->getMessage());
        }
        return [];
    }

    /**  payouts for a company*/
    public static function getPayouts($companyId, $limit = 50) {
        try {
            $client = new SupabaseClient();
            $response = $client->getRecord("company_payouts?company_id=eq." . urlencode($companyId) . "&order=created_at.desc&limit={$limit}", true);
            return static::extractData($response) ?: [];
        } catch (Exception $e) {
            error_log("Error getting payouts for company {$companyId}: " . $e->getMessage());
        }
        return [];
    }

    
    public static function applyPayoutStatusUpdate($companyId, $amount, $status) {
        try {
            $wallet = self::getWallet($companyId);
            if (!$wallet) {
                return false;
            }

            $updateData = [];
            if ($status === 'completed') {
                $updateData['pending_balance'] = max(0, (float)$wallet['pending_balance'] - $amount);
                $updateData['total_paid_out'] = (float)$wallet['total_paid_out'] + $amount;
                $updateData['last_payout_at'] = date('c');
            } elseif (in_array($status, ['failed', 'cancelled'], true)) {
                $updateData['pending_balance'] = max(0, (float)$wallet['pending_balance'] - $amount);
                $updateData['available_balance'] = (float)$wallet['available_balance'] + $amount;
            }

            if (empty($updateData)) {
                return true;
            }

            $updateData['updated_at'] = date('c');
            $client = new SupabaseClient();
            $client->put("company_wallets?company_id=eq." . urlencode($companyId), $updateData, true);

            return true;
        } catch (Exception $e) {
            error_log("Error applying payout status update for company {$companyId}: " . $e->getMessage());
            return false;
        }
    }

    public static function getPendingWithdrawals($companyId) {
        try {
            $client = new SupabaseClient();
            // Sum payout amounts for requests that are still outstanding.
            $response = $client->getRecord(
                "company_payouts?company_id=eq." . urlencode($companyId) . "&status=not.in.(completed,failed,cancelled)&select=amount",
                true
            );
            $rows = static::extractData($response) ?: [];
            $sum = 0.0;
            foreach ($rows as $r) {
                $sum += floatval($r['amount'] ?? 0);
            }
            return $sum;
        } catch (Exception $e) {
            error_log("Error computing pending withdrawals for company {$companyId}: " . $e->getMessage());
        }
        return 0.0;
    }
    
    /**
     * Helper to safely extract array data from SupabaseClient responses
     */
    private static function extractData($response) {
        if (is_array($response)) {
            
            return $response;
        } elseif (is_object($response) && isset($response->data)) {
            return is_array($response->data) ? $response->data : (array)$response->data;
        }
        return null;
    }
}
