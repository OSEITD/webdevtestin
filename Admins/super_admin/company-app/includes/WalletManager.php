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

            // 3. Calculate new balances
            $newAvailable = floatval($wallet['available_balance']) - floatval($amount);
            $newPending = floatval($wallet['pending_balance']) + floatval($amount);

            // 4. Update wallet balances
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
                'payout_method' => $payoutMethod,
                'status' => 'pending'
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
                'payout_id' => $payoutId // Link to payout
            ];
            $client->post('wallet_transactions', $transactionData, true);

            return ['success' => true, 'message' => 'Payout requested successfully'];

        } catch (Exception $e) {
            error_log("Error requesting payout for company {$companyId}: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while requesting payout: ' . $e->getMessage()];
        }
    }

    /**
     * Get recent transactions for a company
     */
    public static function getTransactions($companyId, $limit = 50) {
        try {
            $client = new SupabaseClient();
            $response = $client->getRecord("wallet_transactions?company_id=eq.{$companyId}&order=created_at.desc&limit={$limit}", true);
            return static::extractData($response) ?: [];
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
