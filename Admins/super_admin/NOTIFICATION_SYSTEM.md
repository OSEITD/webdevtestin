# Admin Notification System Implementation

## Overview

The notification system has been successfully implemented in the Admin modules (super_admin) following the same architecture as the outlet-app. The system supports:

- **Database notifications** - Stored in Supabase `notifications` table
- **Web Push notifications** - Real-time browser push alerts via VAPID protocol
- **In-app notifications** - Floating popup UI with auto-refresh
- **Notification management** - Mark read, archive, delete operations
- **Push subscription management** - Automatic health checks and renewal

## Architecture

### Backend Components

#### 1. **notification-helper.php** (`includes/`)
Utility class for creating different notification types:
- `createUserCreatedNotification()` - When new users are created
- `createCompanyCreatedNotification()` - When companies are registered
- `createCompanyStatusNotification()` - Status changes (active → suspended)
- `createOutletCreatedNotification()` - New outlet creation
- `createSystemAlertNotification()` - System alerts/warnings
- `createReportNotification()` - Report generation completed
- `createRoleCreatedNotification()` - New roles created

**Usage:**
```php
require_once 'includes/notification-helper.php';

$notification = AdminNotificationHelper::createUserCreatedNotification($userData, $recipientAdminId);
callSupabaseWithServiceKey('notifications', 'POST', $notification);
```

#### 2. **push-notification-service.php** (`includes/`)
Service for sending Web Push notifications:
- `sendToAdmin($adminId, $title, $body, $data)` - Send push to admin
- `logNotification()` - Audit trail
- Uses VAPID protocol for browser push
- Automatic subscription health checking

**Usage:**
```php
require_once 'includes/push-notification-service.php';

$pushService = new AdminPushNotificationService();
$result = $pushService->sendToAdmin($adminId, 'Title', 'Body message', [
    'url' => '/Admins/super_admin/pages/companies.php',
    'type' => 'company_created'
]);
```

#### 3. **API Endpoints**

**`api/notifications.php`** - Already exists, handles:
- `?action=list` - Fetch notifications
- `?action=unread_count` - Get unread count
- `?action=mark_read` - Mark as read
- `?action=mark_all_read` - Mark all read
- `?action=dismiss` - Dismiss notification
- `?action=archive` - Archive notification

**`api/push/save_subscription.php`** - NEW
Saves/updates browser push subscriptions to `push_subscriptions` table

**`api/push/verify_subscription.php`** - NEW
Verifies if a subscription is active on the server

### Frontend Components

#### 1. **admin-notifications.js** (`assets/js/`)
JavaScript class `AdminNotificationSystem` for:
- Creating notification popup UI
- Loading notifications from API
- Marking as read/unread
- Auto-refresh every 30 seconds
- Click handlers and event management
- Responsive design

**Key Methods:**
```javascript
window.adminNotificationSystem.open()       // Open popup
window.adminNotificationSystem.close()      // Close popup
window.adminNotificationSystem.toggle()     // Toggle popup
window.adminNotificationSystem.loadNotifications()  // Fetch latest
window.adminNotificationSystem.markAsRead(id)      // Mark single as read
```

#### 2. **admin-push-manager.js** (`assets/js/`)
JavaScript class `AdminPushNotificationManager` for:
- Service worker registration
- Browser push permission handling
- Push subscription creation/renewal
- Health checks every 15 minutes
- VAPID protocol integration

**Key Methods:**
```javascript
adminPushManager.getStatus()  // Get push status
// Returns: { supported, registered, subscribed, permission }
```

#### 3. **Service Worker Updates** (`service-worker.js`)
Added push event handlers:
- `push` event - Receive and display notifications
- `notificationclick` event - Handle notification/action clicks
- `pushsubscriptionchange` event - Handle expired subscriptions

#### 4. **HTML Integration** (`includes/header.php`)
Added scripts:
```html
<script src="assets/js/admin-notifications.js"></script>
<script src="assets/js/admin-push-manager.js"></script>
```

The notification bell button dynamically creates in navbar with badge.

## Database Schema

### `notifications` table
```sql
- id (UUID, PK)
- recipient_id (UUID) - Admin user who receives notification
- user_id (UUID) - Related user (optional)
- company_id (UUID) - Related company (optional)
- outlet_id (UUID) - Related outlet (optional)
- title (text) - Notification title
- message (text) - Notification body
- notification_type (enum) - parcel_created, delivery_assigned, etc.
- priority (enum) - low, medium, high, urgent
- status (enum) - unread, read, dismissed, archived
- is_read (boolean)
- data (JSONB) - Context-specific data
- created_at (timestamp)
- read_at (timestamp, nullable)
```

### `push_subscriptions` table
```sql
- id (UUID, PK)
- user_id (UUID) - Admin user
- endpoint (text) - Push service endpoint
- p256dh_key (text) - Encryption key
- auth_key (text) - Authentication key
- is_active (boolean) - Subscription active status
- created_at (timestamp)
- updated_at (timestamp)
```

## Integration Points

### 1. When Creating Users
```php
// In api/add_user.php
require_once 'includes/notification-helper.php';
require_once 'includes/push-notification-service.php';

$notification = AdminNotificationHelper::createUserCreatedNotification($userData);
callSupabaseWithServiceKey('notifications', 'POST', $notification);

// Optionally send push to specific admin
$pushService = new AdminPushNotificationService();
$pushService->sendToAdmin($targetAdminId, $notification['title'], $notification['message'], [
    'type' => 'user_created',
    'user_id' => $userData['id'],
    'url' => '/Admins/super_admin/pages/users.php?id=' . $userData['id']
]);
```

### 2. When Updating Company Status
```php
// In api/update_company.php
if ($oldStatus !== $newStatus) {
    $notification = AdminNotificationHelper::createCompanyStatusNotification(
        $company, 
        $oldStatus, 
        $newStatus,
        $recipientAdminId  // Optional - send to specific admin
    );
    callSupabaseWithServiceKey('notifications', 'POST', $notification);
}
```

### 3. OnPage Load (Automatic)
Push manager automatically:
1. Registers service worker
2. Requests notification permission
3. Creates/restores push subscription
4. Saves subscription to server
5. Starts health checks

## Usage Examples

### Creating and Sending Notifications

**From PHP:**
```php
require_once 'includes/notification-helper.php';
require_once 'includes/push-notification-service.php';

// Create notification object
$notification = AdminNotificationHelper::createCompanyCreatedNotification([
    'id' => 'company-123',
    'company_name' => 'Acme Corp',
    'company_email' => 'contact@acme.com'
], $adminUserId);

// Save to database
callSupabaseWithServiceKey('notifications', 'POST', $notification);

// Send push notification
$pushService = new AdminPushNotificationService();
$result = $pushService->sendToAdmin($adminUserId, 
    'New Company Created',
    'Acme Corp has been added to the system',
    [
        'company_id' => 'company-123',
        'url' => '/Admins/super_admin/pages/companies.php?id=company-123',
        'type' => 'company_created'
    ]
);

// Check result
if ($result['success']) {
    error_log("Push sent to " . $result['sent_count'] . " devices");
}
```

**From API Calls:**
```javascript
// In admin pages, JavaScript can trigger notifications
fetch('api/notifications.php?action=list&limit=10')
    .then(r => r.json())
    .then(data => {
        console.log('Notifications:', data.notifications);
    });
```

### Checking Push Status

**In Browser Console:**
```javascript
adminPushManager.getStatus()
// Returns: 
// {
//   supported: true,
//   registered: true,
//   subscribed: true,
//   permission: "granted"
// }
```

## Configuration

### VAPID Keys
Located in `.env` file (same for entire application):
```
VAPID_SUBJECT=mailto:admin@yourcompany.com
VAPID_PUBLIC_KEY=BIH-erDsh-yK9B_aJuglh52uhmz2V8otvRUZ_a7rUp2vYtgxVNWXs5ZsfmOD_RNz3ATgGVbBGnxwzH0AGnwvlh8
VAPID_PRIVATE_KEY=<private key here>
```

The VAPID keys are identical to outlet-app to maintain consistency.

## Features

✅ **Real-time Push Notifications** - Instant browser alerts
✅ **In-app Popup UI** - Floating notification center
✅ **Auto-refresh** - Polls every 30 seconds
✅ **Subscription Health Checks** - Every 15 minutes
✅ **Multiple Devices** - Support for multiple subscriptions per user
✅ **Notification Types** - Categorized by type, priority, status
✅ **Offline Support** - Graceful degradation when API unavailable
✅ **Audit Trail** - All notifications logged for compliance

## Troubleshooting

### Notifications Not Appearing

1. **Check browser support:**
   ```javascript
   console.log('Service Worker:', 'serviceWorker' in navigator);
   console.log('PushManager:', 'PushManager' in window);
   console.log('Notification:', 'Notification' in window);
   ```

2. **Check permission status:**
   ```javascript
   console.log('Permission:', Notification.permission);
   ```

3. **Check server logs:**
   ```bash
   tail -f /path/to/logs/error.log | grep -i "push\|notification"
   ```

4. **Verify subscription:**
   In Supabase console, check `push_subscriptions` table for active records

### Push Service Not Found

Ensure `.env` file contains valid VAPID keys:
```bash
echo $VAPID_PUBLIC_KEY   # Should print the public key
echo $VAPID_PRIVATE_KEY  # Should print the private key (keep secret!)
```

### Subscriptions Expiring

The health check automatically renews expired subscriptions every 15 minutes. If still failing:
1. Clear browser storage (Settings → Privacy)
2. Reload page
3. Browser will request permission again

## Performance Considerations

- **Notification polling:** 30 seconds (configurable in admin-notifications.js)
- **Push health checks:** 15 minutes (configurable in admin-push-manager.js)
- **Database queries:** Indexed on `recipient_id`, `company_id` for fast lookups
- **Subscription storage:** Minimal (endpoint + 2 keys per device)

## Security

✅ Uses VAPID protocol for push encryption
✅ Service worker validates push origin
✅ Subscriptions scoped to user ID
✅ Requires super_admin role for API access
✅ CORS headers prevent cross-origin push
✅ No sensitive data in push body (use `data` field for URLs only)

## Future Enhancements

- [ ] Email fallback for critical notifications
- [ ] SMS notifications via Twilio
- [ ] Notification preferences/settings page
- [ ] Batch sending for performance
- [ ] Schedule notifications (cron-based)
- [ ] Advanced filtering/search in notification history
- [ ] Export notifications to CSV/PDF
- [ ] Notification templates with variables
