<?php

class OutletAwareSupabaseHelper {
    private $supabaseUrl;
    private $supabaseKey;
    private $tablePrefix;

    public function __construct() {
        
        $this->supabaseUrl = getenv('SUPABASE_URL') ?: 'https://xerpchdsykqafrsxbqef.supabase.co';
        $this->supabaseKey = getenv('SUPABASE_SERVICE_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
        $this->tablePrefix = getenv('SUPABASE_TABLE_PREFIX') ?: '';
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
}
