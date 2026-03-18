<?php

/**
 * Lenco Payout Service
 *
 * This class encapsulates communication with the Lenco v2 payout API.
 *
 * Supported environment variables (set in your .env):
 * - LENCO_API_BASE (default: https://api.lenco.co/access/v2)
 * - LENCO_API_KEY (preferred) OR LENCO_SECRET_KEY_LIVE / LENCO_SECRET_KEY_SANDBOX
 * - LENCO_ENV (live|sandbox) used to select secret key when LENCO_API_KEY is missing
 * - LENCO_ACCOUNT_ID (required) - the 36-char Lenco account UUID that will be debited
 * - LENCO_PAYOUT_PATH (optional) - override the transfer endpoint path (e.g. "/transfers/mobile-money")
 * - LENCO_MOBILE_OPERATOR (optional) - e.g. "mtn", "airtel", "zamtel", "tnm"
 * - LENCO_MOBILE_COUNTRY (optional) - e.g. "zm" or "mw"
 * - LENCO_BANK_ID (optional) - Lenco bank id (used when sending bank transfers)
 * - LENCO_BANK_COUNTRY (optional) - e.g. "zm"
 */
class LencoPayoutService {

    /**
     * Build the Lenco payout payload without sending it.
     *
     * @param array $payout  Company payout row from the DB (company_payouts)
     * @param array $company Company record (companies)
     * @param string $adminId Admin user performing the payout
     * @return array
     */
    public static function buildPayload(array $payout, array $company, string $adminId): array {
        $payload = [
            'accountId' => getenv('LENCO_ACCOUNT_ID'),
            'amount' => floatval($payout['amount']),
            'reference' => (string)$payout['id'],
            'narration' => 'Company payout for ' . ($company['company_name'] ?? $payout['company_id']),
        ];

        // Override narration if a description is provided
        if (!empty($payout['description'])) {
            $payload['narration'] = $payout['description'];
        }

        $method = strtolower($payout['payout_method'] ?? 'mobile_money');

        if ($method === 'mobile_money') {
            $phone = $payout['mobile_number'] ?? $company['mobile_money_number'] ?? null;
            $operator = getenv('LENCO_MOBILE_OPERATOR') ?: ($payout['operator'] ?? null);
            $country = getenv('LENCO_MOBILE_COUNTRY') ?: ($payout['country'] ?? null);

            if ($phone) {
                $payload['phone'] = $phone;
            }
            if ($operator) {
                $payload['operator'] = $operator;
            }
            if ($country) {
                $payload['country'] = $country;
            }

            // If we already have a transfer recipient id stored, use it
            if (!empty($payout['lenco_transfer_recipient_id'])) {
                $payload['transferRecipientId'] = $payout['lenco_transfer_recipient_id'];
            }

        } else {
            // bank transfer
            if (!empty($payout['lenco_transfer_recipient_id'])) {
                $payload['transferRecipientId'] = $payout['lenco_transfer_recipient_id'];
            } else {
                $bankId = getenv('LENCO_BANK_ID') ?: ($payout['bank_id'] ?? null);
                $country = getenv('LENCO_BANK_COUNTRY') ?: ($payout['country'] ?? null);
                $accountNumber = $payout['account_number'] ?? $company['bank_account_number'] ?? null;

                if ($bankId) {
                    $payload['bankId'] = $bankId;
                }
                if ($country) {
                    $payload['country'] = $country;
                }
                if ($accountNumber) {
                    $payload['accountNumber'] = $accountNumber;
                }
            }
        }

        return $payload;
    }

    /**
     * Send a payout request to Lenco.
     *
     * @param array $payout  Company payout row from the DB (company_payouts)
     * @param array $company Company record (companies)
     * @param string $adminId Admin user performing the payout
     * @return array ['success' => bool, 'message' => string, 'raw' => mixed]
     */
    public static function executePayout(array $payout, array $company, string $adminId): array {
        $baseUrl = getenv('LENCO_API_BASE') ?: 'https://api.lenco.co/access/v2';
        $apiKey = getenv('LENCO_API_KEY') ?: self::getApiKeyFromEnv();

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Lenco payout is not configured (missing LENCO_API_KEY or secret key).'];
        }

        if (empty(getenv('LENCO_ACCOUNT_ID'))) {
            return ['success' => false, 'message' => 'Lenco payout is not configured (missing LENCO_ACCOUNT_ID).'];
        }

        $method = strtolower($payout['payout_method'] ?? 'mobile_money');
        $defaultPath = ($method === 'bank_transfer') ? '/transfers/bank-account' : '/transfers/mobile-money';
        $payoutPath = getenv('LENCO_PAYOUT_PATH') ?: $defaultPath;
        $endpoint = rtrim($baseUrl, '/') . '/' . ltrim($payoutPath, '/');

        $payload = self::buildPayload($payout, $company, $adminId);

        // Validate minimal fields
        if (empty($payload['accountId'])) {
            return ['success' => false, 'message' => 'Missing LENCO_ACCOUNT_ID (source account) for Lenco transfer.'];
        }
        if (empty($payload['amount']) || $payload['amount'] <= 0) {
            return ['success' => false, 'message' => 'Invalid transfer amount.'];
        }

        // Lenco requires a minimum amount (currently 5 ZMW for mobile money transfers)
        $minAmount = floatval(getenv('LENCO_MIN_AMOUNT') ?: 5);
        if ($payload['amount'] < $minAmount) {
            return ['success' => false, 'message' => "Lenco requires a minimum transfer amount of K{$minAmount}."];
        }

        if (empty($payload['reference'])) {
            return ['success' => false, 'message' => 'Missing reference for transfer.'];
        }

        // Validate destination details per transfer type
        if ($method === 'mobile_money') {
            if (empty($payload['phone'])) {
                return ['success' => false, 'message' => 'Mobile money transfer requires phone number (set in payout or company record).'];
            }
            if (empty($payload['operator'])) {
                return ['success' => false, 'message' => 'Mobile money transfer requires LENCO_MOBILE_OPERATOR (e.g., mtn, airtel, zamtel, tnm).'];
            }
        } else {
            // bank transfer
            if (empty($payload['transferRecipientId']) && (empty($payload['bankId']) || empty($payload['accountNumber']))) {
                return ['success' => false, 'message' => 'Bank transfer requires either transferRecipientId or both bankId and accountNumber.'];
            }
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        return self::sendLencoRequest($endpoint, $headers, $payload);
    }

    private static function getApiKeyFromEnv(): ?string {
        $env = strtolower(getenv('LENCO_ENV') ?: 'live');
        if ($env === 'sandbox' || $env === 'test' || $env === 'dev') {
            return getenv('LENCO_SECRET_KEY_SANDBOX') ?: null;
        }
        return getenv('LENCO_SECRET_KEY_LIVE') ?: null;
    }

    /**
     * Fetch the Lenco account UUID (accountId) using the current API key.
     *
     * This is useful when you have an API key but don't know the account ID.
     * It calls GET /accounts and returns the first account's `id`.
     *
     * @return string|null
     */
    public static function fetchAccountId(): ?string {
        $baseUrl = getenv('LENCO_API_BASE') ?: 'https://api.lenco.co/access/v2';
        $apiKey = getenv('LENCO_API_KEY') ?: self::getApiKeyFromEnv();
        if (empty($apiKey)) {
            return null;
        }

        $endpoint = rtrim($baseUrl, '/') . '/accounts';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $http !== 200) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['data']) || !is_array($decoded['data'])) {
            return null;
        }

        $first = reset($decoded['data']);
        if (is_array($first) && !empty($first['id'])) {
            return $first['id'];
        }

        return null;
    }

    private static function sendLencoRequest(string $endpoint, array $headers, array $payload): array {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => 'Failed to contact Lenco: ' . $error, 'http_code' => null];
        }

        if ($http === 404) {
            error_log('Lenco payout 404 Not Found — endpoint may be incorrect. Endpoint: ' . $endpoint);
            error_log('Lenco payout payload: ' . json_encode($payload));
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid response from Lenco', 'raw' => $response, 'http_code' => $http];
        }

        // Lenco returns a top-level status boolean and a message.
        $success = isset($decoded['status']) && filter_var($decoded['status'], FILTER_VALIDATE_BOOLEAN);

        // Some endpoints return a data object containing transfer status + reason.
        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
        $transferStatus = isset($data['status']) ? strtolower($data['status']) : null;

        // Treat as failed if the transfer explicitly failed even when top-level status is true.
        $success = $success && ($transferStatus === null || in_array($transferStatus, ['successful', 'completed', 'pending'], true));

        $message = $decoded['message'] ?? ($data['message'] ?? null) ?? ($success ? 'Payout executed' : 'Payout failed');
        if (!$message && isset($data['reasonForFailure'])) {
            $message = $data['reasonForFailure'];
        }

        // Debug logging for failures
        if (!$success) {
            $debug = getenv('LENCO_DEBUG') ?: false;
            if ($debug) {
                error_log('Lenco request failed: endpoint=' . $endpoint . ' http=' . $http . ' message=' . $message);
                error_log('Lenco request payload: ' . json_encode($payload));
                error_log('Lenco response: ' . json_encode($decoded));
            }
        }

        return ['success' => $success, 'message' => $message, 'raw' => $decoded, 'http_code' => $http];
    }
}

