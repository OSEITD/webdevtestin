<?php
    $page_title = 'Company - Notification';

    // Start session early
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    require_once __DIR__ . '/../api/supabase-client.php';
    include __DIR__ . '/../includes/header.php';

    $error = null;
    $notification = null;

    $companyId = $_SESSION['id'] ?? null;
    $accessToken = $_SESSION['access_token'] ?? null;
    $notificationId = isset($_GET['id']) ? trim($_GET['id']) : null;

    if (!$companyId) {
        $error = 'Not authenticated. Please log in.';
    } elseif (empty($notificationId)) {
        $error = 'No notification specified.';
    } else {
        try {
            $supabase = new SupabaseClient();
            // Fetch the notification by id first, then authorize against session values
            $endpoint = 'notifications?id=eq.' . urlencode($notificationId) . '&select=*';

            if ($accessToken && method_exists($supabase, 'getWithToken')) {
                $res = $supabase->getWithToken($endpoint, $accessToken);
            } else {
                $res = $supabase->getRecord($endpoint, true);
            }

            if (is_array($res) && count($res) > 0) {
                $notification = $res[0];

                // Authorize: allow if notification belongs to this company or to this user
                $notifCompanyId = $notification['company_id'] ?? $notification->company_id ?? null;
                $notifUserId = $notification['user_id'] ?? $notification->user_id ?? null;
                $sessionUserId = $_SESSION['user_id'] ?? $_SESSION['uid'] ?? null;

                $authorized = false;
                if (!empty($companyId) && $notifCompanyId && ((string)$notifCompanyId === (string)$companyId)) {
                    $authorized = true;
                } elseif (!empty($sessionUserId) && $notifUserId && ((string)$notifUserId === (string)$sessionUserId)) {
                    $authorized = true;
                }

                if (!$authorized) {
                    $notification = null;
                    $error = 'Notification not found or access denied.';
                } else {
                    // Auto mark as read server-side if not already read. Use put() which performs a PATCH.
                    $isRead = false;
                    if (is_array($notification)) {
                        $isRead = !empty($notification['read']);
                    } elseif (is_object($notification)) {
                        $isRead = !empty($notification->read);
                    }

                    if (!$isRead) {
                        try {
                            $readAt = date('Y-m-d H:i:s');
                            $payload = ['read' => true, 'read_at' => $readAt];
                            // The put method will PATCH the notifications record using the service role key when available
                            $supabase->put("notifications?id=eq." . urlencode($notificationId), $payload);

                            // Update local copy so UI reflects the change immediately
                            if (is_array($notification)) {
                                $notification['read'] = true;
                                $notification['read_at'] = $readAt;
                            } else {
                                $notification->read = true;
                                $notification->read_at = $readAt;
                            }
                        } catch (Exception $e) {
                            error_log('Auto-mark-as-read failed for notification ' . $notificationId . ': ' . $e->getMessage());
                            // Non-fatal: display the notification but don't interrupt page load
                        }
                    }
                }
            } else {
                $error = 'Notification not found or access denied.';
            }
        } catch (Exception $e) {
            error_log('Error fetching notification: ' . $e->getMessage());
            $error = 'Failed to load notification details: ' . $e->getMessage();
        }
    }
?>

<body class="bg-gray-100 min-h-screen">
    <div class="mobile-dashboard">
        <main class="main-content">
            <div class="content-header">
                <h1>Notification Details</h1>
            </div>

            <div class="details-card">
                <?php if ($error): ?>
                    <div class="detail-item">
                        <span class="label">Error:</span>
                        <span class="value"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php else: ?>
                    <h2><?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?></h2>

                    <div class="detail-item">
                        <span class="label">Type:</span>
                        <span class="value"><?php echo htmlspecialchars($notification['type'] ?? $notification['level'] ?? 'info'); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="label">Message:</span>
                        <div class="value"><?php echo nl2br(htmlspecialchars($notification['body'] ?? $notification['message'] ?? '')); ?></div>
                    </div>

                    <div class="detail-item">
                        <span class="label">Related:</span>
                        <?php
                            // Normalize resource type/id for array or object notification shapes
                            $resType = '';
                            $resId = null;
                            if (is_array($notification)) {
                                $resType = $notification['resource_type'] ?? $notification['resourceType'] ?? '';
                                $resId = $notification['resource_id'] ?? $notification['resourceId'] ?? null;
                            } elseif (is_object($notification)) {
                                $resType = $notification->resource_type ?? $notification->resourceType ?? '';
                                $resId = $notification->resource_id ?? $notification->resourceId ?? null;
                            }
                        ?>
                        <span class="value"><?php echo htmlspecialchars($resType); echo ($resId ? (' #' . htmlspecialchars($resId)) : ''); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="label">Status:</span>
                        <span class="value"><?php echo (!empty($notification['read']) ? 'Read' : 'Unread'); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="label">Received:</span>
                        <span class="value"><?php echo isset($notification['created_at']) ? htmlspecialchars(date('Y-m-d H:i', strtotime($notification['created_at']))) : 'N/A'; ?></span>
                    </div>

                    <div class="details-button-group">
                        <button class="action-btn secondary" onclick="window.location.href='notifications.php'">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button class="action-btn" id="markReadBtn"><?php echo (!empty($notification['read']) ? 'Mark Unread' : 'Mark Read'); ?></button>
                        <button class="action-btn danger" id="delNotifBtn">Delete</button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/company-scripts.js"></script>
    <script>
    (function(){
        const notifId = '<?php echo addslashes($notificationId ?? ''); ?>';
        const markBtn = document.getElementById('markReadBtn');
        const delBtn = document.getElementById('delNotifBtn');
        if (markBtn) {
            markBtn.addEventListener('click', async function(){
                try {
                    markBtn.disabled = true;
                    const res = await fetch('../api/mark_notification_read.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ id: notifId })
                    });
                    const j = await res.json();
                    if (j && j.success) {
                        window.location.reload();
                        return;
                    }
                    throw new Error(j && j.error ? j.error : 'Failed to mark read');
                } catch (err) {
                    console.error('Mark read error', err);
                    alert('Could not mark notification: ' + (err.message || err));
                    markBtn.disabled = false;
                }
            });
        }

        if (delBtn) {
            delBtn.addEventListener('click', async function(){
                if (!confirm('Delete this notification? This cannot be undone.')) return;
                try {
                    delBtn.disabled = true;
                    const res = await fetch('../api/delete_notification.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ id: notifId })
                    });
                    const j = await res.json();
                    if (j && j.success) {
                        alert('Notification deleted');
                        window.location.href = 'notifications.php';
                        return;
                    }
                    throw new Error(j && j.error ? j.error : 'Failed to delete');
                } catch (err) {
                    console.error('Delete notification error', err);
                    alert('Could not delete notification: ' + (err.message || err));
                    delBtn.disabled = false;
                }
            });
        }
    })();
    </script>
</body>
</html>
