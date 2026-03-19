<?php
/**
 * Wallet Manager for Company App
 * Handles operations related to accessing company_wallets, wallet_transactions, and requesting payouts.
 * Uses the local supabase-client.php for database access.
 */
require_once __DIR__ . '/../api/supabase-client.php';

class CompanyWalletManager {

    /**
     * Get wallet for a company
     * @param string $companyId
     * @return array|null
     */
    public static function getWallet($companyId) {
        try {
            $client = new SupabaseClient();
            $response = $client->getRecord("company_wallets?company_id=eq.{$companyId}&limit=1", true); // useServiceRole=true
            
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
     * Request a payout
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

            // 1. Get current wallet
            $wallet = self::getWallet($companyId);
            if (!$wallet) {
                return ['success' => false, 'message' => 'Wallet not found'];
            }

            // 2. Validate amount
            if (floatval($amount) <= 0) {
                return ['success' => false, 'message' => 'Invalid amount'];
            }
            if (floatval($amount) > floatval($wallet['available_balance'])) {
                return ['success' => false, 'message' => 'Insufficient available balance'];
            }

            // 3. Enforce gateway-eligible balance rules (only allow payout from gateway-derived funds)
            $gatewayEligible = self::getGatewayEligibleBalance($companyId);
            if (floatval($amount) > $gatewayEligible) {
                return ['success' => false, 'message' => 'Requested amount exceeds gateway-eligible balance.'];
            }

            // 4. Calculate new balances (withdraw from available balance)
            $newAvailable = floatval($wallet['available_balance']) - floatval($amount);
            $newPending = floatval($wallet['pending_balance']) + floatval($amount);

            // 5. Update wallet balances
            $updateData = [
                'available_balance' => $newAvailable,
                'pending_balance' => $newPending,
                'updated_at' => gmdate('Y-m-d\TH:i:s\Z')
            ];
            $client->put("company_wallets?company_id=eq.{$companyId}", $updateData);

            // 5. Create payout record
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

            // 5b. Persist payout destination into the company record so subsequent requests can prefill.
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
                // Non-blocking, just log for visibility
                error_log('Unable to persist payout destination on company record: ' . $ignore->getMessage());
            }

            // 6. Record transaction
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
            $client->post('wallet_transactions', $transactionData, true);

            return ['success' => true, 'message' => 'Payout requested successfully'];

        } catch (Exception $e) {
            error_log("Error requesting payout for company {$companyId}: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while requesting payout: ' . $e->getMessage()];
        }
    }

    /**
     * Determine whether the company has any successful payment transactions from a gateway.
     * This helps ensure payouts are only requested when real gateway funds have been received.
     */
    public static function hasGatewayPayments($companyId) {
        return self::getGatewayEligibleBalance($companyId) > 0;
    }

    /**
     * Returns the balance that is eligible for payout (i.e. funds originating from gateway payments).
     * This is computed as the net of gateway payment credits minus commissions and prior payout debits.
     */
    public static function getGatewayEligibleBalance($companyId) {
        try {
            $client = new SupabaseClient();

            // Find gateway payment transaction IDs (successful payments only).
            // Only include actual gateway methods (bank transfer and mobile money) and ignore cash/COD.
            // The `payment_type` field may also be set in some flows, so we check both columns.
            $gatewayMethods = ['mobile_money', 'bank_transfer'];
            $gatewayMethodsList = implode(',', $gatewayMethods);
            $gatewayResp = $client->getRecord(
                "payment_transactions?company_id=eq.{$companyId}&status=eq.successful&or=(payment_method.in.({$gatewayMethodsList}),payment_type.in.({$gatewayMethodsList}))&select=id",
                true
            );
            $gatewayData = static::extractData($gatewayResp);
            if (empty($gatewayData)) {
                // Debug: log some recent payment transactions so we can see why none match the filter
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
                "wallet_transactions?company_id=eq.{$companyId}&payment_transaction_id=in.({$encodedIds})",
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

            // Subtract payout debits (money already paid out) so the balance reflects remaining
            // gateway-derived funds available for future payouts.
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

    /**
     * Get recent transactions for a company
     */
    public static function getTransactions($companyId, $limit = 50) {
        try {
            $client = new SupabaseClient();
            $endpoint = "wallet_transactions?company_id=eq.{$companyId}&order=created_at.desc&limit={$limit}";
            error_log("WalletManager: fetching transactions with endpoint: {$endpoint}");
            $response = $client->getRecord($endpoint, true);
            $data = static::extractData($response) ?: [];
            error_log("WalletManager: fetched " . count($data) . " transaction(s) for company {$companyId}");
            return $data;
        } catch (Exception $e) {
            error_log("Error getting transactions for company {$companyId}: " . $e->getMessage());
        }
        return [];
    }

    /**
     * Get payouts for a company
     */
    public static function getPayouts($companyId, $limit = 50) {
        try {
            $client = new SupabaseClient();
            $response = $client->getRecord("company_payouts?company_id=eq.{$companyId}&order=created_at.desc&limit={$limit}", true);
            return static::extractData($response) ?: [];
        } catch (Exception $e) {
            error_log("Error getting payouts for company {$companyId}: " . $e->getMessage());
        }
        return [];
    }
    
    /**
     * Helper to safely extract array data from SupabaseClient responses
     */
    private static function extractData($response) {
        if (is_array($response)) {
            // Unwrapped array
            return $response;
        } elseif (is_object($response) && isset($response->data)) {
            return is_array($response->data) ? $response->data : (array)$response->data;
        }
        return null;
    }
}
