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

            // Only allow payouts when the company has at least one successful online payment transaction (gateway).
            if (!self::hasGatewayPayments($companyId)) {
                return [
                    'success' => false,
                    'message' => 'Payouts are only allowed after at least one successful online payment (card, mobile money, or bank transfer).'
                ];
            }

           
            $wallet = self::getWallet($companyId);
            if (!$wallet) {
                return ['success' => false, 'message' => 'Wallet not found'];
            }

            if (floatval($amount) <= 0) {
                return ['success' => false, 'message' => 'Invalid amount'];
            }

            if (floatval($amount) < 5) {
                return ['success' => false, 'message' => 'Payouts must be at least 5 Kwacha.'];
            }

            $gatewayEligible = self::getGatewayEligibleBalance($companyId);
            if ($gatewayEligible <= 0) {
                return ['success' => false, 'message' => 'No gateway-eligible balance available for payout.'];
            }

            if (floatval($amount) > $gatewayEligible) {
                return ['success' => false, 'message' => 'Requested amount exceeds your online eligible balance.'];
            }

            $walletAvailable = min(floatval($wallet['available_balance']), $gatewayEligible);
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

    public static function getGatewayPaymentMethods(): array {
        return ['card', 'mobile_money', 'bank_transfer'];
    }

    public static function getGatewayPaymentFilter(): string {
        $methods = self::getGatewayPaymentMethods();
        $methodsList = implode(',', $methods);
        return "or=(payment_method.in.({$methodsList}),payment_type.in.({$methodsList}))";
    }

    public static function hasGatewayPayments($companyId) {
        $summary = self::getGatewaySummary($companyId);
        return ($summary['gateway_received'] ?? 0) > 0;
    }

    public static function getGatewayEligibleBalance($companyId) {
        $summary = self::getGatewaySummary($companyId);
        return $summary['eligible'] ?? 0.0;
    }

    /**
     * Gateway-only financial summary (excludes COD/cash).
     * Returns: ['eligible' => float, 'gateway_received' => float, 'payout_total' => float, 'payment_transaction_ids' => array]
     */
    public static function getGatewaySummary($companyId): array {
        $summary = [
            'eligible' => 0.0,
            'gateway_received' => 0.0,
            'payout_total' => 0.0,
            'payment_transaction_ids' => [],
        ];

        try {
            $client = new SupabaseClient();

            $gatewayResp = $client->getRecord(
                "payment_transactions?company_id=eq." . urlencode($companyId) . "&status=eq.successful&" . self::getGatewayPaymentFilter() . "&select=id",
                true
            );
            $gatewayData = static::extractData($gatewayResp) ?: [];
            $ids = array_values(array_filter(array_map(function ($row) { return $row['id'] ?? null; }, $gatewayData)));
            if (empty($ids)) {
                return $summary;
            }

            $summary['payment_transaction_ids'] = $ids;
            $encodedIds = implode(',', array_map('urlencode', $ids));

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

            $eligible = round(max(0.0, $netGateway - $payoutTotal), 2);

            $summary['eligible'] = $eligible;
            $summary['gateway_received'] = round($netGateway, 2);
            $summary['payout_total'] = round($payoutTotal, 2);
            return $summary;

        } catch (Exception $e) {
            error_log("Error calculating gateway summary for company {$companyId}: " . $e->getMessage());
        }

        return $summary;
    }

    /**
     * Gateway-only transactions for UI (excludes COD/cash).
     */
    public static function getGatewayTransactions($companyId, $limit = 50) {
        $rows = [];
        try {
            $summary = self::getGatewaySummary($companyId);
            $ids = $summary['payment_transaction_ids'] ?? [];

            if (empty($ids)) {
                return [];
            }

            $client = new SupabaseClient();
            $encodedIds = implode(',', array_map('urlencode', $ids));

            // Fetch gateway payments (successful only)
            $paymentResp = $client->getRecord(
                "payment_transactions?id=in.({$encodedIds})&order=paid_at.desc,created_at.desc&limit={$limit}",
                true
            );
            $payments = static::extractData($paymentResp) ?: [];
            foreach ($payments as $txn) {
                $rows[] = [
                    'created_at' => $txn['paid_at'] ?: $txn['created_at'],
                    'description' => 'Gateway payment (' . ($txn['payment_method'] ?? 'online') . ')',
                    'amount' => floatval($txn['amount'] ?? $txn['total_amount'] ?? 0),
                    'transaction_type' => 'payment_credit',
                    'type' => 'payment_credit',
                    'reference' => $txn['tx_ref'] ?? '',
                ];
            }

            // Include commission debits tied to these transactions
            $walletResp = $client->getRecord(
                "wallet_transactions?company_id=eq." . urlencode($companyId) . "&payment_transaction_id=in.({$encodedIds})&transaction_type=eq.commission_debit",
                true
            );
            $walletRows = static::extractData($walletResp) ?: [];
            foreach ($walletRows as $tx) {
                $rows[] = [
                    'created_at' => $tx['created_at'] ?? null,
                    'description' => 'Commission debit',
                    'amount' => floatval($tx['amount'] ?? 0) * -1,
                    'transaction_type' => 'commission_debit',
                    'type' => 'commission_debit',
                    'reference' => $tx['reference'] ?? '',
                ];
            }

            // Include payouts (they draw from the gateway pool)
            $payoutResp = $client->getRecord(
                "wallet_transactions?company_id=eq." . urlencode($companyId) . "&transaction_type=eq.payout_debit",
                true
            );
            $payoutRows = static::extractData($payoutResp) ?: [];
            foreach ($payoutRows as $tx) {
                $rows[] = [
                    'created_at' => $tx['created_at'] ?? null,
                    'description' => 'Payout request',
                    'amount' => floatval($tx['amount'] ?? 0) * -1,
                    'transaction_type' => 'payout_debit',
                    'type' => 'payout_debit',
                    'reference' => $tx['reference'] ?? '',
                ];
            }

            usort($rows, function($a, $b) {
                $aTime = strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
                $bTime = strtotime($b['created_at'] ?? '1970-01-01 00:00:00');
                return $bTime <=> $aTime;
            });

            return array_slice($rows, 0, $limit);
        } catch (Exception $e) {
            error_log("Error getting gateway transactions for company {$companyId}: " . $e->getMessage());
        }
        return [];
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
