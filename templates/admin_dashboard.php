<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Initialize notifications array in session if not exists
if (!isset($_SESSION['notifications'])) {
    $_SESSION['notifications'] = [];
}

// Database connection with error reporting
$conn = new mysqli('localhost', 'root', '', 'club management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify all required tables exist
$required_tables = ['admin', 'pending_approval', 'members', 'event', 'feedback', 'club_photos'];
foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$result || $result->num_rows == 0) {
        die("Error: Required table '$table' is missing from the database. Please create it first.");
    }
}

// Get admin details with error handling
$admin_email = $_SESSION['admin_email'] ?? '';
if (empty($admin_email)) {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

$stmt = $conn->prepare("SELECT admin_id, name, role FROM admin WHERE email = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if (!$stmt->bind_param("s", $admin_email)) {
    die("Bind failed: " . $stmt->error);
}

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

$admin_id = $admin['admin_id'];
$admin_name = htmlspecialchars($admin['name']);
$admin_role = htmlspecialchars($admin['role']);

// Safe counting function with error handling
function safeCount($conn, $table, $condition = '') {
    $query = "SELECT COUNT(*) FROM $table";
    if (!empty($condition)) {
        $query .= " WHERE $condition";
    }
    
    $result = $conn->query($query);
    if (!$result) {
        error_log("Query failed: " . $conn->error . " | Query: " . $query);
        return 0;
    }
    
    return $result->fetch_row()[0];
}

// Get counts - UPDATED MEMBER COUNT QUERY TO COUNT ALL MEMBERS WITHOUT STATUS CONDITION
$pending_count = safeCount($conn, 'pending_approval', "p_status = 'pending'");
$total_members = safeCount($conn, 'members'); // Removed the status condition
$events_count = safeCount($conn, 'event', "event_date >= CURDATE()");
$feedback_count = safeCount($conn, 'feedback'); // Count all feedback without status condition

// Get upcoming events (within 2 days) for notifications
$upcoming_events = $conn->query("
    SELECT event_name, event_date 
    FROM event 
    WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ORDER BY event_date ASC
");

// Clear existing notifications if they're older than 1 day
$_SESSION['notifications'] = array_filter($_SESSION['notifications'] ?? [], function($notification) {
    return strtotime($notification['time']) > strtotime('-1 day');
});

// Add upcoming events to notifications if they're not already there
if ($upcoming_events && $upcoming_events->num_rows > 0) {
    while ($event = $upcoming_events->fetch_assoc()) {
        $event_message = "Upcoming event: " . htmlspecialchars($event['event_name']) . " on " . date('M j', strtotime($event['event_date']));
        
        // Check if this notification already exists
        $exists = false;
        foreach ($_SESSION['notifications'] as $notification) {
            if ($notification['message'] === $event_message) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            array_unshift($_SESSION['notifications'], [
                'message' => $event_message,
                'time' => date('Y-m-d H:i:s')
            ]);
        }
    }
}

// Add welcome message if it's the first time in this session
if (!isset($_SESSION['welcome_shown'])) {
    array_unshift($_SESSION['notifications'], [
        'message' => "Welcome back, $admin_name! You have $pending_count pending approvals and $events_count upcoming events.",
        'time' => date('Y-m-d H:i:s')
    ]);
    $_SESSION['welcome_shown'] = true;
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['club_photo'])) {
    $target_dir = "uploads/club_photos/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . basename($_FILES['club_photo']['name']);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES['club_photo']['tmp_name']);
    if ($check === false) {
        $_SESSION['notifications'][] = [
            'message' => "File is not an image.",
            'time' => date('Y-m-d H:i:s')
        ];
        $uploadOk = 0;
    }
    
    // Check file size (5MB max)
    if ($_FILES['club_photo']['size'] > 5000000) {
        $_SESSION['notifications'][] = [
            'message' => "Sorry, your file is too large (max 5MB).",
            'time' => date('Y-m-d H:i:s')
        ];
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($imageFileType, $allowed_extensions)) {
        $_SESSION['notifications'][] = [
            'message' => "Sorry, only JPG, JPEG, PNG & GIF files are allowed.",
            'time' => date('Y-m-d H:i:s')
        ];
        $uploadOk = 0;
    }
    
    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES['club_photo']['tmp_name'], $target_file)) {
            // Insert into database
            $stmt = $conn->prepare("INSERT INTO club_photos (photo_path) VALUES (?)");
            $stmt->bind_param("s", $target_file);
            if ($stmt->execute()) {
                $_SESSION['notifications'][] = [
                    'message' => "The photo has been uploaded successfully.",
                    'time' => date('Y-m-d H:i:s')
                ];
            } else {
                $_SESSION['notifications'][] = [
                    'message' => "Sorry, there was an error saving to database.",
                    'time' => date('Y-m-d H:i:s')
                ];
            }
        } else {
            $_SESSION['notifications'][] = [
                'message' => "Sorry, there was an error uploading your file.",
                'time' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    header("Location: admin_dashboard.php");
    exit();
}

// Get club photos
$club_photos = $conn->query("SELECT * FROM club_photos ORDER BY uploaded_at DESC LIMIT 5");
if (!$club_photos) {
    error_log("Club photos query failed: " . $conn->error);
    $club_photos = [];
}

// Calendar navigation
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($current_month < 1 || $current_month > 12) {
    $current_month = date('n');
}
if ($current_year < 2000 || $current_year > 2100) {
    $current_year = date('Y');
}

// Calculate previous and next months
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Add sample notification (remove in production)
if (isset($_GET['test_notification'])) {
    array_unshift($_SESSION['notifications'], [
        'message' => "Test notification - System is working properly",
        'time' => date('Y-m-d H:i:s')
    ]);
    header("Location: admin_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #577590;
            --light: #f8f9fa;
            --dark: #212529;
            --dark-bg: #1a1a2e;
            --dark-card: #16213e;
            --dark-text: #e6e6e6;
            --glass: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        [data-theme="dark"] {
            --light: #1a1a2e;
            --dark-text: #e6e6e6;
            --dark: #f8f9fa;
            --glass: rgba(0, 0, 0, 0.2);
            --glass-border: rgba(0, 0, 0, 0.3);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] body {
            background-color: var(--dark-bg);
            color: var(--dark-text);
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            height: 100vh;
            position: fixed;
            width: 280px;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
            border-right: none;
        }

        [data-theme="dark"] .sidebar {
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.3);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: 1px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin: 5px 15px;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s;
        }

        /* Header */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .topbar {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .page-title h1 {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        [data-theme="dark"] .page-title h1 {
            color: var(--dark-text);
        }

        .page-title p {
            color: #6c757d;
            margin-bottom: 0;
        }

        [data-theme="dark"] .page-title p {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            background: white;
            transition: all 0.3s;
            overflow: hidden;
        }

        [data-theme="dark"] .card {
            background: var(--dark-card);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 20px;
            font-weight: 600;
            color: var(--dark);
        }

        [data-theme="dark"] .card-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--dark-text);
        }

        .card-body {
            padding: 20px;
        }

        /* Stats Cards */
        .stat-card {
            border-radius: 12px;
            color: white;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
            z-index: -1;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .stat-card h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card p {
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* Photo Gallery */
        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .photo-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            aspect-ratio: 1;
        }

        [data-theme="dark"] .photo-item {
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
        }

        .photo-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .photo-item:hover img {
            transform: scale(1.05);
        }

        .photo-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(67, 97, 238, 0.1);
            border: 2px dashed var(--primary);
            cursor: pointer;
        }

        [data-theme="dark"] .photo-upload {
            background: rgba(67, 97, 238, 0.2);
            border-color: var(--primary-light);
        }

        .photo-upload i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        [data-theme="dark"] .photo-upload i {
            color: var(--primary-light);
        }

        .photo-upload span {
            color: var(--primary);
            font-weight: 500;
            text-align: center;
        }

        [data-theme="dark"] .photo-upload span {
            color: var(--primary-light);
        }

        /* Quick Tasks */
        .task-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        [data-theme="dark"] .task-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .task-item:last-child {
            border-bottom: none;
        }

        .task-text {
            font-weight: 500;
            color: var(--dark);
        }

        [data-theme="dark"] .task-text {
            color: var(--dark-text);
        }

        /* Theme Toggle */
        .theme-toggle {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            padding: 5px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .theme-toggle i {
            padding: 8px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .theme-toggle .active {
            background: white;
            color: var(--primary);
        }

        [data-theme="dark"] .theme-toggle .active {
            background: var(--primary);
            color: white;
        }

        /* Notification */
        .notification-bell {
            position: relative;
            color: var(--dark);
            font-size: 1.25rem;
            margin-left: 15px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        [data-theme="dark"] .notification-bell {
            color: var(--dark-text);
        }

        .notification-bell:hover {
            background: rgba(67, 97, 238, 0.1);
        }

        [data-theme="dark"] .notification-bell:hover {
            background: rgba(67, 97, 238, 0.3);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        [data-theme="dark"] .notification-badge {
            border: 2px solid var(--dark-card);
        }

        .notification-dropdown {
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 0;
        }

        [data-theme="dark"] .notification-dropdown {
            background: var(--dark-card);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            color: var(--dark);
        }

        [data-theme="dark"] .notification-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--dark-text);
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        [data-theme="dark"] .notification-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .notification-item:hover {
            background: rgba(67, 97, 238, 0.05);
        }

        [data-theme="dark"] .notification-item:hover {
            background: rgba(67, 97, 238, 0.1);
        }

        .notification-message {
            margin-bottom: 5px;
            color: var(--dark);
        }

        [data-theme="dark"] .notification-message {
            color: var(--dark-text);
        }

        .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }

        [data-theme="dark"] .notification-time {
            color: rgba(255, 255, 255, 0.6);
        }

        .notification-empty {
            padding: 30px;
            text-align: center;
            color: #6c757d;
        }

        [data-theme="dark"] .notification-empty {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Calendar */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            text-align: center;
        }

        .calendar-day-header {
            font-weight: 600;
            font-size: 0.8rem;
            padding: 5px;
            color: var(--dark);
        }

        [data-theme="dark"] .calendar-day-header {
            color: var(--dark-text);
        }

        .calendar-day {
            padding: 8px;
            border-radius: 5px;
            font-size: 0.9rem;
            color: var(--dark);
        }

        [data-theme="dark"] .calendar-day {
            color: var(--dark-text);
        }

        .calendar-day.current {
            background: var(--primary);
            color: white;
            font-weight: bold;
        }

        .calendar-day.empty {
            visibility: hidden;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .calendar-title {
            font-weight: 600;
            margin: 0;
        }

        .calendar-nav-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        [data-theme="dark"] .calendar-nav-btn {
            color: var(--primary-light);
        }

        .calendar-nav-btn:hover {
            background: rgba(67, 97, 238, 0.1);
        }

        [data-theme="dark"] .calendar-nav-btn:hover {
            background: rgba(67, 97, 238, 0.2);
        }

        /* Notification Popup */
    
.notification-popup {
    position: fixed;
    top: 80px; /* Position below the header */
    right: 20px;
    z-index: 1100;
    max-width: 350px;
    animation: fadeIn 0.5s ease forwards;
    background-color: var(--primary);
    color: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

[data-theme="dark"] .notification-popup {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
}

.notification-popup-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.notification-popup h5 {
    margin: 0;
    font-weight: 600;
}

.notification-popup .btn-close {
    filter: invert(1);
    opacity: 0.8;
}

.notification-popup p {
    margin: 0;
}

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(20px); }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }
            
            .notification-dropdown {
                width: 280px;
                right: -100px !important;
            }

            .photo-gallery {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .notification-popup {
                max-width: calc(100% - 40px);
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
    </style>
</head>
<body data-theme="light">
    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <div class="d-flex flex-column h-100">
            <div class="sidebar-header">
                <div class="sidebar-brand">ClubAdmin</div>
                <small class="text-white-50">Management System</small>
            </div>
            
            <div class="sidebar-menu">
                <ul class="nav nav-pills flex-column mb-auto">
                    <li class="nav-item">
                        <a href="admin_dashboard.php" class="nav-link active">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_members.php" class="nav-link">
                            <i class="fas fa-users"></i> Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="pending_approvals.php" class="nav-link">
                            <i class="fas fa-user-clock"></i> Pending Approvals
                            <?php if($pending_count > 0): ?>
                                <span class="badge bg-danger float-end"><?= $pending_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="create_event.php" class="nav-link">
                            <i class="fas fa-calendar-plus"></i> Create Event
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="work_assignments.php" class="nav-link">
                            <i class="fas fa-tasks"></i> Work Assignments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="member_feedback.php" class="nav-link">
                            <i class="fas fa-comment-alt"></i> Member Feedback
                            <?php if($feedback_count > 0): ?>
                                <span class="badge bg-danger float-end"><?= $feedback_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                </ul>
            </div>
            
            <div class="mt-auto p-3">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <img src="https://via.placeholder.com/40" alt="Admin" width="40" height="40" class="rounded-circle me-2">
                        <div>
                            <strong><?= $admin_name ?></strong>
                            <small class="d-block"><?= $admin_role ?></small>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="admin_profile.php?id=<?= $admin_id ?>"><i class="fas fa-user-cog me-2"></i>Profile Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="topbar">
            <div>
                <button class="btn btn-link text-dark d-lg-none me-2" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title">
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?= $admin_name ?>! Here's what's happening today.</p>
                </div>
            </div>
            
            <div class="d-flex align-items-center">
                <div class="theme-toggle me-3" id="themeToggle">
                    <i class="fas fa-sun active"></i>
                    <i class="fas fa-moon"></i>
                </div>
                
                <!-- Notification Bell -->
                <div class="dropdown">
                    <a href="#" class="notification-bell" data-bs-toggle="dropdown" id="notificationDropdown">
                        <i class="fas fa-bell"></i>
                        <?php if (!empty($_SESSION['notifications'])): ?>
                            <span class="notification-badge"><?= count($_SESSION['notifications']) ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                        <li class="notification-header">Notifications</li>
                        <?php if (empty($_SESSION['notifications'])): ?>
                            <li class="notification-empty">No new notifications</li>
                        <?php else: ?>
                            <?php foreach ($_SESSION['notifications'] as $index => $notification): ?>
                                <li class="notification-item">
                                    <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                                    <div class="notification-time">
                                        <?= date('M j, g:i a', strtotime($notification['time'])) ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            <li class="dropdown-divider"></li>
                            <li class="text-center py-2">
                                <small class="text-muted">Showing last <?= count($_SESSION['notifications']) ?> notifications</small>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 fade-in delay-1">
                <div class="stat-card" style="background: linear-gradient(135deg, var(--primary), var(--primary-light));">
                    <i class="fas fa-users"></i>
                    <h3><?= $total_members ?></h3>
                    <p>Total Members</p>
                </div>
            </div>
            <div class="col-md-3 fade-in delay-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #f8961e, #f9c74f);">
                    <i class="fas fa-user-clock"></i>
                    <h3><?= $pending_count ?></h3>
                    <p>Pending Approvals</p>
                </div>
            </div>
            <div class="col-md-3 fade-in delay-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #577590, #43aa8b);">
                    <i class="fas fa-calendar-alt"></i>
                    <h3><?= $events_count ?></h3>
                    <p>Upcoming Events</p>
                </div>
            </div>
            <div class="col-md-3 fade-in delay-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                    <i class="fas fa-comment-alt"></i>
                    <h3><?= $feedback_count ?></h3>
                    <p>New Feedback</p>
                </div>
            </div>
        </div>
        
        <!-- Club Photos Section -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card fade-in">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-images me-2"></i> Club Photos</span>
                        <a href="club_photos.php" class="btn btn-sm btn-link">View All</a>
                    </div>
                    <div class="card-body">
                        <form action="admin_dashboard.php" method="post" enctype="multipart/form-data" class="mb-4">
                            <div class="photo-gallery">
                                <label for="photo-upload" class="photo-item photo-upload">
                                    <i class="fas fa-plus"></i>
                                    <span>Upload Photo</span>
                                    <input type="file" id="photo-upload" name="club_photo" accept="image/*" style="display: none;" required>
                                </label>
                                
                                <?php if(is_object($club_photos) && $club_photos->num_rows > 0): ?>
                                    <?php while($photo = $club_photos->fetch_assoc()): ?>
                                        <div class="photo-item">
                                            <img src="<?= htmlspecialchars($photo['photo_path']) ?>" alt="Club Photo">
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="col-12 text-center py-4">
                                        <i class="fas fa-images fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No photos uploaded yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card fade-in">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-tasks me-2"></i> Quick Tasks</span>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="task-item">
                                <span class="task-text">Review pending approvals</span>
                                <a href="pending_approvals.php" class="btn btn-sm btn-outline-primary rounded-pill">Go</a>
                            </li>
                            <li class="task-item">
                                <span class="task-text">View member feedback</span>
                                <a href="member_feedback.php" class="btn btn-sm btn-outline-primary rounded-pill">Go</a>
                            </li>
                            <li class="task-item">
                                <span class="task-text">Create new event</span>
                                <a href="create_event.php" class="btn btn-sm btn-outline-primary rounded-pill">Go</a>
                            </li>
                            <li class="task-item">
                                <span class="task-text">Manage club photos</span>
                                <a href="club_photos.php" class="btn btn-sm btn-outline-primary rounded-pill">Go</a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Mini Calendar -->
                <div class="card mt-4 fade-in">
                    <div class="card-header">
                        <i class="fas fa-calendar me-2"></i> Calendar
                    </div>
                    <div class="card-body">
                        <div class="calendar-nav">
                            <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="calendar-nav-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <h5 class="calendar-title"><?= date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)) ?></h5>
                            <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" class="calendar-nav-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        <div class="calendar-grid">
                            <?php
                            $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                            foreach ($days as $day) {
                                echo "<div class='calendar-day-header'>$day</div>";
                            }
                            
                            $firstDay = date('w', strtotime("$current_year-$current_month-01"));
                            $daysInMonth = date('t', strtotime("$current_year-$current_month-01"));
                            
                            // Empty cells before first day
                            for ($i = 0; $i < $firstDay; $i++) {
                                echo "<div class='calendar-day empty'></div>";
                            }
                            
                            // Days of month
                            for ($day = 1; $day <= $daysInMonth; $day++) {
                                $isCurrentDay = ($day == date('j') && $current_month == date('n') && $current_year == date('Y')) ? 'current' : '';
                                echo "<div class='calendar-day $isCurrentDay'>$day</div>";
                            }
                            
                            // Fill remaining cells
                            $totalCells = 42; // 6 rows x 7 days
                            $remainingCells = $totalCells - $firstDay - $daysInMonth;
                            for ($i = 0; $i < $remainingCells; $i++) {
                                echo "<div class='calendar-day empty'></div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        
        themeToggle.addEventListener('click', function() {
            const isDark = body.getAttribute('data-theme') === 'dark';
            body.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
            
            // Update toggle icons
            const icons = themeToggle.querySelectorAll('i');
            icons.forEach(icon => icon.classList.toggle('active'));
        });

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        body.setAttribute('data-theme', savedTheme);
        
        // Set initial toggle state
        if (savedTheme === 'dark') {
            const icons = themeToggle.querySelectorAll('i');
            icons[0].classList.remove('active');
            icons[1].classList.add('active');
        }

        // Auto-close notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            notificationDropdown.addEventListener('shown.bs.dropdown', function() {
                setTimeout(() => {
                    const dropdown = bootstrap.Dropdown.getInstance(notificationDropdown);
                    if (dropdown) dropdown.hide();
                }, 5000);
            });
        });

        // Add animation class to elements
        const animateElements = document.querySelectorAll('.fade-in');
        animateElements.forEach((el, index) => {
            el.style.animationDelay = `${index * 0.1}s`;
        });

        // Photo upload preview
        document.getElementById('photo-upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const uploadLabel = document.querySelector('.photo-upload');
                    uploadLabel.innerHTML = `
                        <img src="${event.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">
                        <span class="position-absolute" style="bottom:10px;left:0;right:0;color:white;font-weight:600;">Change Photo</span>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });

        // Show welcome popup
       
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($_SESSION['welcome_shown']) && !empty($_SESSION['notifications'])): ?>
        const welcomeMessage = `<?= addslashes($_SESSION['notifications'][0]['message']) ?>`;
        
        if (welcomeMessage) {
            // Create popup element
            const popup = document.createElement('div');
            popup.className = 'notification-popup';
            popup.style.top = '80px'; // Position below the header
            popup.style.right = '20px';
            popup.innerHTML = `
                <div class="notification-popup-header">
                    <h5><i class="fas fa-bell me-2"></i>Notification</h5>
                    <button type="button" class="btn-close"></button>
                </div>
                <p>${welcomeMessage}</p>
            `;
            
            document.body.appendChild(popup);
            
            // Add close functionality
            const closeBtn = popup.querySelector('.btn-close');
            closeBtn.addEventListener('click', function() {
                popup.style.animation = 'fadeOut 0.5s ease forwards';
                setTimeout(() => popup.remove(), 500);
            });
            
            // Auto-close after 5 seconds
            setTimeout(() => {
                popup.style.animation = 'fadeOut 0.5s ease forwards';
                setTimeout(() => popup.remove(), 500);
            }, 5000);
        }
    <?php endif; ?>
});
    </script>
</body>
</html>
<?php $conn->close(); ?>