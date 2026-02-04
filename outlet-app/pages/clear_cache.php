<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ Opcache cleared<br>";
} else {
    echo "⚠ Opcache not enabled<br>";
}

session_start();
echo "✓ Session started<br>";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

echo "<h1>Cache Cleared!</h1>";
echo "<p><a href='trips.php?nocache=" . time() . "'>Go to Trips Page (Fresh Load)</a></p>";
echo "<p><strong>OR manually:</strong></p>";
echo "<ol>";
echo "<li>Press Ctrl+Shift+Delete</li>";
echo "<li>Clear 'Cached images and files'</li>";
echo "<li>Close ALL browser windows</li>";
echo "<li>Open browser in Incognito/Private mode</li>";
echo "<li>Go to: <code>http://acme.localhost/outlet-app/pages/trips.php</code></li>";
echo "</ol>";
