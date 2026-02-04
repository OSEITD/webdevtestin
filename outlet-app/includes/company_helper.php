<?php
function getCompanyInfo($companyId, $supabaseUrl, $accessToken) {
    if (!$companyId) {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                "apikey: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E",
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ]
        ]
    ]);

    $companyUrl = "$supabaseUrl/rest/v1/companies?select=id,company_name,subdomain,contact_person,contact_email,status,address&id=eq.$companyId";
    $companyData = @file_get_contents($companyUrl, false, $context);

    if ($companyData === false) {
        
        error_log("Failed to fetch company info for $companyId from Supabase.");
        return null;
    }

    $companies = json_decode($companyData, true);
    if (is_array($companies) && count($companies) > 0) {
        return $companies[0];
    }

    return null;
}

function formatSubdomainDisplay($subdomain, $companyName = null) {
    if (!$subdomain) {
        return $companyName ?: 'Company';
    }

    $formatted = ucwords(str_replace(['_', '-', '.'], ' ', $subdomain));

    return $formatted;
}

function getCompanyBrandingColors($companyInfo) {
    $colors = [
        'primary' => '#2563eb',
        'secondary' => '#1e40af',
        'accent' => '#3b82f6',
        'text' => '#1f2937',
        'background' => '#f8fafc',
        'sidebarBg' => '#1f2937',
        'sidebarText' => '#f9fafb',
        'sidebarHover' => '#374151',
        'sidebarActive' => '#2563eb'
    ];

    if ($companyInfo && isset($companyInfo['subdomain'])) {
        $subdomain = $companyInfo['subdomain'];

        switch (strtolower($subdomain)) {
            case 'premium':
                $colors['primary'] = '#7c3aed';
                $colors['secondary'] = '#5b21b6';
                $colors['accent'] = '#8b5cf6';
                break;
            case 'express':
                $colors['primary'] = '#dc2626';
                $colors['secondary'] = '#b91c1c';
                $colors['accent'] = '#ef4444';
                break;
            case 'logistics':
                $colors['primary'] = '#059669';
                $colors['secondary'] = '#047857';
                $colors['accent'] = '#10b981';
                break;
            default:
                break;
        }
    }

    return $colors;
}
