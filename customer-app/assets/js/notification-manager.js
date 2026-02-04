

class NotificationManager {
    constructor() {
        this.swRegistration = null;
        this.isSubscribed = false;
        this.customerPhone = null;
        this.customerEmail = null;
    }
    async init(phone, email) {
        this.customerPhone = phone;
        this.customerEmail = email;
        if (!('serviceWorker' in navigator)) {
            console.warn('Service Workers not supported');
            return false;
        }
        if (!('PushManager' in window)) {
            console.warn('Push notifications not supported');
            return false;
        }
        try {
            this.swRegistration = await navigator.serviceWorker.register(
                'notification-service-worker.js'
            );
            console.log('Service Worker registered:', this.swRegistration);
            await this.checkSubscription();
            if (Notification.permission === 'default') {
                await this.requestPermission();
            }
            return true;
        } catch (error) {
            console.error('Service Worker registration failed:', error);
            return false;
        }
    }
    async checkSubscription() {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            this.isSubscribed = subscription !== null;
            console.log('Push subscription status:', this.isSubscribed);
            
            if (subscription) {
                console.log('Current subscription:', subscription);
            }
            
            return this.isSubscribed;
        } catch (error) {
            console.error('Error checking subscription:', error);
            return false;
        }
    }

    async requestPermission() {
        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                console.log('Notification permission granted');
                await this.subscribe();
                return true;
            } else if (permission === 'denied') {
                console.warn('Notification permission denied');
                this.showPermissionDeniedMessage();
                return false;
            } else {
                console.log('Notification permission dismissed');
                return false;
            }
        } catch (error) {
            console.error('Error requesting permission:', error);
            return false;
        }
    }
    async subscribe() {
        try {
            const vapidPublicKey = 'BCY0qUogvybuSyncUQNX8qmtSx7MoH1oSf7NT0f4NRvFCVniXT_8IuMFJq9u-I4u9vIVtekZNUFPFfVDS0BRamg';
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey)
            });
            console.log('Push subscription successful:', subscription);
            await this.sendSubscriptionToServer(subscription);
            this.isSubscribed = true;
            return subscription;
        } catch (error) {
            console.error('Failed to subscribe:', error);
            return null;
        }
    }
    async unsubscribe() {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            if (subscription) {
                await subscription.unsubscribe();
                console.log('Unsubscribed from push notifications');
                this.isSubscribed = false;
                return true;
            }
            return false;
        } catch (error) {
            console.error('Error unsubscribing:', error);
            return false;
        }
    }
    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('api/save_push_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subscription: subscription,
                    phone: this.customerPhone,
                    email: this.customerEmail
                })
            });
            const data = await response.json();
            if (data.success) {
                console.log('Subscription saved to server');
            } else {
                console.error('Failed to save subscription:', data.error);
            }
            return data;
        } catch (error) {
            console.error('Error sending subscription to server:', error);
            return { success: false, error: error.message };
        }
    }
    async fetchNotifications() {
        try {
            const params = new URLSearchParams();
            if (this.customerPhone) {
                params.append('phone', this.customerPhone);
            } else if (this.customerEmail) {
                params.append('email', this.customerEmail);
            }
            const response = await fetch(`api/notifications.php?action=list&${params}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching notifications:', error);
            return { success: false, error: error.message };
        }
    }
    async markAsRead(notificationId) {
        try {
            const response = await fetch('api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error marking notification as read:', error);
            return { success: false, error: error.message };
        }
    }
    async showLocalNotification(title, body, data = {}) {
        if (Notification.permission === 'granted' && this.swRegistration) {
            await this.swRegistration.showNotification(title, {
                body: body,
                icon: '/img/logo.png',
                badge: '/img/badge.png',
                vibrate: [200, 100, 200],
                data: data,
                tag: 'parcel-update',
                requireInteraction: true
            });
        }
    }
    showPermissionDeniedMessage() {
        console.warn('Push notifications are blocked. Please enable them in browser settings.');
        if (typeof showNotification === 'function') {
            showNotification(
                'To receive delivery updates, please enable notifications in your browser settings.',
                'warning'
            );
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
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationManager;
}

