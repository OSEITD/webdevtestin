<?php
    $page_title = 'Company - Notifications';
    include __DIR__ . '/../includes/header.php';
?>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">
        <main class="main-content">
            <div style="margin-bottom:1rem;">
                <h1><i class="fas fa-bell"></i> Notifications</h1>
                <p class="subtitle">Recent system and operational alerts</p>
            </div>

            <div class="notifications-container" style="display:block;">
                <!-- JS will render notification cards here -->
            </div>

        </main>
    </div>

    <!-- Link to the external JavaScript file -->
    <script src="../assets/js/company-scripts.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
        // Initialize the notifications renderer
        document.addEventListener('DOMContentLoaded', function () {
            try { initializeNotifications(); } catch (e) { console.debug('initializeNotifications not available', e); }
        });
    </script>
</body>
</html>