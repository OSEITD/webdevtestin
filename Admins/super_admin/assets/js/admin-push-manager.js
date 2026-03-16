const ADMIN_PUSH_PUBLIC_KEY = 'BIH-erDsh-yK9B_aJuglh52uhmz2V8otvRUZ_a7rUp2vYtgxVNWXs5ZsfmOD_RNz3ATgGVbBGnxwzH0AGnwvlh8';

// Detect API path based on current location
function getAdminApiPath() {
    const pathname = window.location.pathname;
    // Extract the base path up to and including super_admin/
    const match = pathname.match(/(.*\/super_admin\/)/);
    if (match) {
        return match[1] + 'api/';
    }
    return 'api/';
}

class AdminPushNotificationManager {
    constructor() {
        this.registration = null;
        this.subscription = null;
        this.apiPath = getAdminApiPath();
        console.log('[Admin Push] API Path:', this.apiPath);
        this.init();
    }

    async init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('[Admin Push] Push notifications not supported');
            return;
        }

        try {
            this.registration = await navigator.serviceWorker.ready;
            console.log('[Admin Push] Service worker ready');

            await this.checkExistingSubscription();

            if (!this.subscription) {
                await this.requestPermissionAndSubscribe();
            }

            this.startHealthCheck();
        } catch (error) {
            console.error('[Admin Push] Init error:', error);
        }
    }

    startHealthCheck() {
        setInterval(() => {
            this.checkSubscriptionHealth();
        }, 15 * 60 * 1000);

        setTimeout(() => {
            this.checkSubscriptionHealth();
        }, 60000);
    }

    async checkSubscriptionHealth() {
        try {
            const currentSubscription = await this.registration.pushManager.getSubscription();

            if (!currentSubscription && Notification.permission === 'granted') {
                console.log('[Admin Push] Subscription missing, renewing...');
                await this.subscribe();
                return;
            }

            if (currentSubscription) {
                const url = this.apiPath + 'push/verify_subscription.php';
                console.log('[Admin Push] Verifying subscription at:', url);

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        endpoint: currentSubscription.endpoint
                    })
                });

                const result = await response.json();

                if (!result.active) {
                    console.log('[Admin Push] Subscription inactive, creating new...');
                    this.subscription = null;
                    await this.subscribe();
                } else {
                    console.log('[Admin Push] Health check passed');
                }
            }
        } catch (error) {
            console.error('[Admin Push] Health check error:', error);
        }
    }

    async checkExistingSubscription() {
        try {
            this.subscription = await this.registration.pushManager.getSubscription();

            if (this.subscription) {
                console.log('[Admin Push] Existing subscription found');
                await this.sendSubscriptionToServer(this.subscription);
                return true;
            }
            return false;
        } catch (error) {
            console.error('[Admin Push] Error checking subscription:', error);
            return false;
        }
    }

    async requestPermissionAndSubscribe() {
        try {
            const permission = await Notification.requestPermission();

            if (permission === 'granted') {
                console.log('[Admin Push] Permission granted');
                await this.subscribe();
            } else if (permission === 'denied') {
                console.log('[Admin Push] Permission denied');
            } else {
                console.log('[Admin Push] Permission dismissed');
            }

            return permission;
        } catch (error) {
            console.error('[Admin Push] Permission request error:', error);
            return 'error';
        }
    }

    async subscribe() {
        try {
            const convertedKey = this.urlBase64ToUint8Array(ADMIN_PUSH_PUBLIC_KEY);

            this.subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedKey
            });

            console.log('[Admin Push] Subscribed successfully');

            await this.sendSubscriptionToServer(this.subscription);

            return this.subscription;
        } catch (error) {
            console.error('[Admin Push] Subscription error:', error);
            throw error;
        }
    }

    async sendSubscriptionToServer(subscription) {
        try {
            const subscriptionJSON = subscription.toJSON();
            const url = this.apiPath + 'push/save_subscription.php';

            console.log('[Admin Push] Sending subscription to:', url);
            console.log('[Admin Push] API path:', this.apiPath);
            console.log('[Admin Push] Subscription endpoint:', subscriptionJSON.endpoint);

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subscription: subscriptionJSON,
                    endpoint: subscriptionJSON.endpoint
                })
            });

            const result = await response.json();

            if (result.success) {
                console.log('[Admin Push] Subscription sent to server');
            } else {
                console.error('[Admin Push] Server error:', result.error);
            }
        } catch (error) {
            console.error('[Admin Push] Error sending subscription:', error);
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

    getStatus() {
        return {
            supported: ('serviceWorker' in navigator) && ('PushManager' in window),
            registered: this.registration !== null,
            subscribed: this.subscription !== null,
            permission: Notification.permission
        };
    }
}

const adminPushManager = new AdminPushNotificationManager();
