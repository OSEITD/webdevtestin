<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Status Check</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 24px;
            font-size: 28px;
        }
        
        .status-grid {
            display: grid;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .status-item {
            background: #f3f4f6;
            padding: 16px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-label {
            font-weight: 600;
            color: #4b5563;
        }
        
        .status-value {
            font-family: 'Courier New', monospace;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .status-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .log-section {
            background: #1f2937;
            color: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .log-entry {
            margin: 4px 0;
            padding: 4px 0;
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
            margin-right: 12px;
            margin-bottom: 12px;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        .secondary {
            background: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 Push Notification Status</h1>
        
        <div class="status-grid">
            <div class="status-item">
                <span class="status-label">User ID:</span>
                <span class="status-value"><?php echo $_SESSION['user_id']; ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">Role:</span>
                <span class="status-value"><?php echo $_SESSION['role'] ?? 'Not set'; ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">Company ID:</span>
                <span class="status-value"><?php echo $_SESSION['company_id'] ?? 'Not set'; ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">Browser Support:</span>
                <span class="status-value" id="browserSupport">Checking...</span>
            </div>
            <div class="status-item">
                <span class="status-label">Notification Permission:</span>
                <span class="status-value" id="notificationPerm">Checking...</span>
            </div>
            <div class="status-item">
                <span class="status-label">Service Worker:</span>
                <span class="status-value" id="swStatus">Checking...</span>
            </div>
            <div class="status-item">
                <span class="status-label">Push Subscription:</span>
                <span class="status-value" id="subStatus">Checking...</span>
            </div>
            <div class="status-item">
                <span class="status-label">Session Storage (Dismissed):</span>
                <span class="status-value" id="sessionDismissed">Checking...</span>
            </div>
        </div>
        
        <div class="log-section" id="logOutput">
            <div>Loading diagnostic information...</div>
        </div>
        
        <button onclick="requestPermission()">Request Permission</button>
        <button onclick="subscribeNow()">Subscribe Now</button>
        <button onclick="clearSessionStorage()" class="secondary">Clear Session Storage</button>
        <button onclick="window.location.reload()" class="secondary">Refresh</button>
        <button onclick="window.location.href='outlet_dashboard.php'" class="secondary">Back to Dashboard</button>
    </div>
    
    <script>
        const VAPID_PUBLIC_KEY = '<?php echo htmlspecialchars(EnvLoader::get('VAPID_PUBLIC_KEY')); ?>';
        
        function log(message) {
            const logDiv = document.getElementById('logOutput');
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            logDiv.appendChild(entry);
            logDiv.scrollTop = logDiv.scrollHeight;
        }
        
        function setStatus(id, text, type) {
            const el = document.getElementById(id);
            el.textContent = text;
            el.className = 'status-value status-' + type;
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
        
        async function checkStatus() {
            document.getElementById('logOutput').innerHTML = '';
            
            log('Starting diagnostic check...');
            
            
            const hasServiceWorker = 'serviceWorker' in navigator;
            const hasPushManager = 'PushManager' in window;
            const hasNotification = 'Notification' in window;
            
            if (hasServiceWorker && hasPushManager && hasNotification) {
                setStatus('browserSupport', 'Supported', 'success');
                log('✅ Browser supports all required APIs');
            } else {
                setStatus('browserSupport', 'Not Supported', 'error');
                log('❌ Browser missing APIs: ' + 
                    (!hasServiceWorker ? 'ServiceWorker ' : '') +
                    (!hasPushManager ? 'PushManager ' : '') +
                    (!hasNotification ? 'Notification' : ''));
                return;
            }
            
            
            const permission = Notification.permission;
            setStatus('notificationPerm', permission, 
                permission === 'granted' ? 'success' : 
                permission === 'denied' ? 'error' : 'warning');
            log('Notification permission: ' + permission);
            
            
            const dismissed = sessionStorage.getItem('notification_prompt_dismissed');
            setStatus('sessionDismissed', dismissed || 'No', dismissed ? 'warning' : 'success');
            if (dismissed) {
                log('⚠️ Notification prompt was dismissed in this session');
            }
            
            
            try {
                const swPath = window.location.pathname.includes('/outlet-app/')
                    ? '/outlet-app/sw-manager.js'
                    : (window.location.pathname.includes('/pages/')
                        ? new URL('../sw-manager.js', window.location.href).pathname
                        : '/sw-manager.js');
                        
                log('Service worker path: ' + swPath);
                
                const registration = await navigator.serviceWorker.register(swPath);
                await navigator.serviceWorker.ready;
                setStatus('swStatus', 'Registered', 'success');
                log('✅ Service worker registered: ' + registration.scope);
                
                
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    setStatus('subStatus', 'Active', 'success');
                    log('✅ Push subscription exists');
                    log('Endpoint: ' + subscription.endpoint.substring(0, 60) + '...');
                } else {
                    setStatus('subStatus', 'Not Subscribed', 'warning');
                    log('⚠️ No push subscription found');
                }
            } catch (error) {
                setStatus('swStatus', 'Error', 'error');
                setStatus('subStatus', 'Error', 'error');
                log('❌ Service worker error: ' + error.message);
                console.error(error);
            }
        }
        
        async function requestPermission() {
            log('Requesting notification permission...');
            try {
                const permission = await Notification.requestPermission();
                log('Permission result: ' + permission);
                await checkStatus();
            } catch (error) {
                log('❌ Error requesting permission: ' + error.message);
            }
        }
        
        async function subscribeNow() {
            log('Starting subscription process...');
            try {
                if (Notification.permission !== 'granted') {
                    log('⚠️ Permission not granted, requesting...');
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        log('❌ Permission denied');
                        return;
                    }
                }
                
                const swPath = window.location.pathname.includes('/outlet-app/')
                    ? '/outlet-app/sw-manager.js'
                    : (window.location.pathname.includes('/pages/')
                        ? new URL('../sw-manager.js', window.location.href).pathname
                        : '/sw-manager.js');
                
                const registration = await navigator.serviceWorker.register(swPath);
                await navigator.serviceWorker.ready;
                log('✅ Service worker ready');
                
                
                const oldSub = await registration.pushManager.getSubscription();
                if (oldSub) {
                    log('Unsubscribing from old subscription...');
                    await oldSub.unsubscribe();
                }
                
                log('Creating new subscription...');
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });
                
                log('✅ Subscription created');
                
                const apiPath = window.location.pathname.includes('/outlet-app/')
                    ? '/outlet-app/api/save_push_subscription.php'
                    : '/api/save_push_subscription.php';
                
                log('Saving to backend: ' + apiPath);
                
                const response = await fetch(apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subscription: subscription.toJSON(),
                        user_role: 'outlet_manager'
                    })
                });
                
                const result = await response.json();
                log('Backend response: ' + JSON.stringify(result));
                
                if (result.success) {
                    log('✅ Subscription saved successfully!');
                    await checkStatus();
                } else {
                    log('❌ Failed to save: ' + result.error);
                }
            } catch (error) {
                log('❌ Subscription error: ' + error.message);
                console.error(error);
            }
        }
        
        function clearSessionStorage() {
            sessionStorage.removeItem('notification_prompt_dismissed');
            log('✅ Session storage cleared');
            checkStatus();
        }
        
        
        checkStatus();
    </script>
</body>
</html>
