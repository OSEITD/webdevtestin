<?php

class SMSService {
    private $enabled = false;
    private $provider = 'clickatell'; 
    private $config = [];
    private $logFile;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/../logs/sms_log.txt';
        $this->loadConfig();
    }
    
    
    private function loadConfig() {
        
        $this->config = [
            'enabled' => false, 
            'provider' => 'clickatell', 
            
            
            'clickatell' => [
                'api_key' => '', 
                'sender_id' => 'ACME', 
                'api_url' => 'https://platform.clickatell.com/messages'
            ],
            
            
            'africastalking' => [
                'api_key' => '', 
                'username' => '', 
                'sender_id' => 'ACME',
                'api_url' => 'https://api.africastalking.com/version1/messaging'
            ],
            
            
            'twilio' => [
                'account_sid' => '', 
                'auth_token' => '', 
                'from_number' => '', 
                'api_url' => 'https://api.twilio.com/2010-04-01/Accounts'
            ],
            
            
            'custom' => [
                'api_key' => '',
                'api_url' => '',
                'method' => 'POST',
                'headers' => array(),
                'sender_id' => 'ACME'
            ]
        ];
        
        $this->enabled = $this->config['enabled'];
        $this->provider = $this->config['provider'];
    }
    
    
    public function sendSMS($to, $message) {
        
        $this->log("Attempting to send SMS to {$to}");
        
        if (!$this->enabled) {
            $this->log("SMS service is DISABLED - Message would be: {$message}");
            return [
                'success' => true,
                'message' => 'SMS service disabled (queued for future sending)',
                'queued' => true
            ];
        }
        
        
        $to = $this->normalizePhoneNumber($to);
        if (!$to) {
            $this->log("Invalid phone number format");
            return ['success' => false, 'error' => 'Invalid phone number'];
        }
        
        
        try {
            switch ($this->provider) {
                case 'clickatell':
                    return $this->sendViaClickatell($to, $message);
                case 'africastalking':
                    return $this->sendViaAfricasTalking($to, $message);
                case 'twilio':
                    return $this->sendViaTwilio($to, $message);
                case 'custom':
                    return $this->sendViaCustomAPI($to, $message);
                default:
                    throw new Exception("Unknown SMS provider: {$this->provider}");
            }
        } catch (Exception $e) {
            $this->log("SMS Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    
    private function normalizePhoneNumber($phone) {
        
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        
        $phone = ltrim($phone, '0');
        
        
        if (!preg_match('/^260/', $phone)) {
            $phone = '260' . $phone;
        }
        
        
        if (preg_match('/^260[79][0-9]{8}$/', $phone)) {
            return $phone;
        }
        
        return null;
    }
    
    
    private function sendViaClickatell($to, $message) {
        $config = $this->config['clickatell'];
        
        $data = [
            'messages' => [[
                'to' => [$to],
                'text' => $message,
                'from' => $config['sender_id']
            ]]
        ];
        
        $ch = curl_init($config['api_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $config['api_key'],
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        $result = json_decode($response, true);
        $this->log("Clickatell Response: " . $response);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'response' => $result];
        } else {
            throw new Exception("Clickatell API error: {$response}");
        }
    }
    
    
    private function sendViaAfricasTalking($to, $message) {
        $config = $this->config['africastalking'];
        
        $data = http_build_query([
            'username' => $config['username'],
            'to' => $to,
            'message' => $message,
            'from' => $config['sender_id']
        ]);
        
        $ch = curl_init($config['api_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                'apiKey: ' . $config['api_key'],
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        $result = json_decode($response, true);
        $this->log("Africa's Talking Response: " . $response);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'response' => $result];
        } else {
            throw new Exception("Africa's Talking API error: {$response}");
        }
    }
    
    
    private function sendViaTwilio($to, $message) {
        $config = $this->config['twilio'];
        
        $url = $config['api_url'] . '/' . $config['account_sid'] . '/Messages.json';
        
        $data = http_build_query([
            'To' => '+' . $to,
            'From' => $config['from_number'],
            'Body' => $message
        ]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_USERPWD => $config['account_sid'] . ':' . $config['auth_token'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        $result = json_decode($response, true);
        $this->log("Twilio Response: " . $response);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'response' => $result];
        } else {
            throw new Exception("Twilio API error: {$response}");
        }
    }
    
    
    private function sendViaCustomAPI($to, $message) {
        $config = $this->config['custom'];
        
        
        $data = json_encode([
            'to' => $to,
            'message' => $message,
            'from' => $config['sender_id']
        ]);
        
        $ch = curl_init($config['api_url']);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $config['method'],
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'Authorization: Bearer ' . $config['api_key']
            ], $config['headers']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        $this->log("Custom API Response: " . $response);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'response' => json_decode($response, true)];
        } else {
            throw new Exception("Custom API error: {$response}");
        }
    }
    
    
    public function notifySender($senderPhone, $trackingNumber, $receiverName) {
        $message = "Your parcel to {$receiverName} has been registered successfully. " .
                   "Tracking Number: {$trackingNumber}. " .
                   "Track at: " . $this->getTrackingUrl($trackingNumber);
        
        return $this->sendSMS($senderPhone, $message);
    }
    
    
    public function notifyReceiver($receiverPhone, $trackingNumber, $senderName) {
        $message = "A parcel from {$senderName} is on its way to you! " .
                   "Tracking Number: {$trackingNumber}. " .
                   "Track at: " . $this->getTrackingUrl($trackingNumber);
        
        return $this->sendSMS($receiverPhone, $message);
    }
    
    
    public function notifyDelivery($phone, $trackingNumber, $deliveryTime) {
        $message = "Your parcel ({$trackingNumber}) has been delivered successfully at {$deliveryTime}. " .
                   "Thank you for using our service!";
        
        return $this->sendSMS($phone, $message);
    }
    
    
    public function notifyStatusChange($phone, $trackingNumber, $status, $location = '') {
        $statusMessages = [
            'at_outlet' => 'is at the outlet ready for dispatch',
            'in_transit' => 'is in transit' . ($location ? " at {$location}" : ''),
            'out_for_delivery' => 'is out for delivery',
            'arrived' => 'has arrived at the destination outlet'
        ];
        
        $statusText = $statusMessages[$status] ?? $status;
        $message = "Parcel Update: Your parcel ({$trackingNumber}) {$statusText}. " .
                   "Track: " . $this->getTrackingUrl($trackingNumber);
        
        return $this->sendSMS($phone, $message);
    }
    
    
    private function getTrackingUrl($trackingNumber) {
        
        $domain = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        return "{$protocol}://{$domain}/customer-app/track_parcel.php?track=" . urlencode($trackingNumber);
    }
    
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        
        $logsDir = dirname($this->logFile);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    
    public function isEnabled() {
        return $this->enabled;
    }
    
    
    public function getStatus() {
        return [
            'enabled' => $this->enabled,
            'provider' => $this->provider,
            'configured' => !empty($this->config[$this->provider]['api_key'])
        ];
    }
}
