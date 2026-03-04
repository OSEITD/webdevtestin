<?php
if (!class_exists('EnvLoader')) {
    require_once __DIR__ . '/../includes/env.php';
}
EnvLoader::load();

define('SUPABASE_URL', getenv('SUPABASE_URL') ?: '');

define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY') ?: '');


define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: '');

// Storage bucket name for parcel photos
define('PARCEL_PHOTOS_BUCKET', 'parcel-photos');
?>
