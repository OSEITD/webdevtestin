<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Worker Cleanup</title>
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
        }
        
        .log {
            background: #1f2937;
            color: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            max-height: 500px;
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
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .danger {
            background: #ef4444;
        }
        
        .danger:hover {
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        .success {
            background: #10b981;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Service Worker Cleanup Tool</h1>
        <p style="margin-bottom: 20px; color: #6b7280;">
            This tool will help you unregister conflicting service workers and re-register the correct one for drivers.
        </p>
        
        <div class="log" id="log"></div>
        
        <button onclick="listServiceWorkers()">List Service Workers</button>
        <button onclick="unregisterAll()" class="danger">Unregister All</button>
        <button onclick="registerDriverSW()" class="success">Register Driver SW</button>
        <button onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
    </div>
    
    <script>
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            const timestamp = new Date().toLocaleTimeString();
            entry.textContent = `[${timestamp}] ${message}`;
            if (type === 'success') entry.style.color = '#10b981';
            if (type === 'error') entry.style.color = '#ef4444';
            if (type === 'warning') entry.style.color = '#f59e0b';
            logDiv.appendChild(entry);
            logDiv.scrollTop = logDiv.scrollHeight;
        }
        
        async function listServiceWorkers() {
            log('Checking service worker registrations...', 'info');
            
            if (!('serviceWorker' in navigator)) {
                log('❌ Service Worker API not supported', 'error');
                return;
            }
            
            try {
                const registrations = await navigator.serviceWorker.getRegistrations();
                log(`Found ${registrations.length} service worker(s)`, 'info');
                
                registrations.forEach((reg, index) => {
                    log(`\n--- Service Worker ${index + 1} ---`, 'info');
                    log(`Scope: ${reg.scope}`, 'info');
                    log(`Active: ${reg.active ? 'YES' : 'NO'}`, reg.active ? 'success' : 'warning');
                    if (reg.active) {
                        log(`  Script URL: ${reg.active.scriptURL}`, 'info');
                        log(`  State: ${reg.active.state}`, 'info');
                    }
                    if (reg.installing) {
                        log(`Installing: ${reg.installing.scriptURL}`, 'warning');
                    }
                    if (reg.waiting) {
                        log(`Waiting: ${reg.waiting.scriptURL}`, 'warning');
                    }
                });
                
                if (registrations.length === 0) {
                    log('✅ No service workers registered', 'success');
                }
            } catch (error) {
                log(`❌ Error listing service workers: ${error.message}`, 'error');
            }
        }
        
        async function unregisterAll() {
            log('\n🗑️ Unregistering all service workers...', 'warning');
            
            try {
                const registrations = await navigator.serviceWorker.getRegistrations();
                
                if (registrations.length === 0) {
                    log('ℹ️ No service workers to unregister', 'info');
                    return;
                }
                
                for (const registration of registrations) {
                    log(`Unregistering: ${registration.scope}`, 'info');
                    const success = await registration.unregister();
                    if (success) {
                        log(`✅ Unregistered: ${registration.scope}`, 'success');
                    } else {
                        log(`❌ Failed to unregister: ${registration.scope}`, 'error');
                    }
                }
                
                log('\n✅ All service workers unregistered!', 'success');
                log('⚠️ You should now refresh the page and register the driver SW', 'warning');
                
            } catch (error) {
                log(`❌ Error unregistering: ${error.message}`, 'error');
            }
        }
        
        async function registerDriverSW() {
            log('\n📝 Registering driver service worker...', 'info');
            
            try {
                
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (const reg of registrations) {
                    await reg.unregister();
                    log(`Unregistered: ${reg.scope}`, 'info');
                }
                
                
                await new Promise(resolve => setTimeout(resolve, 500));
                
                
                const swPath = './sw.js';
                log(`Registering: ${swPath}`, 'info');
                
                const registration = await navigator.serviceWorker.register(swPath);
                await navigator.serviceWorker.ready;
                
                log(`✅ Driver SW registered!`, 'success');
                log(`Scope: ${registration.scope}`, 'info');
                log(`Script: ${registration.active?.scriptURL || 'pending...'}`, 'info');
                
                log('\n⚠️ Now you need to enable push notifications', 'warning');
                log('👉 Go back to dashboard and click "Enable Notifications"', 'info');
                
            } catch (error) {
                log(`❌ Error registering driver SW: ${error.message}`, 'error');
                console.error(error);
            }
        }
        
        
        window.addEventListener('load', listServiceWorkers);
    </script>
</body>
</html>
