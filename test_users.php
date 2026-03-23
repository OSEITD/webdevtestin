<?php
require_once __DIR__ . '/Admins/super_admin/api/supabase-client.php';
$users = callSupabaseWithServiceKey('all_users?limit=1');
print_r($users);
?>
