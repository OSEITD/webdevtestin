<?php
/**
 * Wallet Manager for Outlet App
 * Handles operations related to company_wallets, wallet_transactions, and company_payouts
 * Uses SupabaseHelper for database access.
 */
require_once __DIR__ . '/supabase-helper.php';

class WalletManager {
    
    private static function getDb() {
        return new SupabaseHelper();
    }

    /**
     * Get wallet for a company
     * @param string $companyId
     * @return array|null 
     */
    public static function getWallet($companyId) {
        try {
            $db = self::getDb();
            $response = $db->get('company_wallets', "company_id=eq." . urlencode($companyId) . "&limit=1");
            if (is_array($response) && isset($response[0])) {
                return $response[0];
            }

            // Wallet not found; create a fresh one (useful when onboarding new companies)
            $created = $db->post('company_wallets', [
                'company_id' => $companyId,
                'available_balance' => 0,
                'pending_balance' => 0,
                'total_earned' => 0,
                'total_paid_out' => 0,
                'total_commission_paid' => 0,
                'currency' => 'ZMW'
            ]);
            if (is_array($created) && isset($created[0])) {
                return $created[0];
            }
        } catch (Exception $e) {
            error_log("Error getting or creating wallet for company {$companyId}: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Log a wallet transaction to the immutable ledger
     */
    public static function logTransaction($companyId, $referenceId, $payoutId, $type, $amount, $runningBalance, $description, $userId = null) {
        $transactionData = [
            'company_id' => $companyId,
            'transaction_type' => $type,
            'amount' => $amount,
            'running_balance' => $runningBalance,
            'description' => $description
        ];
        
        if ($referenceId) {
            $transactionData['payment_transaction_id'] = $referenceId;
        }
        if ($payoutId) {
            $transactionData['payout_id'] = $payoutId;
        }
        if ($userId) {
            $transactionData['created_by'] = $userId;
        }

        try {
            $db = self::getDb();
            $db->post('wallet_transactions', $transactionData);
            return true;
        } catch (Exception $e) {
            error_log("Error logging wallet transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process a successfully received payment
     * Parses gross amount, deducts platform commission, updates running wallet and ledger.
     * 
     * @param string $companyId The company handling the parcel
     * @param float $totalAmount Total amount paid by customer
     * @param float $commissionRate The platform commission rate percentage (0-100)
     * @param string $paymentTransactionId ID from payment_transactions
     * @return bool
     */
    public static function processPaymentReceived($companyId, $totalAmount, $commissionRate, $paymentTransactionId = null) {
        if ($totalAmount <= 0) return false;

        $wallet = self::getWallet($companyId);
        if (!$wallet) return false; 

        // Current balances
        $availableBalance = (float)$wallet['available_balance'];
        $totalEarned = (float)$wallet['total_earned'];
        $totalCommissionPaid = (float)$wallet['total_commission_paid'];

        // Calculations
        $commissionAmount = $totalAmount * ($commissionRate / 100);
        $netAmount = $totalAmount - $commissionAmount;

        // 1. Credit the total (gross)
        $runningBalance = $availableBalance + $totalAmount;
        self::logTransaction($companyId, $paymentTransactionId, null, 'payment_credit', $totalAmount, $runningBalance, "Payment received");

        // 2. Debit the commission if applicable
        if ($commissionAmount > 0) {
            $runningBalance -= $commissionAmount;
            self::logTransaction($companyId, $paymentTransactionId, null, 'commission_debit', $commissionAmount, $runningBalance, "Platform commission deduction ({$commissionRate}%)");
        }

        // 3. Update the wallet
        $updateData = [
            'available_balance' => $runningBalance,
            'total_earned' => $totalEarned + $netAmount, // Net earnings tracked as total earned
            'total_commission_paid' => $totalCommissionPaid + $commissionAmount,
            'updated_at' => gmdate('Y-m-d\TH:i:sP')
        ];

        // Wallet balance updates should be driven by database triggers from the wallet_transactions ledger.
        // However, we apply a direct update to ensure immediate consistency in this implementation.
        $db = self::getDb();
        try {
            $db->patch('company_wallets', $updateData, "company_id=eq.{$companyId}");
            return true;
        } catch (Exception $e) {
            error_log("Error updating wallet row after payment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Request a new payout for a company
     * Moves available balance to pending balance.
     * 
     * @param string $companyId
     * @param float $amount
     * @param string $payoutMethod (bank_transfer, mobile_money, etc)
     * @param array $details ['bank_name' => '', 'account_number' => '', 'account_name' => '', 'mobile_number' => '']
     * @param string $userId The user requesting
     * @return array ['success' => bool, 'message' => string]
     */
    public static function requestPayout($companyId, $amount, $payoutMethod, $details = [], $userId = null) {
        if (floatval($amount) <= 0) {
            return ['success' => false, 'message' => 'Invalid payout amount'];
        }

        $wallet = self::getWallet($companyId);
        if (!$wallet) {
            return ['success' => false, 'message' => 'Wallet not found for company'];
        }

        if ((float)$wallet['available_balance'] < $amount) {
            return ['success' => false, 'message' => 'Insufficient available balance'];
        }

        $newAvailable = (float)$wallet['available_balance'] - $amount;
        $newPending = (float)$wallet['pending_balance'] + $amount;

        $payoutData = [
            'company_id' => $companyId,
            'amount' => $amount,
            'payout_method' => $payoutMethod,
            'status' => 'pending',
            'requested_by' => $userId
        ];
        
        if (!empty($details['bank_name'])) $payoutData['bank_name'] = $details['bank_name'];
        if (!empty($details['account_number'])) $payoutData['account_number'] = $details['account_number'];
        if (!empty($details['account_name'])) $payoutData['account_name'] = $details['account_name'];
        if (!empty($details['mobile_number'])) $payoutData['mobile_number'] = $details['mobile_number'];

        try {
            $db = self::getDb();
            // 1. Insert payout
            $payoutResult = $db->post('company_payouts', $payoutData);
            
            $payoutId = null;
            if (is_array($payoutResult) && count($payoutResult) > 0 && isset($payoutResult[0]['id'])) {
                $payoutId = $payoutResult[0]['id'];
            } elseif (is_array($payoutResult) && isset($payoutResult['id'])) {
                $payoutId = $payoutResult['id'];
            }

            // 2. Adjust wallet via ledger and direct row update for consistency
            self::logTransaction($companyId, null, $payoutId, 'payout_debit', floatval($amount), $newAvailable, "Payout requested", $userId);

            try {
                $db->patch('company_wallets', [
                    'available_balance' => $newAvailable,
                    'pending_balance' => $newPending,
                    'updated_at' => gmdate('Y-m-d\TH:i:sP')
                ], "company_id=eq.{$companyId}");
            } catch (Exception $e) {
                error_log("Error updating company wallet after payout request: " . $e->getMessage());
                // not failing user action yet, as ledger entry exists, but log for support.
            }

            return ['success' => true, 'message' => 'Payout requested successfully.'];
        } catch (Exception $e) {
            error_log("Error requesting payout: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error processing payout request'];
        }
    }

    /**
     * Process an existing payout (e.g. approve, complete, or reject/cancel)
     * @param string $payoutId 
     * @param string $companyId
     * @param string $status 'completed', 'failed', 'cancelled', 'approved', 'processing'
     * @param string $userId Admin Processing
     * @param string $notes Optional notes
     * @param string $reference External reference number
     * @return bool
     */
    public static function resolvePayout($payoutId, $companyId, $status, $userId, $notes = "", $reference = "") {
        $validStatuses = ['completed', 'failed', 'cancelled', 'approved', 'processing'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $wallet = self::getWallet($companyId);
        if (!$wallet) return false;

        $db = self::getDb();
        $payoutsResponse = $db->get('company_payouts', "id=eq.{$payoutId}&limit=1");
        
        if (empty($payoutsResponse) || !isset($payoutsResponse[0])) return false;
        $payout = $payoutsResponse[0];

        // Prevent processing twice
        if (in_array($payout['status'], ['completed', 'failed', 'cancelled'])) {
            return false;
        }

        $amount = (float)$payout['amount'];
        $walletUpdateData = [];
        $payoutUpdateData = [
            'status' => $status,
            'notes' => $notes ?: null,
            'external_reference' => $reference ?: null,
            'updated_at' => gmdate('Y-m-d\TH:i:sP')
        ];

        // Processing Logic
        if ($status === 'completed') {
            // Payout success
            $walletUpdateData['pending_balance'] = (float)$wallet['pending_balance'] - $amount;
            $walletUpdateData['total_paid_out'] = (float)$wallet['total_paid_out'] + $amount;
            $walletUpdateData['last_payout_at'] = gmdate('Y-m-d\TH:i:sP');
            
            $payoutUpdateData['completed_at'] = gmdate('Y-m-d\TH:i:sP');
            $payoutUpdateData['completed_by'] = $userId;
        } 
        elseif (in_array($status, ['failed', 'cancelled'])) {
            // Reverse the payout lock
            $newAvailable = (float)$wallet['available_balance'] + $amount;
            $walletUpdateData['pending_balance'] = (float)$wallet['pending_balance'] - $amount;
            $walletUpdateData['available_balance'] = $newAvailable;
            
            // Refund to the wallet ledger
            self::logTransaction($companyId, null, $payoutId, 'adjustment_credit', $amount, $newAvailable, "Payout {$status} - Funds restored", $userId);
        }
        elseif ($status === 'approved') {
            // Intermediate state
            $payoutUpdateData['approved_at'] = gmdate('Y-m-d\TH:i:sP');
            $payoutUpdateData['approved_by'] = $userId;
        }

        try {
            $db->patch('company_payouts', $payoutUpdateData, "id=eq.{$payoutId}");
            
            if (!empty($walletUpdateData)) {
                try {
                    $walletUpdateData['updated_at'] = gmdate('Y-m-d\TH:i:sP');
                    $db->patch('company_wallets', $walletUpdateData, "company_id=eq.{$companyId}");
                } catch (Exception $e) {
                    error_log("Error updating wallet balances during payout resolution: " . $e->getMessage());
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Error resolving payout: " . $e->getMessage());
            return false;
        }
    }
}