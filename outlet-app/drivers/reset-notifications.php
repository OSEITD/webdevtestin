<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../includes/env.php';
EnvLoader::load();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Push Notifications</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 12px;
            font-size: 28px;
        }
        
        p {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        
        .status {
            background: #f3f4f6;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .status div {
            margin: 4px 0;
        }
        
        .success {
            color: #10b981;
        }
        
        .error {
            color: #ef4444;
        }
        
        .info {
            color: #3b82f6;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-bottom: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .secondary:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 Reset Push Notifications</h1>
        <p>If you're not receiving trip notifications, use this tool to reset your push notification subscription with the latest credentials.</p>
        
        <div class="status" id="status">
            <div class="info">⏳ Ready to reset notifications...</div>
        </div>
        
        <button id="resetBtn" onclick="resetNotifications()">Reset Notifications</button>
        <button class="secondary" onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
    </div>
    
    <script>
        const VAPID_PUBLIC_KEY = '<?php echo htmlspecialchars(EnvLoader::get('VAPID_PUBLIC_KEY')); ?>';
        
        function log(message, type = 'info') {
            const statusDiv = document.getElementById('status');
            const div = document.createElement('div');
            div.className = type;
            div.textContent = message;
            statusDiv.appendChild(div);
            statusDiv.scrollTop = statusDiv.scrollHeight;
        }
        
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');
            
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
        
        async function resetNotifications() {
            const btn = document.getElementById('resetBtn');
            btn.disabled = true;
            
            try {
                log('🔍 Checking browser support...', 'info');
                
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                    log('❌ Push notifications not supported in this browser', 'error');
                    btn.disabled = false;
                    return;
                }
                
                log('✅ Browser supports push notifications', 'success');
                log('📝 Registering service worker...', 'info');
                
                const registration = await navigator.serviceWorker.register('./sw.js');
                await navigator.serviceWorker.ready;
                
                log('✅ Service worker registered', 'success');
                log('🔍 Checking existing subscription...', 'info');
                
                const existingSubscription = await registration.pushManager.getSubscription();
                
                if (existingSubscription) {
                    log('📋 Found existing subscription', 'info');
                    log('🗑️ Unsubscribing from old subscription...', 'info');
                    await existingSubscription.unsubscribe();
                    log('✅ Unsubscribed from old subscription', 'success');
                } else {
                    log('ℹ️ No existing subscription found', 'info');
                }
                
                log('🔐 Requesting notification permission...', 'info');
                const permission = await Notification.requestPermission();
                
                if (permission !== 'granted') {
                    log('❌ Notification permission denied', 'error');
                    btn.disabled = false;
                    return;
                }
                
                log('✅ Notification permission granted', 'success');
                log('📡 Creating new subscription with current VAPID keys...', 'info');
                
                const newSubscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });
                
                log('✅ New subscription created', 'success');
                log('💾 Saving subscription to server...', 'info');
                
                const response = await fetch('./api/push/save_subscription.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subscription: newSubscription.toJSON(),
                        endpoint: newSubscription.endpoint,
                        keys: {
                            p256dh: newSubscription.toJSON().keys.p256dh,
                            auth: newSubscription.toJSON().keys.auth
                        }
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    log('✅ Subscription saved successfully!', 'success');
                    log('🎉 Push notifications reset complete!', 'success');
                    
                    
                    localStorage.setItem('driver_push_last_check', Date.now().toString());
                    localStorage.setItem('driver_vapid_version', '1');
                    
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    log('❌ Failed to save subscription: ' + result.error, 'error');
                    btn.disabled = false;
                }
                
            } catch (error) {
                log('❌ Error: ' + error.message, 'error');
                console.error('Reset error:', error);
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
