<?php
session_start();

// Verify admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
require_once __DIR__ . '/../includes/db_connect.php';

// Initialize variables
$search_term = '';
$feedback_list = [];
$error = '';
$success = '';

// First, delete any feedback older than 5 days
try {
    $delete_old = $conn->prepare("DELETE FROM feedback WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 DAY)");
    $delete_old->execute();
    $rows_affected = $delete_old->affected_rows;
    if ($rows_affected > 0) {
        $success = "Automatically deleted $rows_affected feedback entries older than 5 days.";
    }
} catch (Exception $e) {
    $error = "Database error during auto-cleanup: " . $e->getMessage();
}

// Handle search and delete operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['search'])) {
        $search_term = trim($_POST['search_term']);
        
        try {
            $stmt = $conn->prepare("
                SELECT f.feedback_id, f.feedback, f.created_at,
                       m.member_id, m.m_name,
                       e.event_id, e.event_name,
                       DATEDIFF(NOW(), f.created_at) as days_old
                FROM feedback f
                JOIN members m ON f.member_id = m.member_id
                JOIN event e ON f.event_id = e.event_id
                WHERE m.member_id LIKE ? OR m.m_name LIKE ? OR e.event_id LIKE ? OR e.event_name LIKE ?
                ORDER BY f.created_at DESC
            ");
            $search_param = "%$search_term%";
            $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
            $stmt->execute();
            $result = $stmt->get_result();
            $feedback_list = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_feedback'])) {
        $feedback_id = $_POST['feedback_id'] ?? 0;
        
        try {
            $stmt = $conn->prepare("DELETE FROM feedback WHERE feedback_id = ?");
            $stmt->bind_param("i", $feedback_id);
            if ($stmt->execute()) {
                $success = "Feedback deleted successfully";
            } else {
                $error = "Failed to delete feedback";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// If no search, get all feedback
if (empty($feedback_list)) {
    try {
        $stmt = $conn->prepare("
            SELECT f.feedback_id, f.feedback, f.created_at,
                   m.member_id, m.m_name,
                   e.event_id, e.event_name,
                   DATEDIFF(NOW(), f.created_at) as days_old
            FROM feedback f
            JOIN members m ON f.member_id = m.member_id
            JOIN event e ON f.event_id = e.event_id
            ORDER BY f.created_at DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $feedback_list = $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Feedback Management</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-box button {
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .feedback-item {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            position: relative;
        }
        .feedback-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.9em;
            color: #666;
        }
        .feedback-content {
            margin-bottom: 10px;
        }
        .delete-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .days-remaining {
            font-weight: bold;
            color: #e67e22;
        }
        .expires-soon {
            background-color: #fff8e1;
        }
        .expired {
            background-color: #ffebee;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Feedback Management</h1>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="search-box">
            <input type="text" name="search_term" placeholder="Search by member name, ID, or event..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" name="search">Search</button>
        </form>
        
        <?php if (!empty($feedback_list)): ?>
            <?php foreach ($feedback_list as $feedback): 
                $days_remaining = 5 - $feedback['days_old'];
                $expires_class = '';
                if ($days_remaining <= 0) {
                    $expires_class = 'expired';
                } elseif ($days_remaining <= 2) {
                    $expires_class = 'expires-soon';
                }
            ?>
                <div class="feedback-item <?php echo $expires_class; ?>">
                    <div class="feedback-meta">
                        <div>
                            <strong>Member:</strong> 
                            <?php echo htmlspecialchars($feedback['m_name']); ?> 
                            (ID: <?php echo htmlspecialchars($feedback['member_id']); ?>)
                        </div>
                        <div>
                            <strong>Event:</strong> 
                            <?php echo htmlspecialchars($feedback['event_name']); ?> 
                            (ID: <?php echo htmlspecialchars($feedback['event_id']); ?>)
                        </div>
                        <div>
                            <strong>Date:</strong> 
                            <?php echo date('M j, Y g:i a', strtotime($feedback['created_at'])); ?>
                            <span class="days-remaining">
                                (<?php echo $days_remaining > 0 ? "Expires in $days_remaining day(s)" : "Expired"; ?>)
                            </span>
                        </div>
                    </div>
                    <div class="feedback-content">
                        <?php echo nl2br(htmlspecialchars($feedback['feedback'])); ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                        <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                        <button type="submit" name="delete_feedback" class="delete-btn">Delete Feedback</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No feedback found.</p>
        <?php endif; ?>
    </div>
</body>
</html>