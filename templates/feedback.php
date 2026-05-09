 <?php
session_start();

// 1. Verify Member is Logged In
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Database Connection
require_once __DIR__ . '/../includes/db_connect.php';

// 3. Get Member ID from Session
$member_id = $_SESSION['member_id'];

// 4. Fetch Approved Events for This Member
$events = [];
try {
    $stmt = $conn->prepare("
        SELECT e.event_id, e.event_name, e.event_date 
        FROM event e
        JOIN workassignment wa ON e.event_id = wa.event_id
        WHERE wa.member_id = ? AND wa.w_status = 'approved'
        ORDER BY e.event_date DESC
    ");
    $stmt->bind_param("s", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// 5. Handle Feedback Submission
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $event_id = $_POST['event_id'] ?? '';
    $feedback_text = trim($_POST['feedback_text'] ?? '');
    
    // Validation
    if (empty($event_id)) {
        $error = "Please select an event";
    } elseif (empty($feedback_text)) {
        $error = "Please enter your feedback";
    } else {
        try {
            // Check for existing feedback
            $check_stmt = $conn->prepare("
                SELECT feedback_id FROM feedback 
                WHERE member_id = ? AND event_id = ?
            ");
            $check_stmt->bind_param("ss", $member_id, $event_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "You've already submitted feedback for this event";
            } else {
                // Get next feedback_id
                $id_stmt = $conn->query("SELECT MAX(feedback_id) as max_id FROM feedback");
                $id_result = $id_stmt->fetch_assoc();
                $next_id = ($id_result['max_id'] ?? 0) + 1;
                
                // Insert new feedback
                $insert_stmt = $conn->prepare("
                    INSERT INTO feedback (feedback_id, member_id, event_id, feedback, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insert_stmt->bind_param("isss", $next_id, $member_id, $event_id, $feedback_text);
                
                if ($insert_stmt->execute()) {
                    $success = "Thank you for your feedback!";
                    // Clear form
                    $_POST['event_id'] = '';
                    $_POST['feedback_text'] = '';
                } else {
                    $error = "Failed to submit feedback. Please try again.";
                }
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Feedback System</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .alert {
            padding: 15px;
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 16px;
        }
        select {
            height: 40px;
            background-color: white;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
        }
        textarea {
            min-height: 150px;
            resize: vertical;
        }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #2980b9;
        }
        .no-events {
            text-align: center;
            padding: 30px;
            color: #666;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Submit Event Feedback</h1>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($events)): ?>
            <form method="POST" action="feedback.php">
                <div class="form-group">
                    <label for="event_id">Select Event:</label>
                    <select name="event_id" id="event_id" required>
                        <option value="">-- Select an Event --</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo htmlspecialchars($event['event_id']); ?>"
                                <?php echo (isset($_POST['event_id']) && $_POST['event_id'] == $event['event_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event['event_name']); ?> 
                                (<?php echo date('M j, Y', strtotime($event['event_date'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="feedback_text">Your Feedback:</label>
                    <textarea name="feedback_text" id="feedback_text" placeholder="Please share your thoughts about the event..." required><?php echo htmlspecialchars($_POST['feedback_text'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" name="submit_feedback">Submit Feedback</button>
            </form>
        <?php else: ?>
            <div class="no-events">
                <p>No events available for feedback at this time.</p>
                <p>You can submit feedback for events you've participated in after they're approved by the admin.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>