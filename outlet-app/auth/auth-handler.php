<?php

class AuthHandler {
    private $supabaseUrl;
    private $supabaseKey;
    
    public function __construct() {
        
        $this->supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
        $this->supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';
    }
    
    public function authenticate($email, $password) {
        $host = $_SERVER['HTTP_HOST'];
        $subdomain = explode('.', $host)[0];

        
        $authUrl = $this->supabaseUrl . "/auth/v1/token?grant_type=password";

        $payload = json_encode([
            'email' => $email,
            'password' => $password
        ]);

        
        $ch = curl_init($authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: {$this->supabaseKey}",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $authData = json_decode($response, true);

        if (isset($authData['error'])) {
            return ['success' => false, 'error' => $authData['error_description'] ?? 'Login failed'];
        }

        $accessToken = $authData['access_token'];
        $userId = $authData['user']['id'] ?? $authData['user_id'] ?? null;

        if (!$userId) {
            return ['success' => false, 'error' => 'Login succeeded, but user ID was not returned.'];
        }

        
        $profileUrl = $this->supabaseUrl . "/rest/v1/profiles?select=*&id=eq.$userId";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "apikey: {$this->supabaseKey}\r\nAuthorization: Bearer $accessToken\r\n"
            ]
        ]);

        $profileData = file_get_contents($profileUrl, false, $context);
        $profiles = json_decode($profileData, true);

        if (empty($profiles)) {
            return ['success' => false, 'error' => 'Profile not found.'];
        }

        $profile = $profiles[0];
        $companyId = $profile['company_id'];

        
        $companyData = file_get_contents($this->supabaseUrl . "/rest/v1/companies?id=eq.$companyId", false, $context);
        $company = json_decode($companyData, true)[0] ?? null;

        if (!$company || !isset($company['subdomain']) || $company['subdomain'] !== $subdomain) {
            return ['success' => false, 'error' => 'Access denied: Subdomain mismatch with company.'];
        }

        
        $userData = [
            'user_id' => $userId,
            'email' => $email,
            'role' => $profile['role'],
            'company_id' => $companyId,
            'company_name' => $company['name'],
            'access_token' => $accessToken,
            'refresh_token' => $authData['refresh_token'] ?? null,
            'outlet_id' => $profile['outlet_id'] ?? null
        ];

        return ['success' => true, 'user' => $userData];
    }
    
    public function getRedirectUrl($role) {
        switch ($role) {
            case 'outlet_manager':
                return 'outlet_dashboard.php';
            case 'driver':
                return 'outlet-app/drivers/Pages/driver.php';
            case 'outlet_admin':
                return 'outlet_admin_dashboard.php';
            default:
                return 'dashboard.php';
        }
    }
}
?>
