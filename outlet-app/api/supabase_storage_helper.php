<?php

class SupabaseStorageHelper {
    private $supabaseUrl = "https://xerpchdsykqafrsxbqef.supabase.co";
    private $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ";
    private $bucket = "parcel_photos";

    public function upload($bucket, $fileName, $tmpName) {
        
        if ($bucket === 'parcel_photos') {
            $bucket = 'parcel-photos';
        }
        
        $url = $this->supabaseUrl . "/storage/v1/object/$bucket/$fileName";
        $fileData = file_get_contents($tmpName);
        
        
        $mimeType = mime_content_type($tmpName);
        
        
        if (!$mimeType || $mimeType === 'application/octet-stream') {
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $mimeType = 'image/jpeg';
                    break;
                case 'png':
                    $mimeType = 'image/png';
                    break;
                case 'gif':
                    $mimeType = 'image/gif';
                    break;
                case 'webp':
                    $mimeType = 'image/webp';
                    break;
                default:
                    $mimeType = 'image/jpeg'; 
            }
        }
        
        error_log("Uploading file: $fileName with mime type: $mimeType");
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $fileData,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->supabaseKey}",
                "apikey: {$this->supabaseKey}",
                "Content-Type: $mimeType"
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            $publicUrl = $this->supabaseUrl . "/storage/v1/object/public/$bucket/$fileName";
            return ['success' => true, 'publicUrl' => $publicUrl];
        } else {
            error_log("Supabase upload failed: HTTP $httpCode - Response: $response");
            return ['success' => false, 'error' => "HTTP $httpCode: $response"];
        }
    }
}
?>
