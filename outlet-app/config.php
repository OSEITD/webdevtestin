<?php

// Load .env so getenv() works when this file is required standalone
if (!class_exists('EnvLoader')) {
    $envLoaderPath = __DIR__ . '/includes/env.php';
    if (file_exists($envLoaderPath)) {
        require_once $envLoaderPath;
        EnvLoader::load();
    }
}

return [
    'supabase' => [
        'url'              => getenv('SUPABASE_URL') ?: '',
        'key'              => getenv('SUPABASE_ANON_KEY') ?: '',
        'service_role_key' => getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY') ?: '',
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'parcel_system',
        'user' => 'root',
        'pass' => '',
    ],
    'app' => [
        'name' => 'WD Parcel System',
        'url' => 'http://localhost',
        'session_timeout' => 3600,
    ]
];
