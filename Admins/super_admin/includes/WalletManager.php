<?php

require_once __DIR__ . '/../api/supabase-client.php';
require_once __DIR__ . '/LencoPayoutService.php';

class WalletManager {
    
    /**
     * Getting wallet for a company
     * @param string $companyId
     * @return array|null 
     */
    public static function getWallet($companyId) {
        try {
            $response = callSupabaseWithServiceKey("company_wallets?company_id=eq.{$companyId}&limit=1", 'GET');
            if (is_array($response) && isset($response[0])) {
                return $response[0];
            }
        } catch (Exception $e) {
            error_log("Error getting wallet for company {$companyId}: " . $e->getMessage());
        }
        return null;
    }

   
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
            callSupabaseWithServiceKey('wallet_transactions', 'POST', $transactionData);
            return true;
        } catch (Exception $e) {
            error_log("Error logging wallet transaction: " . $e->getMessage());
            return false;
        }
    }

   
    private static function getLastLedgerRunningBalance($companyId) {
        try {
            $resp = callSupabaseWithServiceKey("wallet_transactions?company_id=eq.{$companyId}&order=created_at.desc,id.desc&limit=1", 'GET');
            if (is_array($resp) && count($resp) > 0 && isset($resp[0]['running_balance'])) {
                return (float)$resp[0]['running_balance'];
            }
        } catch (Exception $e) {
            error_log("Error fetching last ledger running balance for company {$companyId}: " . $e->getMessage());
        }
        return null;
    }

    /**

     * @param string $companyId 
     * @param float $totalAmount 
     * @param float $commissionRate 
     * @param string $paymentTransactionId 
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

        // Crediting the total (gross)
        $runningBalance = $availableBalance + $totalAmount;
        self::logTransaction($companyId, $paymentTransactionId, null, 'payment_credit', $totalAmount, $runningBalance, "Payment received");

    
        if ($commissionAmount > 0) {
            $runningBalance -= $commissionAmount;
            self::logTransaction($companyId, $paymentTransactionId, null, 'commission_debit', $commissionAmount, $runningBalance, "Platform commission deduction ({$commissionRate}%)");
        }

        
        return true;
    }

    /**
     * 
     * @param string $companyId
     * @param float $amount
     * @param string $payoutMethod 
     * @param array $details 
     * @param string $userId
     * @return array 
     */
    public static function requestPayout($companyId, $amount, $payoutMethod, $details = [], $userId = null) {
        if (floatval($amount) <= 0) {
            return ['success' => false, 'message' => 'Invalid amount'];
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
           
            $payoutResult = callSupabaseWithServiceKey('company_payouts', 'POST', $payoutData, ['Prefer' => 'return=representation']);
            $payoutId = null;
            if (is_array($payoutResult) && count($payoutResult) > 0 && isset($payoutResult[0]['id'])) {
                $payoutId = $payoutResult[0]['id'];
            } elseif (is_array($payoutResult) && isset($payoutResult['id'])) {
                $payoutId = $payoutResult['id'];
            }

            $lastRunning = self::getLastLedgerRunningBalance($companyId);
            $runningBalance = $lastRunning !== null ? ($lastRunning - $amount) : $newAvailable;
            self::logTransaction($companyId, null, $payoutId, 'payout_debit', floatval($amount), $runningBalance, "Payout requested", $userId);

            return ['success' => true, 'message' => 'Payout requested successfully.'];
        } catch (Exception $e) {
            error_log("Error requesting payout: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error processing payout request'];
        }
    }

    /**
     * Processing an existing payout 
     * @param string $payoutId 
     * @param string $companyId
     * @param string $status 
     * @param string $userId 
     * @param string $notes 
     * @param string $reference 
     */
    public static function resolvePayout($payoutId, $companyId, $status, $userId, $notes = "", $reference = "") {
        $validStatuses = ['completed', 'failed', 'cancelled', 'approved', 'processing'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $wallet = self::getWallet($companyId);
        if (!$wallet) return false;

        $payoutsResponse = callSupabaseWithServiceKey("company_payouts?id=eq.{$payoutId}&limit=1", 'GET');
        if (empty($payoutsResponse) || !isset($payoutsResponse[0])) return false;
        $payout = $payoutsResponse[0];

        // Preventing processing twice
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
        $payoutFailed = false;

        if ($status === 'completed') {

            try {

                $companyResponse = callSupabaseWithServiceKey("companies?id=eq.{$companyId}&limit=1", 'GET');
                $company = is_array($companyResponse) && isset($companyResponse[0]) ? $companyResponse[0] : [];

                $payoutResult = LencoPayoutService::executePayout($payout, $company, $userId);

                $duplicateReference = false;
                if (!$payoutResult['success']) {
                    $msg = strtolower($payoutResult['message'] ?? '');
                    if (strpos($msg, 'duplicate reference') !== false
                        || (!empty($payoutResult['raw']['message']) && stripos($payoutResult['raw']['message'], 'duplicate reference') !== false)
                        || (!empty($payoutResult['raw']['errorCode']) && $payoutResult['raw']['errorCode'] === '02')
                    ) {
                        $duplicateReference = true;
                    }
                }

                if (!$payoutResult['success'] && !$duplicateReference) {
                 
                    $payoutFailed = true;
                    $status = 'failed';
                    $payoutUpdateData['status'] = 'failed';

                    $reasonParts = [];
                    if (!empty($payoutResult['message'])) {
                        $reasonParts[] = $payoutResult['message'];
                    }
                    if (!empty($payoutResult['http_code'])) {
                        $reasonParts[] = "HTTP {$payoutResult['http_code']}";
                    }

                    if (!empty($payoutResult['raw']) && is_array($payoutResult['raw'])) {
                        $raw = $payoutResult['raw'];

                        if (!empty($raw['error'])) {
                            $reasonParts[] = "error=" . (is_string($raw['error']) ? $raw['error'] : json_encode($raw['error']));
                        }
                        if (!empty($raw['message'])) {
                            $reasonParts[] = "message=" . $raw['message'];
                        }

                        if (!empty($raw['data']) && is_array($raw['data'])) {
                            if (!empty($raw['data']['reasonForFailure'])) {
                                $reasonParts[] = "reason=" . $raw['data']['reasonForFailure'];
                            }
                            if (!empty($raw['data']['status'])) {
                                $reasonParts[] = "status=" . $raw['data']['status'];
                            }
                        }

                        if (isset($raw['code'])) {
                            $reasonParts[] = "code=" . $raw['code'];
                        }
                    }

                    $reason = implode(' | ', array_filter($reasonParts));
                    if (stripos($reason, 'not found') !== false && strpos($reason, 'endpoint') === false) {
                        $reason .= ' (verify LENCO_API_BASE/LENCO_PAYOUT_PATH and payload fields match Lenco API)';
                    }

                    $payoutUpdateData['failure_reason'] = $reason ?: 'Lenco payout failed';
                    $payoutUpdateData['updated_at'] = gmdate('Y-m-d\TH:i:sP');
                }
                if ($duplicateReference) {
                    $payoutFailed = false;
                    $status = 'completed';
                    $payoutUpdateData['status'] = 'completed';
                    $payoutUpdateData['failure_reason'] = null;
                    $payoutUpdateData['updated_at'] = gmdate('Y-m-d\TH:i:sP');
                }

                
                if (!$payoutFailed) {
                    $walletUpdateData['pending_balance'] = max(0, (float)$wallet['pending_balance'] - $amount);
                    $walletUpdateData['total_paid_out'] = (float)$wallet['total_paid_out'] + $amount;
                    $walletUpdateData['last_payout_at'] = gmdate('Y-m-d\TH:i:sP');

                    $payoutUpdateData['completed_at'] = gmdate('Y-m-d\TH:i:sP');
                    $payoutUpdateData['completed_by'] = $userId;

                    $rawData = $payoutResult['raw']['data'] ?? $payoutResult['raw'];
                    $payoutUpdateData['external_reference'] = $rawData['lencoReference'] ?? $rawData['id'] ?? $rawData['reference'] ?? $reference;

                   
                    $existingLedger = callSupabaseWithServiceKey(
                        "wallet_transactions?company_id=eq.{$companyId}&payout_id=eq.{$payoutId}&transaction_type=eq.payout_debit",
                        'GET'
                    );
                    $existingLedgerData = is_array($existingLedger) ? $existingLedger : (is_object($existingLedger) && isset($existingLedger->data) ? $existingLedger->data : []);
                    if (empty($existingLedgerData)) {
                        $lastRunning = self::getLastLedgerRunningBalance($companyId);
                        $runningBalance = $lastRunning !== null ? ($lastRunning - $amount) : (float)$wallet['available_balance'];
                        self::logTransaction(
                            $companyId,
                            null,
                            $payoutId,
                            'payout_debit',
                            $amount,
                            $runningBalance,
                            'Payout completed',
                            $userId
                        );
                    }

                    $lastRunningAfter = self::getLastLedgerRunningBalance($companyId);
                    if ($lastRunningAfter !== null) {
                        $walletUpdateData['available_balance'] = $lastRunningAfter;
                    }
                }
            } catch (Exception $e) {
                error_log('Lenco payout error: ' . $e->getMessage());
                $payoutUpdateData['status'] = 'failed';
                $payoutUpdateData['failure_reason'] = 'Lenco payout error: ' . $e->getMessage();
                $payoutUpdateData['updated_at'] = gmdate('Y-m-d\TH:i:sP');
                callSupabaseWithServiceKey("company_payouts?id=eq.{$payoutId}", 'PATCH', $payoutUpdateData);
                return false;
            }
        }
        if ($payoutFailed || in_array($status, ['failed', 'cancelled'])) {
            $walletUpdateData['pending_balance'] = max(0, (float)$wallet['pending_balance'] - $amount);

            // Refund to the wallet ledger
            $lastRunning = self::getLastLedgerRunningBalance($companyId);
            $runningBalance = $lastRunning !== null ? ($lastRunning + $amount) : ((float)$wallet['available_balance'] + $amount);
            self::logTransaction($companyId, null, $payoutId, 'adjustment_credit', $amount, $runningBalance, "Payout {$status} - Funds restored", $userId);

         
            $lastRunningAfter = self::getLastLedgerRunningBalance($companyId);
            if ($lastRunningAfter !== null) {
                $walletUpdateData['available_balance'] = $lastRunningAfter;
            } else {
                $walletUpdateData['available_balance'] = (float)$wallet['available_balance'] + $amount;
            }
        }
        elseif ($status === 'approved') {
        
            $payoutUpdateData['approved_at'] = gmdate('Y-m-d\TH:i:sP');
            $payoutUpdateData['approved_by'] = $userId;
        }

        try {
            try {
                callSupabaseWithServiceKey("company_payouts?id=eq.{$payoutId}", 'PATCH', $payoutUpdateData);
            } catch (Exception $e) {
                $msg = $e->getMessage();
              
                if (strpos($msg, 'P0001') !== false || stripos($msg, 'Invalid payout status transition') !== false) {
                  
                    $refetch = callSupabaseWithServiceKey("company_payouts?id=eq.{$payoutId}&limit=1", 'GET');
                    $current = is_array($refetch) && isset($refetch[0]) ? $refetch[0] : null;
                    $currentStatus = $current['status'] ?? null;

                    if ($currentStatus === 'completed') {
                  
                        if (!empty($walletUpdateData)) {
                            callSupabaseWithServiceKey("company_wallets?id=eq.{$companyId}", 'PATCH', $walletUpdateData);
                        }
                        return true;
                    }

                    
                    $attemptData = [];
                    if ($currentStatus === 'pending') {
                        $attemptData = [
                            'status' => 'approved',
                            'approved_at' => gmdate('Y-m-d\TH:i:sP'),
                            'approved_by' => $userId,
                            'updated_at' => gmdate('Y-m-d\TH:i:sP'),
                        ];
                        try {
                            callSupabaseWithServiceKey("company_payouts?id=eq.{$payoutId}", 'PATCH', $attemptData);
                        } catch (Exception $e2) {
                      
                            error_log("Payout status transition retry failed (pending->approved): " . $e2->getMessage());
                            throw $e;
                        }
                    }

                    try {
                        callSupabaseWithServiceKey("company_payouts?id=eq.{$payoutId}", 'PATCH', $payoutUpdateData);
                    } catch (Exception $e2) {
                        error_log("Payout status transition retry failed (approved->completed): " . $e2->getMessage());
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }

            if (!empty($walletUpdateData)) {
              
                callSupabaseWithServiceKey("company_wallets?id=eq.{$companyId}", 'PATCH', $walletUpdateData);
            }

            return true;
        } catch (Exception $e) {
            error_log("Error resolving payout: " . $e->getMessage());
            return false;
        }
    }
}