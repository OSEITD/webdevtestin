<?php


class LencoPayoutService {

    /**
     * 
     *
     * @param array $payout  
     * @param array $company
     * @param string $adminId 
     * @return array
     */
    public static function buildPayload(array $payout, array $company, string $adminId): array {
        $payload = [
            'accountId' => getenv('LENCO_ACCOUNT_ID'),
            'amount' => floatval($payout['amount']),
            'reference' => (string)$payout['id'],
            'narration' => 'Company payout for ' . ($company['company_name'] ?? $payout['company_id']),
        ];

        if (!empty($payout['description'])) {
            $payload['narration'] = $payout['description'];
        }

        $method = strtolower($payout['payout_method'] ?? 'mobile_money');

        if ($method === 'mobile_money') {
            $phone = $payout['mobile_number'] ?? $company['mobile_money_number'] ?? null;
            $operator = getenv('LENCO_MOBILE_OPERATOR') ?: ($payout['operator'] ?? null);
            $country = getenv('LENCO_MOBILE_COUNTRY') ?: ($payout['country'] ?? null);

          
            $operator = is_string($operator) ? strtolower(trim($operator)) : null;
            $country = is_string($country) ? strtolower(trim($country)) : null;
            $phone = self::normalizePhoneForCountry(is_string($phone) ? trim($phone) : null, $country);

            if (empty($operator) && $phone) {
                $operator = self::guessMobileOperator($phone);
            }

            if ($phone) {
                $payload['phone'] = $phone;
            }
            if ($operator) {
                $payload['operator'] = $operator;
            }
            if ($country) {
                $payload['country'] = $country;
            }

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
     * Sending a payout request to Lenco.
     *
     * @param array $payout  
     * @param array $company 
     * @param string $adminId 
     * @return array 
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

        // Validating minimal fields
        if (empty($payload['accountId'])) {
            return ['success' => false, 'message' => 'Missing LENCO_ACCOUNT_ID (source account) for Lenco transfer.'];
        }
        if (empty($payload['amount']) || $payload['amount'] <= 0) {
            return ['success' => false, 'message' => 'Invalid transfer amount.'];
        }
        $minAmount = floatval(getenv('LENCO_MIN_AMOUNT') ?: 5);
        if ($payload['amount'] < $minAmount) {
            return ['success' => false, 'message' => "Lenco requires a minimum transfer amount of K{$minAmount}."];
        }

        if (empty($payload['reference'])) {
            return ['success' => false, 'message' => 'Missing reference for transfer.'];
        }

        // Validating destination details per transfer type
        if ($method === 'mobile_money') {
            if (empty($payload['phone'])) {
                return ['success' => false, 'message' => 'Mobile money transfer requires phone number (set in payout or company record).'];
            }
            if (empty($payload['operator'])) {
                return ['success' => false, 'message' => 'Mobile money transfer requires LENCO_MOBILE_OPERATOR (e.g., mtn, airtel, zamtel, tnm).'];
            }

           
            if (empty($payload['transferRecipientId'])) {
          
                $operatorsToTry = [];

                if (!empty($payload['operator'])) {
                    $operatorsToTry[] = strtolower($payload['operator']);
                }

                $guessed = self::guessMobileOperator($payload['phone']);
                if ($guessed && !in_array($guessed, $operatorsToTry, true)) {
                    $operatorsToTry[] = $guessed;
                }

                
                foreach (['mtn', 'airtel', 'zamtel'] as $op) {
                    if (!in_array($op, $operatorsToTry, true)) {
                        $operatorsToTry[] = $op;
                    }
                }

                $resolveResult = null;
                $lastResolve = null;

                foreach ($operatorsToTry as $op) {
                    $payload['operator'] = $op;
                    $lastResolve = self::resolveMobileMoneyRecipient($payload['phone'], $payload['operator'], $payload['country'] ?? 'zm');

                    if (!empty($lastResolve['success'])) {
                        $resolveResult = $lastResolve;
                        break;
                    }

                    
                    $errorCode = $lastResolve['raw']['errorCode'] ?? null;
                    $msg = strtolower($lastResolve['message'] ?? '');
                    if ($errorCode !== '05' && strpos($msg, 'account details') === false) {
                        return $lastResolve;
                    }
                }

                if (empty($resolveResult)) {
                    
                    return $lastResolve ?: ['success' => false, 'message' => 'Failed to resolve mobile money recipient'];
                }

                if (!empty($resolveResult['transferRecipientId'])) {
                    $payload['transferRecipientId'] = $resolveResult['transferRecipientId'];
                }
            }
        } else {
           
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

    private static function resolveMobileMoneyRecipient(string $phone, string $operator, string $country = 'zm'): array {
        $baseUrl = getenv('LENCO_API_BASE') ?: 'https://api.lenco.co/access/v2';
        $apiKey = getenv('LENCO_API_KEY') ?: self::getApiKeyFromEnv();
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Lenco payout is not configured (missing LENCO_API_KEY or secret key).'];
        }

        $endpoint = rtrim($baseUrl, '/') . '/resolve/mobile-money';
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        $payload = [
            'phone' => $phone,
            'operator' => strtolower($operator),
            'country' => strtolower($country),
        ];

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

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid response from Lenco', 'raw' => $response, 'http_code' => $http];
        }

        $success = isset($decoded['status']) && filter_var($decoded['status'], FILTER_VALIDATE_BOOLEAN);
        $message = $decoded['message'] ?? null;

        if (!$success) {
            return ['success' => false, 'message' => $message ?? 'Failed to resolve mobile money recipient', 'raw' => $decoded, 'http_code' => $http];
        }

       
        $transferRecipientId = null;
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $data = $decoded['data'];
            if (!empty($data['transferRecipientId'])) {
                $transferRecipientId = $data['transferRecipientId'];
            } elseif (!empty($data['recipientId'])) {
                $transferRecipientId = $data['recipientId'];
            }
        }

        return [
            'success' => true,
            'message' => 'Recipient resolved',
            'transferRecipientId' => $transferRecipientId,
            'raw' => $decoded,
        ];
    }

    /**
     * Normalize a phone number for the specified country.
     *
     * Lenco expects mobile-money phone numbers in a consistent format (usually E.164).
     * This ensures we send numbers like 260771234567 rather than 0771234567.
     *
     * @param string|null $phone
     * @param string|null $country
     * @return string|null
     */
    private static function normalizePhoneForCountry(?string $phone, ?string $country): ?string {
        if (empty($phone)) {
            return null;
        }

        // Strip non-digit characters
        $digits = preg_replace('/\D+/', '', $phone);
        if (empty($digits)) {
            return null;
        }

        $country = is_string($country) ? strtolower(trim($country)) : null;

        // Normalize for Zambia (common for this project)
        if ($country === 'zm' || $country === 'zambia') {
            // Drop leading zero if present (local format: 07xxxxxxxx)
            if (strpos($digits, '0') === 0) {
                $digits = substr($digits, 1);
            }
            // Ensure country code is present
            if (strpos($digits, '260') !== 0) {
                $digits = '260' . $digits;
            }
        }

        // Add other country normalization rules here as needed.

        return $digits;
    }

    /**
     * Guess the mobile money operator from the phone number prefix.
     *
     * This is useful when the operator isn't explicitly stored in the user/company record.
     *
     * @param string|null $phone
     * @return string|null
     */
    private static function guessMobileOperator(?string $phone): ?string {
        if (empty($phone)) {
            return null;
        }

        // Clean to digits only
        $digits = preg_replace('/\D+/', '', $phone);
        if (empty($digits)) {
            return null;
        }

        // Remove leading zero or country code prefix for Zambia (260)
        if (strpos($digits, '0') === 0) {
            $digits = substr($digits, 1);
        }
        if (strpos($digits, '260') === 0) {
            $digits = substr($digits, 3);
        }

        $prefix = substr($digits, 0, 2);
        if (in_array($prefix, ['96', '76'], true)) {
            return 'mtn';
        }
        if (in_array($prefix, ['97', '77'], true)) {
            return 'airtel';
        }
        if (in_array($prefix, ['95', '55'], true)) {
            return 'zamtel';
        }

        return null;
    }

    /**
     *
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

        $success = isset($decoded['status']) && filter_var($decoded['status'], FILTER_VALIDATE_BOOLEAN);
        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
        $transferStatus = isset($data['status']) ? strtolower($data['status']) : null;

        $success = $success && ($transferStatus === null || in_array($transferStatus, ['successful', 'completed', 'pending'], true));

        $message = $decoded['message'] ?? ($data['message'] ?? null) ?? ($success ? 'Payout executed' : 'Payout failed');
        if (!$message && isset($data['reasonForFailure'])) {
            $message = $data['reasonForFailure'];
        }

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

