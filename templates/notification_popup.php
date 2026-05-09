<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['member_logged_in']) || $_SESSION['member_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 15px;
        }
        .notification-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .notification-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .notification-time {
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="notification-item">
        <div class="notification-title">Dance Classes Scheduled</div>
        <div>Dance classes are scheduled for May 1st at Utsav hall, JSS campus.</div>
        <div class="notification-time"><?php echo date('M j, Y g:i a'); ?></div>
    </div>
</body>
</html>