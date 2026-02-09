<?php
// Cache test file - Should show current timestamp
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cache Test</title>
</head>
<body>
    <h1>Cache Test</h1>
    <p>Current server time: <strong><?php echo date('Y-m-d H:i:s'); ?></strong></p>
    <p>Refresh this page (F5 or Ctrl+R). The timestamp should update each time.</p>
    <p>If the timestamp doesn't change, your browser is caching the page.</p>
    <hr>
    <p>Random number: <?php echo rand(10000, 99999); ?></p>
    
    <script>
        console.log('Page loaded at:', new Date().toISOString());
        console.log('PHP rendered at:', '<?php echo date('c'); ?>');
    </script>
</body>
</html>
