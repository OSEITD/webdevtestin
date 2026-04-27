<?php
/**
 * NotificationDispatcher - Unified Notification System
 * 
 * This is the single entry point for ALL notifications across the platform.
 * It handles:
 *   1. Creating a persistent notification record in the `notifications` table
 *   2. Logging to `notification_logs`
 *   3. Sending real-time Web Push via the Push API
 *   4. Cleaning up expired/invalid subscriptions automatically
 * 
 * Usage:
 *   $dispatcher = new NotificationDispatcher();
 *   $dispatcher->send([
 *       'recipient_id' => $userId,
 *       'company_id'   => $companyId,
 *       'title'        => 'Parcel Dispatched',
 *       'message'      => 'Your parcel WD-12345 is on the way!',
 *       'notification_type' => 'parcel_status_change',
 *       'priority'     => 'high',
 *       'data'         => ['tracking_number' => 'WD-12345', 'url' => '/track?t=WD-12345'],
 *   ]);
 * 
 *   // Broadcast to all outlet staff:
 *   $dispatcher->sendToOutletStaff($outletId, $companyId, [...]);
 * 
 *   // Send to customer by parcel data (sender/receiver):
 *   $dispatcher->sendToCustomer($parcelData, 'Parcel Update', 'Your parcel is in transit');
 */

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class NotificationDispatcher
{
    private ?WebPush $webPush = null;
    private string $supabaseUrl;
    private string $supabaseKey;
    private bool $pushEnabled = false;

    /**
     * @param array|null $config Optional config override [supabase_url, supabase_key, vapid_public, vapid_private, vapid_subject]
     */
    public function __construct(?array $config = null)
    {
        $this->initSupabase($config);
        $this->initWebPush($config);
    }

    // ─── INITIALISATION ─────────────────────────────────────────

    private function initSupabase(?array $config): void
    {
        $this->supabaseUrl = $config['supabase_url']
            ?? $this->env('SUPABASE_URL', 'https://xerpchdsykqafrsxbqef.supabase.co');
        $this->supabaseKey = $config['supabase_key']
            ?? $this->env('SUPABASE_SERVICE_ROLE_KEY', '');

        if (empty($this->supabaseKey)) {
            // Fallback: try loading from outlet-app config
            $configFile = __DIR__ . '/../outlet-app/config.php';
            if (file_exists($configFile)) {
                $cfg = require $configFile;
                $this->supabaseUrl = $cfg['supabase']['url'] ?? $this->supabaseUrl;
                $this->supabaseKey = $cfg['supabase']['service_role_key'] ?? $this->supabaseKey;
            }
        }
    }

    private function initWebPush(?array $config): void
    {
        $vapidPublic  = $config['vapid_public']  ?? $this->env('VAPID_PUBLIC_KEY');
        $vapidPrivate = $config['vapid_private'] ?? $this->env('VAPID_PRIVATE_KEY');
        $vapidSubject = $config['vapid_subject'] ?? $this->env('VAPID_SUBJECT', 'mailto:admin@wdparcel.com');

        if (empty($vapidPublic) || empty($vapidPrivate)) {
            error_log('[NotificationDispatcher] VAPID keys not configured — push disabled');
            return;
        }

        try {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject'    => $vapidSubject,
                    'publicKey'  => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ]);
            $this->webPush->setReuseVAPIDHeaders(true);
            $this->pushEnabled = true;
        } catch (\Exception $e) {
            error_log('[NotificationDispatcher] WebPush init failed: ' . $e->getMessage());
        }
    }

    // ─── PRIMARY API ────────────────────────────────────────────

    /**
     * Send a notification: creates DB record + sends Web Push.
     *
     * @param array $params {
     *   @type string $recipient_id  (required) UUID of recipient profile
     *   @type string $company_id    (required) UUID of company
     *   @type string $title         (required) Notification title
     *   @type string $message       (required) Notification body
     *   @type string $notification_type  One of: parcel_created, parcel_status_change,
     *                                    delivery_assigned, delivery_completed, driver_unavailable,
     *                                    payment_received, urgent_delivery, system_alert, customer_inquiry
     *   @type string $priority      low|medium|high|urgent (default: medium)
     *   @type string $outlet_id     Optional outlet UUID
     *   @type string $sender_id     Optional sender profile UUID
     *   @type string $parcel_id     Optional parcel UUID
     *   @type string $delivery_id   Optional delivery UUID
     *   @type array  $data          Optional extra payload for push (url, type, etc.)
     *   @type array  $actions       Optional push notification action buttons
     *   @type string $tag           Optional push grouping tag
     *   @type bool   $push_only     If true, skip DB insert (default: false)
     *   @type bool   $db_only       If true, skip push send (default: false)
     * }
     * @return array {success: bool, notification_id: ?string, push_result: ?array}
     */
    public function send(array $params): array
    {
        $result = [
            'success'         => false,
            'notification_id' => null,
            'push_result'     => null,
            'errors'          => [],
        ];

        // Validate required fields
        foreach (['recipient_id', 'company_id', 'title', 'message', 'notification_type'] as $field) {
            if (empty($params[$field])) {
                $result['errors'][] = "Missing required field: $field";
            }
        }
        if (!empty($result['errors'])) {
            return $result;
        }

        $pushOnly = $params['push_only'] ?? false;
        $dbOnly   = $params['db_only'] ?? false;

        // Step 1: Create DB notification record
        if (!$pushOnly) {
            try {
                $dbResult = $this->createNotificationRecord($params);
                $result['notification_id'] = $dbResult['id'] ?? null;
            } catch (\Exception $e) {
                error_log('[NotificationDispatcher] DB insert failed: ' . $e->getMessage());
                $result['errors'][] = 'DB insert failed: ' . $e->getMessage();
            }
        }

        // Step 2: Send Web Push
        if (!$dbOnly && $this->pushEnabled) {
            try {
                // Ensure push payload contains notification_id and current unread_count
                $pushData = $params['data'] ?? [];
                if (!empty($result['notification_id'])) {
                    $pushData['notification_id'] = $result['notification_id'];
                }
                // Attempt to include unread count for recipient (helpful for badges)
                try {
                    $pushData['unread_count'] = $this->getUnreadCount($params['recipient_id']);
                } catch (\Exception $e) {
                    // Non-fatal; continue without unread_count
                    error_log('[NotificationDispatcher] Failed to fetch unread count: ' . $e->getMessage());
                }

                $pushResult = $this->sendPush(
                    $params['recipient_id'],
                    $params['title'],
                    $params['message'],
                    $pushData,
                    $params
                );
                $result['push_result'] = $pushResult;
            } catch (\Exception $e) {
                error_log('[NotificationDispatcher] Push send failed: ' . $e->getMessage());
                $result['errors'][] = 'Push send failed: ' . $e->getMessage();
            }
        }

        // Step 3: Log the notification
        $this->logNotification(
            $params['recipient_id'],
            $params['title'],
            $params['message'],
            $params['data'] ?? [],
            empty($result['errors']) ? 'sent' : 'failed'
        );

        $result['success'] = empty($result['errors']);
        return $result;
    }

    /**
     * Send notification to multiple recipients.
     *
     * @param array  $recipientIds Array of profile UUIDs
     * @param array  $params       Same as send() but without recipient_id
     * @return array {success: bool, results: array}
     */
    public function sendToMany(array $recipientIds, array $params): array
    {
        $results = [];
        $successCount = 0;

        foreach ($recipientIds as $recipientId) {
            $params['recipient_id'] = $recipientId;
            $r = $this->send($params);
            $results[] = ['recipient_id' => $recipientId, 'result' => $r];
            if ($r['success']) $successCount++;
        }

        return [
            'success'     => $successCount > 0,
            'sent_count'  => $successCount,
            'total'       => count($recipientIds),
            'results'     => $results,
        ];
    }

    /**
     * Send notification to all staff at an outlet (managers + staff roles).
     */
    public function sendToOutletStaff(string $outletId, string $companyId, array $params): array
    {
        $staffIds = $this->getOutletStaffIds($outletId, $companyId);

        if (empty($staffIds)) {
            return ['success' => false, 'message' => 'No staff found for outlet'];
        }

        $params['company_id'] = $companyId;
        $params['outlet_id']  = $outletId;
        return $this->sendToMany($staffIds, $params);
    }

    /**
     * Send notification to all admins of a company.
     */
    public function sendToCompanyAdmins(string $companyId, array $params): array
    {
        $adminIds = $this->getCompanyAdminIds($companyId);

        if (empty($adminIds)) {
            return ['success' => false, 'message' => 'No admins found for company'];
        }

        $params['company_id'] = $companyId;
        return $this->sendToMany($adminIds, $params);
    }

    /**
     * Send notification to customer(s) associated with a parcel (sender/receiver).
     */
    public function sendToCustomer(array $parcelData, string $title, string $body, array $extraData = []): array
    {
        $results = ['sender' => null, 'receiver' => null];

        // Notify sender
        if (!empty($parcelData['global_sender_id'])) {
            $results['sender'] = $this->sendPushToCustomer(
                $parcelData['global_sender_id'],
                'sender',
                $title,
                $body,
                array_merge($extraData, ['tracking_number' => $parcelData['track_number'] ?? ''])
            );
        }

        // Notify receiver
        if (!empty($parcelData['global_receiver_id'])) {
            $results['receiver'] = $this->sendPushToCustomer(
                $parcelData['global_receiver_id'],
                'receiver',
                $title,
                $body,
                array_merge($extraData, ['tracking_number' => $parcelData['track_number'] ?? ''])
            );
        }

        $anySuccess = ($results['sender']['success'] ?? false) || ($results['receiver']['success'] ?? false);
        return ['success' => $anySuccess, 'results' => $results];
    }

    /**
     * Send push to driver by driver_id.
     */
    public function sendToDriver(string $driverId, string $title, string $body, array $data = []): array
    {
        return $this->send([
            'recipient_id' => $driverId,
            'company_id'   => $data['company_id'] ?? null,
            'title'        => $title,
            'message'      => $body,
            'notification_type' => $data['notification_type'] ?? 'trip_assignment',
            'data'         => $data,
            'role_filter'  => 'driver',
            'tag'          => $data['tag'] ?? 'trip-assignment',
            'icon'         => $data['icon'] ?? '/outlet-app/drivers/icons/icon-192x192.png',
            'actions'      => $data['actions'] ?? [
                ['action' => 'view', 'title' => 'View Trip'],
                ['action' => 'dismiss', 'title' => 'Dismiss'],
            ],
        ]);
    }

    /**
     * Send push to outlet manager by manager profile ID.
     */
    public function sendToManager(string $managerId, string $title, string $body, array $data = []): array
    {
        return $this->send([
            'recipient_id' => $managerId,
            'company_id'   => $data['company_id'] ?? null,
            'title'        => $title,
            'message'      => $body,
            'notification_type' => $data['notification_type'] ?? 'manager_notification',
            'data'         => $data,
            'role_filter'  => 'outlet_manager',
            'tag'          => $data['tag'] ?? 'manager-notification',
            'icon'         => $data['icon'] ?? '/outlet-app/icons/icon-192x192.png',
            'actions'      => $data['actions'] ?? [
                ['action' => 'view', 'title' => 'View Details'],
                ['action' => 'dismiss', 'title' => 'Dismiss'],
            ],
        ]);
    }

    // ─── CONVENIENCE METHODS FOR COMMON EVENTS ──────────────────

    /**
     * Parcel created — notify outlet staff + optionally the customer.
     */
    public function onParcelCreated(array $parcel, ?string $createdBy = null): array
    {
        $trackNum = $parcel['track_number'] ?? '';
        $receiver = $parcel['receiver_name'] ?? 'customer';
        $results  = [];

        // Notify origin outlet staff
        if (!empty($parcel['origin_outlet_id']) && !empty($parcel['company_id'])) {
            $results['outlet_staff'] = $this->sendToOutletStaff(
                $parcel['origin_outlet_id'],
                $parcel['company_id'],
                [
                    'title'             => 'New Parcel Created',
                    'message'           => "Parcel $trackNum created for $receiver",
                    'notification_type' => 'parcel_created',
                    'parcel_id'         => $parcel['id'] ?? null,
                    'sender_id'         => $createdBy,
                    'priority'          => 'medium',
                    'data'              => [
                        'type'           => 'parcel_created',
                        'track_number'   => $trackNum,
                        'url'            => '/outlet-app/pages/parcel_details.php?id=' . ($parcel['id'] ?? ''),
                    ],
                ]
            );
        }

        return $results;
    }

    /**
     * Parcel status changed — notify relevant parties.
     */
    public function onParcelStatusChange(array $parcel, string $oldStatus, string $newStatus): array
    {
        $trackNum = $parcel['track_number'] ?? '';
        $priority = in_array($newStatus, ['dispatched', 'delivered', 'in_transit']) ? 'high' : 'medium';
        $results  = [];

        // Notify customer
        $results['customer'] = $this->sendToCustomer(
            $parcel,
            'Parcel Status Updated',
            "Your parcel $trackNum status: $newStatus",
            [
                'type'       => 'parcel_status_change',
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'url'        => '/customer-app/track_parcel.php?track=' . $trackNum,
            ]
        );

        return $results;
    }

    /**
     * Trip assigned — notify driver + origin/destination outlet managers.
     */
    public function onTripAssigned(array $tripData): array
    {
        $results = [];
        $tripId  = $tripData['trip_id'] ?? $tripData['id'] ?? '';
        $origin  = $tripData['origin_outlet_name'] ?? 'Origin';
        $dest    = $tripData['destination_outlet_name'] ?? 'Destination';
        $depart  = isset($tripData['departure_time'])
            ? date('g:i A', strtotime($tripData['departure_time']))
            : 'TBD';

        // Notify driver
        if (!empty($tripData['driver_id'])) {
            $results['driver'] = $this->sendToDriver(
                $tripData['driver_id'],
                'New Trip Assigned',
                "Trip from $origin to $dest — Departure: $depart",
                [
                    'type'    => 'trip_assignment',
                    'trip_id' => $tripId,
                    'url'     => '/outlet-app/drivers/dashboard.php?trip_id=' . $tripId,
                ]
            );
        }

        // Notify outlet manager
        if (!empty($tripData['outlet_manager_id'])) {
            $results['manager'] = $this->sendToManager(
                $tripData['outlet_manager_id'],
                'New Trip Created',
                "Trip from $origin to $dest needs approval — Departure: $depart",
                [
                    'type'    => 'trip_assignment_manager',
                    'trip_id' => $tripId,
                    'url'     => '/outlet-app/pages/manager_trips.php',
                    'actions' => [
                        ['action' => 'accept_trip', 'title' => 'Accept Trip'],
                        ['action' => 'view_trip', 'title' => 'View Details'],
                    ],
                ]
            );
        }

        return $results;
    }

    /**
     * Trip started — notify manager + customers with parcels on this trip.
     */
    public function onTripStarted(string $tripId, array $tripData): array
    {
        $results = [];

        if (!empty($tripData['outlet_manager_id'])) {
            $short   = substr($tripId, 0, 8);
            $origin  = $tripData['origin_outlet_name'] ?? 'origin';
            $dest    = $tripData['destination_outlet_name'] ?? 'destination';

            $results['manager'] = $this->sendToManager(
                $tripData['outlet_manager_id'],
                'Trip Started',
                "Trip $short moving from $origin to $dest",
                [
                    'type'    => 'trip_started',
                    'trip_id' => $tripId,
                    'url'     => '/outlet-app/pages/trips_manager.php?trip_id=' . $tripId,
                ]
            );
        }

        // Notify customers on each parcel
        if (!empty($tripData['parcel_ids'])) {
            $results['customers'] = $this->notifyCustomersForParcels(
                $tripData['parcel_ids'],
                'Parcel In Transit',
                'Your parcel is now on the way!',
                ['type' => 'trip_started']
            );
        }

        return $results;
    }

    /**
     * Delivery completed — notify customer.
     */
    public function onDeliveryCompleted(array $parcel): array
    {
        $trackNum = $parcel['track_number'] ?? '';
        return $this->sendToCustomer(
            $parcel,
            'Parcel Delivered',
            "Your parcel $trackNum has been delivered successfully!",
            [
                'type'  => 'delivery_completed',
                'url'   => '/customer-app/track_parcel.php?track=' . $trackNum,
            ]
        );
    }

    /**
     * Payment received — notify sender/receiver + outlet staff.
     */
    public function onPaymentReceived(array $parcel, array $payment): array
    {
        $trackNum = $parcel['track_number'] ?? '';
        $amount   = $payment['amount'] ?? 0;
        $method   = $payment['payment_method'] ?? 'unknown';

        $results = [];

        // Customer notification
        $results['customer'] = $this->sendToCustomer(
            $parcel,
            'Payment Received',
            "Payment of $amount confirmed for parcel $trackNum",
            ['type' => 'payment_received', 'amount' => $amount, 'method' => $method]
        );

        // Outlet staff
        if (!empty($parcel['origin_outlet_id']) && !empty($parcel['company_id'])) {
            $results['outlet_staff'] = $this->sendToOutletStaff(
                $parcel['origin_outlet_id'],
                $parcel['company_id'],
                [
                    'title'             => 'Payment Received',
                    'message'           => "Payment of $amount for $trackNum via $method",
                    'notification_type' => 'payment_received',
                    'parcel_id'         => $parcel['id'] ?? null,
                    'priority'          => 'medium',
                    'data'              => ['type' => 'payment_received', 'amount' => $amount],
                ]
            );
        }

        return $results;
    }

    // ─── PRIVATE: PUSH SENDING ──────────────────────────────────

    /**
     * Send push notification to a user's active subscriptions.
     */
    private function sendPush(string $userId, string $title, string $body, array $data, array $options = []): array
    {
        if (!$this->pushEnabled) {
            return ['success' => false, 'message' => 'Push notifications disabled (VAPID not configured)'];
        }

        $subscriptions = $this->getSubscriptions($userId, $options['role_filter'] ?? null);

        if (empty($subscriptions)) {
            error_log("[NotificationDispatcher] No active subscriptions for user: $userId");
            return ['success' => false, 'message' => 'No active subscriptions', 'sent_count' => 0];
        }

        $payload = json_encode([
            'title'              => $title,
            'body'               => $body,
            'icon'               => $options['icon'] ?? '/outlet-app/icons/icon-192x192.png',
            'badge'              => $options['badge'] ?? '/outlet-app/icons/icon-72x72.png',
            'data'               => $data,
            'actions'            => $options['actions'] ?? [],
            'tag'                => $options['tag'] ?? 'notification',
            'requireInteraction' => $options['require_interaction'] ?? false,
            'vibrate'            => [200, 100, 200],
            'timestamp'          => time() * 1000,
        ]);

        $successCount = 0;
        $failedIds    = [];

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'keys'     => [
                        'p256dh' => $sub['p256dh_key'],
                        'auth'   => $sub['auth_key'],
                    ],
                ]);

                $report = $this->webPush->sendOneNotification($subscription, $payload);

                if ($report->isSuccess()) {
                    $successCount++;
                } else {
                    $reason = $report->getReason();
                    error_log("[NotificationDispatcher] Push failed for sub {$sub['id']}: $reason");

                    // Auto-cleanup expired/invalid subscriptions
                    if ($report->isSubscriptionExpired()
                        || strpos($reason, '403') !== false
                        || strpos($reason, '410') !== false
                        || strpos($reason, 'VAPID') !== false
                    ) {
                        $failedIds[] = $sub['id'];
                    }
                }
            } catch (\Exception $e) {
                error_log("[NotificationDispatcher] Push exception for sub {$sub['id']}: " . $e->getMessage());
            }
        }

        // Batch-deactivate invalid subscriptions
        if (!empty($failedIds)) {
            $this->deactivateSubscriptions($failedIds);
        }

        return [
            'success'             => $successCount > 0,
            'sent_count'          => $successCount,
            'total_subscriptions' => count($subscriptions),
            'cleaned_up'          => count($failedIds),
        ];
    }

    /**
     * Send push to a customer (from global_customers) by user_id and role.
     */
    private function sendPushToCustomer(string $userId, string $role, string $title, string $body, array $data): array
    {
        if (!$this->pushEnabled) {
            return ['success' => false, 'message' => 'Push disabled'];
        }

        // Customers can subscribe as sender, receiver, or customer
        $roles = ['sender', 'receiver', 'customer'];
        $roleStr = implode(',', $roles);
        $query = "user_id=eq.$userId&user_role=in.($roleStr)&is_active=eq.true&select=*";

        $subscriptions = $this->supabaseGet('push_subscriptions', $query);

        if (empty($subscriptions)) {
            return ['success' => false, 'message' => 'No customer subscriptions'];
        }

        $payload = json_encode([
            'title'              => $title,
            'body'               => $body,
            'icon'               => 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>',
            'badge'              => 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📦</text></svg>',
            'data'               => $data,
            'tag'                => 'parcel-update',
            'requireInteraction' => true,
            'vibrate'            => [200, 100, 200, 100, 200],
        ]);

        $successCount = 0;
        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'keys'     => ['p256dh' => $sub['p256dh_key'], 'auth' => $sub['auth_key']],
                ]);
                $report = $this->webPush->sendOneNotification($subscription, $payload);
                if ($report->isSuccess()) {
                    $successCount++;
                } elseif ($report->isSubscriptionExpired()) {
                    $this->deactivateSubscriptions([$sub['id']]);
                }
            } catch (\Exception $e) {
                error_log("[NotificationDispatcher] Customer push error: " . $e->getMessage());
            }
        }

        return ['success' => $successCount > 0, 'sent_count' => $successCount];
    }

    /**
     * Notify customers for a list of parcel IDs.
     */
    private function notifyCustomersForParcels(array $parcelIds, string $title, string $defaultBody, array $extraData): array
    {
        $results = [];
        $idsStr  = implode(',', array_map('addslashes', $parcelIds));
        $parcels = $this->supabaseGet('parcels', "id=in.($idsStr)&select=id,track_number,global_sender_id,global_receiver_id,origin_outlet_id,destination_outlet_id");

        foreach ($parcels as $parcel) {
            $trackNum = $parcel['track_number'] ?? '';
            $body     = str_replace('{track}', $trackNum, $defaultBody);
            $data     = array_merge($extraData, [
                'tracking_number' => $trackNum,
                'url'             => '/customer-app/track_parcel.php?track=' . $trackNum,
            ]);
            $results[] = $this->sendToCustomer($parcel, $title, $body, $data);
        }

        return $results;
    }

    // ─── PRIVATE: DATABASE OPERATIONS ───────────────────────────

    private function createNotificationRecord(array $params): ?array
    {
        // Ensure notification_type conforms to allowed values in the DB check constraint.
        $allowedTypes = [
            'parcel_created', 'parcel_status_change', 'delivery_assigned', 'delivery_completed',
            'driver_unavailable', 'payment_received', 'urgent_delivery', 'system_alert', 'customer_inquiry'
        ];

        $requestedType = $params['notification_type'] ?? null;
        $mapping = [
            'trip_assignment' => 'delivery_assigned',
            'trip_assignment_outlet' => 'delivery_assigned',
            'trip_assignment_manager' => 'delivery_assigned',
            'trip_started' => 'urgent_delivery',
            'trip_arrived' => 'urgent_delivery',
            'trip_completed' => 'delivery_completed',
        ];

        if ($requestedType === null) {
            $finalType = 'system_alert';
        } elseif (!in_array($requestedType, $allowedTypes, true)) {
            $finalType = $mapping[$requestedType] ?? 'system_alert';
        } else {
            $finalType = $requestedType;
        }

        $record = [
            'company_id'        => $params['company_id'],
            'recipient_id'      => $params['recipient_id'],
            'title'             => $params['title'],
            'message'           => $params['message'],
            'notification_type' => $finalType,
            'priority'          => $params['priority'] ?? 'medium',
            'status'            => 'unread',
            'created_at'        => date('c'),
        ];

        // Optional fields
        foreach (['outlet_id', 'sender_id', 'parcel_id', 'delivery_id'] as $field) {
            if (!empty($params[$field])) {
                $record[$field] = $params[$field];
            }
        }

        if (!empty($params['data'])) {
            $record['data'] = is_string($params['data']) ? $params['data'] : json_encode($params['data']);
        }

        $result = $this->supabasePost('notifications', $record);
        return is_array($result) && !empty($result) ? $result[0] ?? $result : null;
    }

    private function logNotification(string $userId, string $title, string $body, array $data, string $status): void
    {
        try {
            $this->supabasePost('notification_logs', [
                'user_id'    => $userId,
                'title'      => $title,
                'body'       => $body,
                'data'       => json_encode($data),
                'status'     => $status,
                'sent_at'    => $status === 'sent' ? date('c') : null,
                'created_at' => date('c'),
            ]);
        } catch (\Exception $e) {
            error_log("[NotificationDispatcher] Log failed: " . $e->getMessage());
        }
    }

    private function getSubscriptions(string $userId, ?string $roleFilter = null): array
    {
        $query = "user_id=eq.$userId&is_active=eq.true&select=*";
        if ($roleFilter) {
            $query .= "&user_role=eq.$roleFilter";
        }

        return $this->supabaseGet('push_subscriptions', $query) ?: [];
    }

    /**
     * Return the number of unread notifications for a recipient.
     * @param string $recipientId
     * @return int
     */
    private function getUnreadCount(string $recipientId): int
    {
        $query = "recipient_id=eq.$recipientId&status=eq.unread&select=id";
        $rows = $this->supabaseGet('notifications', $query);
        if (!is_array($rows)) return 0;
        return count($rows);
    }

    private function deactivateSubscriptions(array $ids): void
    {
        foreach ($ids as $id) {
            try {
                $this->supabasePatch('push_subscriptions', ['is_active' => false, 'updated_at' => date('c')], "id=eq.$id");
                error_log("[NotificationDispatcher] Deactivated subscription: $id");
            } catch (\Exception $e) {
                error_log("[NotificationDispatcher] Deactivation failed for $id: " . $e->getMessage());
            }
        }
    }

    private function getOutletStaffIds(string $outletId, string $companyId): array
    {
        $outlet = $this->supabaseGet('outlets', "id=eq.$outletId&company_id=eq.$companyId&select=id,outlet_manager_id,manager_id");
        if (empty($outlet) || !is_array($outlet)) {
            return [];
        }

        $outlet = $outlet[0] ?? $outlet;
        $managerIds = array_filter([
            $outlet['outlet_manager_id'] ?? null,
            $outlet['manager_id'] ?? null,
        ]);

        $queryParts = [
            "company_id=eq.$companyId",
            'role=in.(outlet_manager,outlet_staff,admin)',
            'select=id',
        ];

        $conditions = ["outlet_id.eq.$outletId"];
        foreach ($managerIds as $managerId) {
            $conditions[] = "id.eq.$managerId";
        }

        if (!empty($conditions)) {
            $queryParts[] = 'or=(' . implode(',', $conditions) . ')';
        }

        $query = implode('&', $queryParts);
        $profiles = $this->supabaseGet('profiles', $query);

        return array_column($profiles ?: [], 'id');
    }

    private function getCompanyAdminIds(string $companyId): array
    {
        $profiles = $this->supabaseGet(
            'profiles',
            "company_id=eq.$companyId&role=in.(admin,company_admin)&select=id"
        );

        return array_column($profiles ?: [], 'id');
    }

    // ─── PRIVATE: SUPABASE HTTP HELPERS ─────────────────────────

    private function supabaseGet(string $table, string $query): array
    {
        $url = "{$this->supabaseUrl}/rest/v1/{$table}?{$query}";
        return $this->supabaseRequest('GET', $url) ?: [];
    }

    private function supabasePost(string $table, array $data): ?array
    {
        $url = "{$this->supabaseUrl}/rest/v1/{$table}";
        return $this->supabaseRequest('POST', $url, $data, ['Prefer: return=representation']);
    }

    private function supabasePatch(string $table, array $data, string $filter): ?array
    {
        $url = "{$this->supabaseUrl}/rest/v1/{$table}?{$filter}";
        return $this->supabaseRequest('PATCH', $url, $data);
    }

    private function supabaseRequest(string $method, string $url, ?array $data = null, array $extraHeaders = []): ?array
    {
        $ch = curl_init();

        $headers = array_merge([
            "apikey: {$this->supabaseKey}",
            "Authorization: Bearer {$this->supabaseKey}",
            'Content-Type: application/json',
        ], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Supabase cURL error: $error");
        }

        if ($httpCode >= 400) {
            throw new \Exception("Supabase HTTP $httpCode: $response");
        }

        return json_decode($response, true);
    }

    // ─── UTILITY ────────────────────────────────────────────────

    /**
     * Read env variable with fallback. Tries EnvLoader, getenv, $_ENV.
     */
    private function env(string $key, ?string $default = null): ?string
    {
        // Try EnvLoader if available
        if (class_exists('EnvLoader')) {
            $val = \EnvLoader::get($key);
            if ($val) return $val;
        }

        // Try getenv / $_ENV
        $val = getenv($key);
        if ($val !== false) return $val;

        return $_ENV[$key] ?? $default;
    }

    /**
     * Check if push notifications are enabled.
     */
    public function isPushEnabled(): bool
    {
        return $this->pushEnabled;
    }
}
