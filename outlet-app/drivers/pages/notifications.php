<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}
$driverName = $_SESSION['full_name'] ?? 'Driver';
$pageTitle = "Notifications - $driverName";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .notifications-main { max-width: 600px; margin: 2rem auto; }
        .notification-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #eee; padding: 1.5rem; margin-bottom: 1rem; }
        .notification-title { font-weight: 600; margin-bottom: 0.5rem; }
        .notification-time { color: #6b7280; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .notification-message { margin-bottom: 0.5rem; }
        .notification-unread { background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="driver-app" id="driverApp">
        <?php include '../includes/navbar.php'; ?>
        <main class="notifications-main">
            <h1>Notifications</h1>
            <div id="notificationsList">
                <p>Loading notifications...</p>
            </div>
        </main>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            const notifications = [
                { id: 1, title: 'New Trip Assigned', message: 'You have been assigned to Trip TRIP12345.', time: '2025-09-23 08:00', unread: true },
                { id: 2, title: 'Parcel Added', message: 'Parcel PCL1001 added to your trip.', time: '2025-09-23 08:15', unread: false },
                { id: 3, title: 'Trip Rescheduled', message: 'Trip TRIP12346 has been rescheduled to tomorrow.', time: '2025-09-22 17:00', unread: true }
            ];
            const notificationsList = document.getElementById('notificationsList');
            if (notifications.length) {
                notificationsList.innerHTML = notifications.map(n => `
                    <div class="notification-card${n.unread ? ' notification-unread' : ''}">
                        <div class="notification-title">${n.title}</div>
                        <div class="notification-time">${n.time}</div>
                        <div class="notification-message">${n.message}</div>
                        <button class="btn-secondary" onclick="markRead(${n.id})">Mark as Read</button>
                    </div>
                `).join('');
            } else {
                notificationsList.innerHTML = '<p>No notifications.</p>';
            }
        });
        function markRead(id) {
            alert('Mark as read (feature coming soon)');
        }
        </script>
    </div>
</body>
</html>
