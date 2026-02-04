<!-- Manager Push Notification Settings Modal -->
<div id="pushNotificationModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px 12px 0 0;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-bell"></i>
                Push Notifications Settings
            </h2>
            <button onclick="closePushNotificationModal()" style="position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.2); border: none; color: white; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 20px;">
                ×
            </button>
        </div>
        
        <div class="modal-body" style="padding: 30px;">
            <div id="pushNotificationContent">
                <!-- Content will be dynamically loaded -->
            </div>
        </div>
    </div>
</div>

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px);
        animation: fadeIn 0.3s ease;
    }

    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        max-width: 600px;
        position: relative;
        animation: slideIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .notification-status {
        background: #f3f4f6;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
    }

    .notification-status i {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .notification-status.enabled i {
        color: #10b981;
    }

    .notification-status.disabled i {
        color: #ef4444;
    }

    .btn-enable-notifications {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        width: 100%;
        margin-top: 15px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .btn-enable-notifications:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-enable-notifications:disabled {
        background: #9ca3af;
        cursor: not-allowed;
        transform: none;
    }

    .btn-disable-notifications {
        background: #ef4444;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        width: 100%;
        margin-top: 10px;
    }

    .info-box {
        background: #dbeafe;
        border-left: 4px solid #3b82f6;
        padding: 15px;
        margin: 15px 0;
        border-radius: 4px;
        font-size: 14px;
        line-height: 1.6;
    }

    .success-box {
        background: #d1fae5;
        border-left: 4px solid #10b981;
        padding: 15px;
        margin: 15px 0;
        border-radius: 4px;
        font-size: 14px;
        line-height: 1.6;
    }

    .error-box {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        padding: 15px;
        margin: 15px 0;
        border-radius: 4px;
        font-size: 14px;
        line-height: 1.6;
    }

    .subscription-info {
        background: #f9fafb;
        padding: 15px;
        border-radius: 6px;
        margin-top: 20px;
        font-size: 13px;
        color: #6b7280;
    }

    .subscription-info strong {
        color: #374151;
        display: block;
        margin-bottom: 5px;
    }

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<script>

class ManagerPushNotificationManager {
    constructor() {
        this.publicKey = 'BIH-erDsh-yK9B_aJuglh52uhmz2V8otvRUZ_a7rUp2vYtgxVNWXs5ZsfmOD_RNz3ATgGVbBGnxwzH0AGnwvlh8';
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.subscription = null;
    }

    async init() {
        if (!this.isSupported) {
            return {
                supported: false,
                message: 'Push notifications are not supported in this browser.'
            };
        }

        try {
            
            const currentPath = window.location.pathname;
            const swPath = currentPath.includes('/outlet-app/') 
                ? '/outlet-app/sw-manager.js' 
                : (currentPath.includes('/pages/') 
                    ? new URL('../sw-manager.js', window.location.href).pathname
                    : '/sw-manager.js');
            
            console.log('[Push Manager] Registering SW at:', swPath);
            const registration = await navigator.serviceWorker.register(swPath);
            await navigator.serviceWorker.ready;

            
            this.subscription = await registration.pushManager.getSubscription();

            return {
                supported: true,
                subscribed: this.subscription !== null,
                subscription: this.subscription
            };
        } catch (error) {
            console.error('Error initializing push notifications:', error);
            return {
                supported: true,
                subscribed: false,
                error: error.message
            };
        }
    }

    async subscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;

            
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                throw new Error('Notification permission denied');
            }

            
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.publicKey)
            });

            this.subscription = subscription;

            
            
            const apiPath = window.location.pathname.includes('/outlet-app/') 
                ? '/outlet-app/api/save_push_subscription.php'
                : '/api/save_push_subscription.php';
                
            const response = await fetch(apiPath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subscription: subscription.toJSON(),
                    user_role: 'outlet_manager'
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to save subscription');
            }

            return { success: true, subscription };

        } catch (error) {
            console.error('Error subscribing to push notifications:', error);
            throw error;
        }
    }

    async unsubscribe() {
        try {
            if (!this.subscription) {
                const registration = await navigator.serviceWorker.ready;
                this.subscription = await registration.pushManager.getSubscription();
            }

            if (this.subscription) {
                await this.subscription.unsubscribe();

                
                const apiPath = window.location.pathname.includes('/outlet-app/') 
                    ? '/outlet-app/api/remove_push_subscription.php'
                    : '/api/remove_push_subscription.php';
                    
                const response = await fetch(apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        endpoint: this.subscription.endpoint
                    })
                });

                this.subscription = null;

                return { success: true };
            }

            return { success: true, message: 'No subscription to remove' };

        } catch (error) {
            console.error('Error unsubscribing from push notifications:', error);
            throw error;
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}

const managerPushManager = new ManagerPushNotificationManager();

async function openPushNotificationModal() {
    const modal = document.getElementById('pushNotificationModal');
    const content = document.getElementById('pushNotificationContent');

    modal.style.display = 'block';
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="loading-spinner"></div><p style="margin-top: 15px;">Loading...</p></div>';

    
    const status = await managerPushManager.init();

    if (!status.supported) {
        content.innerHTML = `
            <div class="error-box">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Not Supported</strong>
                <p>${status.message}</p>
            </div>
        `;
        return;
    }

    if (status.subscribed) {
        content.innerHTML = `
            <div class="notification-status enabled">
                <i class="fas fa-check-circle"></i>
                <h3 style="color: #10b981; margin: 10px 0;">Notifications Enabled</h3>
                <p style="color: #6b7280;">You will receive push notifications for trip updates.</p>
            </div>
            
            <div class="success-box">
                <strong>✓ What you'll receive:</strong>
                <ul style="margin: 10px 0 0 20px; padding: 0;">
                    <li>Trip started notifications</li>
                    <li>Trip arrival at outlet notifications</li>
                    <li>Real-time trip status updates</li>
                </ul>
            </div>

            <button class="btn-disable-notifications" onclick="disablePushNotifications()">
                <i class="fas fa-bell-slash"></i> Disable Notifications
            </button>

            <div class="subscription-info">
                <strong>Subscription Details:</strong>
                <p style="word-break: break-all; margin: 5px 0 0 0;">
                    Endpoint: ${status.subscription.endpoint.substring(0, 50)}...
                </p>
            </div>
        `;
    } else {
        content.innerHTML = `
            <div class="notification-status disabled">
                <i class="fas fa-bell-slash"></i>
                <h3 style="color: #ef4444; margin: 10px 0;">Notifications Disabled</h3>
                <p style="color: #6b7280;">Enable notifications to receive trip updates.</p>
            </div>

            <div class="info-box">
                <strong>📱 Receive notifications for:</strong>
                <ul style="margin: 10px 0 0 20px; padding: 0;">
                    <li><strong>Trip Started:</strong> When drivers start their trips</li>
                    <li><strong>Outlet Arrivals:</strong> When trips reach outlet stops</li>
                    <li><strong>Trip Updates:</strong> Real-time status changes</li>
                </ul>
            </div>

            <button class="btn-enable-notifications" onclick="enablePushNotifications()" id="enablePushBtn">
                <i class="fas fa-bell"></i>
                Enable Push Notifications
            </button>

            <div class="info-box" style="margin-top: 20px; background: #fef3c7; border-left-color: #f59e0b;">
                <strong>⚠️ Note:</strong> Your browser will ask for permission to show notifications. 
                Please click "Allow" when prompted.
            </div>
        `;
    }
}

function closePushNotificationModal() {
    document.getElementById('pushNotificationModal').style.display = 'none';
}

async function enablePushNotifications() {
    const btn = document.getElementById('enablePushBtn');
    const originalHTML = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<div class="loading-spinner"></div> Enabling...';

    try {
        await managerPushManager.subscribe();

        
        openPushNotificationModal();

        
        showNotification('Push notifications enabled successfully!', 'success');

    } catch (error) {
        btn.disabled = false;
        btn.innerHTML = originalHTML;

        const content = document.getElementById('pushNotificationContent');
        content.innerHTML = `
            <div class="error-box">
                <strong>❌ Failed to enable notifications</strong>
                <p>${error.message}</p>
                <p style="margin-top: 10px; font-size: 13px;">
                    <strong>Common causes:</strong><br>
                    • Notification permission was denied<br>
                    • Browser doesn't support push notifications<br>
                    • Service worker registration failed
                </p>
            </div>
            <button class="btn-enable-notifications" onclick="openPushNotificationModal()">
                Try Again
            </button>
        `;
    }
}

async function disablePushNotifications() {
    if (!confirm('Are you sure you want to disable push notifications? You will no longer receive trip updates.')) {
        return;
    }

    try {
        await managerPushManager.unsubscribe();

        
        openPushNotificationModal();

        showNotification('Push notifications disabled', 'info');

    } catch (error) {
        alert('Failed to disable notifications: ' + error.message);
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10001;
        animation: slideInRight 0.3s ease;
        max-width: 400px;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

window.onclick = function(event) {
    const modal = document.getElementById('pushNotificationModal');
    if (event.target === modal) {
        closePushNotificationModal();
    }
}
</script>
