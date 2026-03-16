<?php
// Compatibility shim: some pages historically required 'supabase-client.php' from the pages/ folder.
// Forward to the canonical client implementation in ../api/
// This file keeps older relative require calls working without changing many files.
try {
    require_once __DIR__ . '/../api/supabase-client.php';
} catch (Exception $e) {
    // If include fails, log and rethrow so the caller sees a clear message in the logs
    error_log('shim supabase-client.php failed to include ../api/supabase-client.php: ' . $e->getMessage());
    throw $e;
}

?>
