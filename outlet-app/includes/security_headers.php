<?php

class SecurityHeaders {
    public static function apply() {
        
        header("X-Frame-Options: DENY");
        
        
        header("X-Content-Type-Options: nosniff");
        
        
        header("X-XSS-Protection: 1; mode=block");
        
        
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        
        $supabaseUrl = getenv('SUPABASE_URL') ?: 'https://xerpchdsykqafrsxbqef.supabase.co';\n        header(\"Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; connect-src 'self' $supabaseUrl;\");
        
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
        
        
        header("Permissions-Policy: geolocation=(self), camera=(), microphone=()");
    }
}