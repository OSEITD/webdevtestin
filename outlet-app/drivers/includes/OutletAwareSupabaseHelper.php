<?php

class OutletAwareSupabaseHelper {
    private $supabaseUrl;
    private $supabaseKey;
    private $tablePrefix;

    public function __construct() {
        // Load .env if not already loaded
        if (!class_exists('EnvLoader')) {
            require_once __DIR__ . '/../../includes/env.php';
        }
        EnvLoader::load();

        $this->supabaseUrl = getenv('SUPABASE_URL');
        $this->supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY');
        $this->tablePrefix = getenv('SUPABASE_TABLE_PREFIX') ?: '';

        if (empty($this->supabaseUrl) || empty($this->supabaseKey)) {
            throw new RuntimeException(
                'Supabase credentials are not configured. ' .
                'Please set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in outlet-app/.env'
            );
        }
    }

    
    public function insert($table, $data) {
        $url = rtrim($this->supabaseUrl, '/') . "/rest/v1/" . $this->tablePrefix . $table;
        $headers = [
            "apikey: {$this->supabaseKey}",
            "Authorization: Bearer {$this->supabaseKey}",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ];
        $payload = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 201 || $httpCode === 200) {
            $json = json_decode($result, true);
            return $json ? $json[0] : true;
        }
        return false;
    }

    
    public function update($table, $id, $data) {
        $url = rtrim($this->supabaseUrl, '/') . "/rest/v1/" . $this->tablePrefix . $table . "?id=eq." . urlencode($id);
        $headers = [
            "apikey: {$this->supabaseKey}",
            "Authorization: Bearer {$this->supabaseKey}",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ];
        $payload = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $json = json_decode($result, true);
            return $json ? $json[0] : true;
        }
        return false;
    }

    
    public function select($table, $filters = []) {
        $url = rtrim($this->supabaseUrl, '/') . "/rest/v1/" . $this->tablePrefix . $table;
        if (!empty($filters)) {
            $query = [];
            foreach ($filters as $col => $val) {
                $query[] = urlencode($col) . "=eq." . urlencode($val);
            }
            $url .= '?' . implode('&', $query);
        }
        $headers = [
            "apikey: {$this->supabaseKey}",
            "Authorization: Bearer {$this->supabaseKey}",
            "Content-Type: application/json"
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            return json_decode($result, true);
        }
        return false;
    }

    public function getUrl(): string { return $this->supabaseUrl; }
    public function getKey(): string { return $this->supabaseKey; }
}
