const PUSH_PUBLIC_KEY = 'BIH-erDsh-yK9B_aJuglh52uhmz2V8otvRUZ_a7rUp2vYtgxVNWXs5ZsfmOD_RNz3ATgGVbBGnxwzH0AGnwvlh8';

class PushNotificationManager {
    constructor() {
        this.registration = null;
        this.subscription = null;
        this.init();
    }
    
    async init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('Push notifications not supported');
            return;
        }
        
        try {
            this.registration = await navigator.serviceWorker.ready;
            console.log('[Push] Service worker ready');
            
            await this.checkExistingSubscription();
            
            if (!this.subscription) {
                await this.requestPermissionAndSubscribe();
            }
            
            // Periodically check subscription health
            this.startHealthCheck();
        } catch (error) {
            console.error('[Push] Init error:', error);
        }
    }
    
    startHealthCheck() {
        // Check subscription health every 15 minutes (reduced from 5 to avoid too frequent checks)
        setInterval(() => {
            this.checkSubscriptionHealth();
        }, 15 * 60 * 1000);
        
        // Initial check after 1 minute (gives time for page to fully load)
        setTimeout(() => {
            this.checkSubscriptionHealth();
        }, 60000);
    }
    
    async checkSubscriptionHealth() {
        try {
            const currentSubscription = await this.registration.pushManager.getSubscription();
            
            // If subscription is missing, renew it
            if (!currentSubscription && Notification.permission === 'granted') {
                console.log('[Push] Subscription missing, renewing...');
                await this.subscribe();
                return;
            }
            
            // Verify subscription is still active on server
            if (currentSubscription) {
                const response = await fetch('api/push/verify_subscription.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        endpoint: currentSubscription.endpoint
                    })
                });
                
                const result = await response.json();
                
                if (!result.active) {
                    console.log('[Push] Subscription inactive on server, creating new subscription...');
                    // Don't unsubscribe - just create a new subscription
                    // The old one is already inactive on server
                    this.subscription = null;
                    await this.subscribe();
                } else {
                    console.log('[Push] Health check passed - subscription is active');
                }
            }
        } catch (error) {
            console.error('[Push] Health check error:', error);
        }
    }
    
    async checkExistingSubscription() {
        try {
            this.subscription = await this.registration.pushManager.getSubscription();
            
            if (this.subscription) {
                console.log('[Push] Existing subscription found');
                await this.sendSubscriptionToServer(this.subscription);
                return true;
            }
            return false;
        } catch (error) {
            console.error('[Push] Error checking subscription:', error);
            return false;
        }
    }
    
    async requestPermissionAndSubscribe() {
        try {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                console.log('[Push] Permission granted');
                await this.subscribe();
            } else if (permission === 'denied') {
                console.log('[Push] Permission denied');
            } else {
                console.log('[Push] Permission dismissed');
            }
            
            return permission;
        } catch (error) {
            console.error('[Push] Permission request error:', error);
            return 'error';
        }
    }
    
    async subscribe() {
        try {
            const convertedKey = this.urlBase64ToUint8Array(PUSH_PUBLIC_KEY);
            
            this.subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedKey
            });
            
            console.log('[Push] Subscribed successfully');
            
            await this.sendSubscriptionToServer(this.subscription);
            
            return this.subscription;
        } catch (error) {
            console.error('[Push] Subscription error:', error);
            throw error;
        }
    }
    
    async sendSubscriptionToServer(subscription) {
        try {
            const subscriptionJSON = subscription.toJSON();
            
            const response = await fetch('api/push/save_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subscription: subscriptionJSON,
                    endpoint: subscriptionJSON.endpoint,
                    keys: subscriptionJSON.keys
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('[Push] Subscription saved to server');
            } else {
                console.error('[Push] Failed to save subscription:', result.error);
            }
            
            return result;
        } catch (error) {
            console.error('[Push] Error sending subscription to server:', error);
            throw error;
        }
    }
    
    async unsubscribe() {
        try {
            if (!this.subscription) {
                console.log('[Push] No active subscription');
                return true;
            }
            
            const success = await this.subscription.unsubscribe();
            
            if (success) {
                console.log('[Push] Unsubscribed successfully');
                this.subscription = null;
                
                await fetch('../api/push/remove_subscription.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
            }
            
            return success;
        } catch (error) {
            console.error('[Push] Unsubscribe error:', error);
            return false;
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
    
    getSubscriptionStatus() {
        return {
            supported: ('serviceWorker' in navigator) && ('PushManager' in window),
            subscribed: this.subscription !== null,
            permission: Notification.permission
        };
    }
}

const pushManager = new PushNotificationManager();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = PushNotificationManager;
}
