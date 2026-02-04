<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

// Pagination settings
$itemsPerPage = 25;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Fetch notifications
$allNotifications = [];
try {
    $allNotifications = callSupabaseWithServiceKey('notifications?order=created_at.desc', 'GET');
    $totalNotifications = count($allNotifications);
    $totalPages = ceil($totalNotifications / $itemsPerPage);
    
    // Ensure current page is within valid range
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
    }
    
    // Get notifications for current page
    $notifications = array_slice($allNotifications, $offset, $itemsPerPage);
} catch (Exception $e) {
    error_log('Error fetching notifications: ' . $e->getMessage());
    $notifications = [];
    $totalNotifications = 0;
    $totalPages = 1;
}

$pageTitle = 'Admin - Notifications';
require_once '../includes/header.php';
?>
    <div class="mobile-dashboard">
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="content-header">
                <h1>Notifications</h1>
            </div>

            <div class="notification-list">
                        <?php if (!empty($notifications) && is_array($notifications)): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo (isset($notification['is_read']) && $notification['is_read']) ? 'is-read' : ''; ?>" data-id="<?php echo htmlspecialchars($notification['id']); ?>">
                                    <div class="notification-content">
                                        <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <div class="notification-meta">
                                            <span><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if (!isset($notification['is_read']) || !$notification['is_read']): ?>
                                            <button onclick="markAsRead('<?php echo htmlspecialchars($notification['id']); ?>')">
                                                Mark as Read
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <p>No notifications found.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="pagination-container" style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 30px; flex-wrap: wrap;">
                        <a href="?page=1" 
                           class="pagination-btn" 
                           style="<?= $currentPage == 1 ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                           <?= $currentPage == 1 ? 'onclick="return false;"' : '' ?>>
                            <i class="fas fa-chevron-left"></i> First
                        </a>
                        
                        <a href="?page=<?= max(1, $currentPage - 1) ?>" 
                           class="pagination-btn" 
                           style="<?= $currentPage == 1 ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                           <?= $currentPage == 1 ? 'onclick="return false;"' : '' ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>

                        <div class="page-numbers" style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <?php
                            // Show page numbers (max 7 visible)
                            $startPage = max(1, $currentPage - 3);
                            $endPage = min($totalPages, $startPage + 6);
                            if ($endPage - $startPage < 6) {
                                $startPage = max(1, $endPage - 6);
                            }
                            
                            for ($page = $startPage; $page <= $endPage; $page++):
                            ?>
                                <a href="?page=<?= $page ?>"
                                   class="page-number"
                                   style="<?= $page == $currentPage ? 'background-color: #3b82f6; color: white; border-radius: 4px; padding: 5px 10px; cursor: default;' : 'padding: 5px 10px; border-radius: 4px; border: 1px solid #d1d5db;' ?>"
                                   <?= $page == $currentPage ? 'onclick="return false;"' : '' ?>>
                                    <?= $page ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <a href="?page=<?= min($totalPages, $currentPage + 1) ?>" 
                           class="pagination-btn" 
                           style="<?= $currentPage >= $totalPages ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                           <?= $currentPage >= $totalPages ? 'onclick="return false;"' : '' ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </a>

                        <a href="?page=<?= $totalPages ?>" 
                           class="pagination-btn" 
                           style="<?= $currentPage >= $totalPages ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                           <?= $currentPage >= $totalPages ? 'onclick="return false;"' : '' ?>>
                            Last <i class="fas fa-chevron-right"></i>
                        </a>

                    </div>
                </div>
            </main>
        </div>

    <script>
    async function markAsRead(notificationId) {
        const item = document.querySelector(`.notification-item[data-id='${notificationId}']`);
        try {
            const response = await fetch(`${adminBaseUrl}/api/notifications.php?action=mark_read&id=${notificationId}`, {
                method: 'POST'
            });
            const data = await response.json();
            if (data.success) {
                item.classList.add('is-read');
                const button = item.querySelector('button');
                if (button) {
                    button.remove();
                }
            } else {
                alert(data.error || 'Failed to mark as read.');
            }
        } catch (error) {
            alert('An error occurred: ' + error.message);
        }
    }
    </script>
    <script>
    </script>
</body>
</html>
</body>
</html>
