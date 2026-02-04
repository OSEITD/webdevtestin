<?php

function getBaseUrl() {
    require_once __DIR__ . '/env.php';
    EnvLoader::load();
    
    $baseUrl = EnvLoader::get('BASE_URL');
    
    if (!$baseUrl) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . $host;
    }
    
    return rtrim($baseUrl, '/');
}

function getAppUrl($path = '') {
    $baseUrl = getBaseUrl();
    $path = ltrim($path, '/');
    return $baseUrl . ($path ? '/' . $path : '');
}

function getDriverUrl($path = '') {
    return getAppUrl('drivers/' . ltrim($path, '/'));
}

function getPageUrl($path = '') {
    return getAppUrl('pages/' . ltrim($path, '/'));
}

function getCustomerUrl($path = '') {
    $baseUrl = getBaseUrl();
    $baseUrl = str_replace('outlet.', 'customer.', $baseUrl);
    $path = ltrim($path, '/');
    return $baseUrl . ($path ? '/' . $path : '');
}
