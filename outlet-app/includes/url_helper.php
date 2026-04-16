<?php

function getBaseUrl() {
    require_once __DIR__ . '/env.php';
    EnvLoader::load();

    $baseUrl = EnvLoader::get('BASE_URL');

    if (!$baseUrl) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
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
    require_once __DIR__ . '/env.php';
    EnvLoader::load();

    if (EnvLoader::has('CUSTOMER_BASE_URL')) {
        $baseUrl = EnvLoader::get('CUSTOMER_BASE_URL');
    } else {
        $baseUrl = getBaseUrl();
        $parsedUrl = parse_url($baseUrl);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? '';

        if (stripos($host, 'outlet.') === 0) {
            $host = 'customer.' . substr($host, 7);
            $baseUrl = $scheme . '://' . $host;
        }
    }

    $path = ltrim($path, '/');
    return rtrim($baseUrl, '/') . ($path ? '/' . $path : '');
}
