<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['member_logged_in'])) {
    header("Location: login.php");
    exit;
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'club management');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get member details from session
$member_email = $_SESSION['member_email'];
$member_name = $_SESSION['member_name'];

// Get member_id from database using email
$member_id = '';
$stmt = $conn->prepare("SELECT member_id FROM members WHERE m_email = ?");
if ($stmt) {
    $stmt->bind_param("s", $member_email);
    $stmt->execute();
    $stmt->bind_result($member_id);
    $stmt->fetch();
    $stmt->close();
}

// Initialize counts and calendar events
$event_count = 0;
$task_count = 0;
$calendar_events = [];

// Get count of UPCOMING events this member is assigned to
$current_date = date('Y-m-d');
$event_query = $conn->prepare("
    SELECT COUNT(*) 
    FROM workassignment wa
    JOIN event e ON wa.event_id = e.event_id
    WHERE wa.member_id = ? AND e.event_date >= ?
");
if ($event_query) {
    $event_query->bind_param("ss", $member_id, $current_date);
    $event_query->execute();
    $event_query->bind_result($event_count);
    $event_query->fetch();
    $event_query->close();
}

// Task count is same as event count (as per your existing logic)
$task_count = $event_count;

// Get all UPCOMING events for calendar
$calendar_query = $conn->prepare("
    SELECT 
        e.event_name AS title, 
        e.event_date AS start,
        CASE WHEN w.member_id IS NOT NULL THEN 'assigned' ELSE 'club' END AS event_type
    FROM 
        event e
    LEFT JOIN 
        workassignment w ON e.event_id = w.event_id AND w.member_id = ?
    WHERE
        e.event_date >= ?
    ORDER BY 
        e.event_date
");
if ($calendar_query) {
    $calendar_query->bind_param("ss", $member_id, $current_date);
    $calendar_query->execute();
    $result = $calendar_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['start'] = date('Y-m-d', strtotime($row['start']));
        $row['color'] = ($row['event_type'] === 'assigned') ? '#378006' : '#3498db';
        $calendar_events[] = $row;
    }
    $calendar_query->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --events-color: #27ae60;
            --tasks-color: #e67e22;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            display: flex;
        }

        .sidebar {
            width: 250px;
            background-color: var(--secondary-color);
            color: white;
            height: 100vh;
            position: fixed;
            padding-top: 20px;
        }

        .sidebar h2 {
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }

        .sidebar-menu li {
            padding: 10px 20px;
            cursor: pointer;
        }

        .sidebar-menu li:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            flex: 1;
        }

        .header {
            background: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .logout-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            flex: 1;
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }

        #totalEvents .stat-value {
            color: var(--events-color);
        }

        #totalTasks .stat-value {
            color: var(--tasks-color);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .photos-container, .calendar-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .view-photos-btn {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            cursor: pointer;
        }

        .calendar-container {
            min-height: 400px;
        }

        .fc {
            font-size: 0.9rem;
        }
        
        .fc-event {
            cursor: pointer;
            padding: 2px 5px;
            margin: 1px 0;
            border-radius: 3px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>Club Portal</h2>
    <ul class="sidebar-menu">
        <li onclick="window.location.href='profile.php'">Profile</li>
        <li onclick="window.location.href='events.php'">Events</li>
        <li onclick="window.location.href='tasks.php'">Tasks</li>
        <li onclick="window.location.href='feedback.php'">Feedback</li>
    </ul>
</div>

<div class="main-content">
    <div class="header">
        <h3>Welcome, <?php echo htmlspecialchars($member_name); ?></h3>
        <button class="logout-btn" onclick="window.location.href='member_logout.php'">Logout</button>
    </div>

    <div class="stats-container">
        <div class="stat-card" id="totalEvents">
            <div class="stat-value"><?php echo $event_count; ?></div>
            <p>Your Events</p>
        </div>
        <div class="stat-card" id="totalTasks">
            <div class="stat-value"><?php echo $task_count; ?></div>
            <p>Your Tasks</p>
        </div>
    </div>

    <div class="content-grid">
        <div class="photos-container">
            <h3>Club Photos</h3>
            <button class="view-photos-btn" onclick="window.location.href='member_photos.php'">View Club Photos</button>
        </div>
        <div class="calendar-container">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 350,
            headerToolbar: {
                left: 'prev,next',
                center: 'title',
                right: ''
            },
            events: <?php echo json_encode($calendar_events); ?>,
            eventDisplay: 'block',
            eventDidMount: function(info) {
                info.el.setAttribute('title', info.event.extendedProps.event_type === 'assigned' ? 
                    'Your assigned event' : 'Club event');
            }
        });
        calendar.render();
    });
</script>

</body>
</html>