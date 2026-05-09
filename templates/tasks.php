<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['member_logged_in']) || $_SESSION['member_logged_in'] !== true) {
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'accept') {
            $assignment_id = $_POST['assignment_id'];
            $stmt = $conn->prepare("UPDATE workassignment SET w_status = 'approved' WHERE assignment_id = ? AND member_id = ?");
            $stmt->bind_param("is", $assignment_id, $member_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['message'] = "Task accepted successfully!";
        } 
        elseif ($_POST['action'] === 'reject') {
            $assignment_id = $_POST['assignment_id'];
            $stmt = $conn->prepare("UPDATE workassignment SET w_status = 'rejected' WHERE assignment_id = ? AND member_id = ?");
            $stmt->bind_param("is", $assignment_id, $member_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['message'] = "Task rejected successfully!";
        }
        elseif ($_POST['action'] === 'pay' && isset($_POST['transaction_id'])) {
            $event_id = $_POST['event_id'];
            $transaction_id = $_POST['transaction_id'];
            
            // First check if there's an existing assignment for this event and member
            $check_stmt = $conn->prepare("SELECT assignment_id FROM workassignment WHERE event_id = ? AND member_id = ?");
            $check_stmt->bind_param("ss", $event_id, $member_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing assignment
                $row = $result->fetch_assoc();
                $update_stmt = $conn->prepare("UPDATE workassignment SET payment_status = 'completed', transaction_id = ? WHERE assignment_id = ?");
                $update_stmt->bind_param("si", $transaction_id, $row['assignment_id']);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                // Create new assignment record for payable event
                $insert_stmt = $conn->prepare("INSERT INTO workassignment (event_id, member_id, description, w_status, payment_status, transaction_id) VALUES (?, ?, 'Event participation', 'approved', 'completed', ?)");
                $insert_stmt->bind_param("sss", $event_id, $member_id, $transaction_id);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            
            $check_stmt->close();
            $_SESSION['message'] = "Payment recorded successfully!";
        }
        header("Location: tasks.php");
        exit;
    }
}

// Get current date
$current_date = date('Y-m-d');

// Get all tasks and payable events in a single query with combined information
$tasks = [];
$query = "
    SELECT 
        e.event_id,
        e.event_name,
        e.event_date,
        e.member_share,
        w.assignment_id,
        w.description AS task_description,
        w.w_status,
        w.payment_status,
        w.transaction_id,
        CASE 
            WHEN w.assignment_id IS NOT NULL THEN 'assigned'
            WHEN e.member_share > 0 THEN 'payable'
            ELSE 'other'
        END AS type
    FROM 
        event e
    LEFT JOIN 
        workassignment w ON e.event_id = w.event_id AND w.member_id = ?
    WHERE 
        (w.assignment_id IS NOT NULL OR e.member_share > 0)
        AND (w.payment_status IS NULL OR w.payment_status != 'completed')
        AND e.event_date >= ?  -- Only include future or today's events
    ORDER BY 
        e.event_date ASC  -- Sort by date ascending to show nearest events first
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ss", $member_id, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Group events by event_id to avoid duplicates
    $grouped_tasks = [];
    while ($row = $result->fetch_assoc()) {
        $event_id = $row['event_id'];
        if (!isset($grouped_tasks[$event_id])) {
            $grouped_tasks[$event_id] = $row;
        } else {
            // Merge assignment details if multiple records exist for same event
            if ($row['assignment_id']) {
                $grouped_tasks[$event_id]['assignment_id'] = $row['assignment_id'];
                $grouped_tasks[$event_id]['task_description'] = $row['task_description'];
                $grouped_tasks[$event_id]['w_status'] = $row['w_status'];
                $grouped_tasks[$event_id]['payment_status'] = $row['payment_status'];
                $grouped_tasks[$event_id]['transaction_id'] = $row['transaction_id'];
                $grouped_tasks[$event_id]['type'] = 'assigned';
            }
        }
    }
    $tasks = array_values($grouped_tasks);
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Portal - My Tasks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .sidebar {
            width: 200px;
            background-color: #2c3e50;
            color: white;
            position: fixed;
            height: 100%;
            padding: 20px;
        }
        .sidebar h2 { text-align: center; margin-bottom: 30px; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li { padding: 10px 0; cursor: pointer; }
        .sidebar-menu li:hover { color: #3498db; }
        .main-content { margin-left: 240px; padding: 20px; }
        .task-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .task-title { font-weight: bold; font-size: 1.1em; margin-bottom: 5px; }
        .task-date { color: #666; font-size: 0.9em; margin-bottom: 10px; }
        .task-info { margin-bottom: 5px; }
        .amount-section { margin-top: 10px; }
        .amount { font-weight: bold; }
        .action-buttons { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-accept { background-color: #27ae60; color: white; }
        .btn-reject { background-color: #e74c3c; color: white; }
        .btn-pay { background-color: #3498db; color: white; }
        .btn-paid { background-color: #27ae60; color: white; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 300px;
        }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .modal-input {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal-submit {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .transaction-id {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>Club Portal</h2>
    <ul class="sidebar-menu">
        <li>Dashboard</li>
        <li>Events</li>
        <li>Tasks</li>
        <li>Feedback</li>
    </ul>
</div>

<div class="main-content">
    <h3>My Upcoming Tasks and Payments</h3>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div style="color: green; margin-bottom: 15px;"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    
    <?php if (empty($tasks)): ?>
        <p>You don't have any upcoming tasks or payments due.</p>
    <?php else: ?>
        <?php foreach ($tasks as $task): ?>
            <div class="task-card">
                <div class="task-title"><?php echo htmlspecialchars($task['event_name']); ?></div>
                <div class="task-date"><?php echo date('M j, Y', strtotime($task['event_date'])); ?></div>
                
                <?php if ($task['type'] === 'assigned'): ?>
                    <div class="task-info">
                        <strong>Assigned Work:</strong> <?php echo htmlspecialchars($task['task_description']); ?>
                    </div>
                    
                    <div class="task-info">
                        <strong>Status:</strong> <?php echo ucfirst($task['w_status'] ?? 'N/A'); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($task['member_share'] > 0): ?>
                    <div class="amount-section">
                        <span class="amount">Amount Due: ₹<?php echo number_format($task['member_share'], 2); ?></span>
                        <?php if (!empty($task['transaction_id'])): ?>
                            <div class="transaction-id">
                                <strong>Transaction ID:</strong> <?php echo htmlspecialchars($task['transaction_id']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <?php if ($task['type'] === 'assigned' && $task['w_status'] === 'pending'): ?>
                        <form method="post">
                            <input type="hidden" name="assignment_id" value="<?php echo $task['assignment_id']; ?>">
                            <button type="submit" name="action" value="accept" class="btn btn-accept">Accept Work</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="assignment_id" value="<?php echo $task['assignment_id']; ?>">
                            <button type="submit" name="action" value="reject" class="btn btn-reject">Reject Work</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($task['member_share'] > 0 && (empty($task['payment_status']) || $task['payment_status'] === 'pending')): ?>
                        <button class="btn btn-pay" onclick="showPaymentModal('<?php echo $task['event_id']; ?>')">Mark as Paid</button>
                    <?php elseif ($task['member_share'] > 0 && $task['payment_status'] === 'completed'): ?>
                        <button class="btn btn-paid" disabled>Paid</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePaymentModal()">&times;</span>
        <h3>Enter Payment Details</h3>
        <form method="post" id="paymentForm">
            <input type="hidden" name="event_id" id="modalEventId">
            <input type="hidden" name="action" value="pay">
            <input type="text" name="transaction_id" class="modal-input" placeholder="Enter UPI Transaction ID" required>
            <button type="submit" class="modal-submit">Submit</button>
        </form>
    </div>
</div>

<script>
    function showPaymentModal(eventId) {
        document.getElementById('modalEventId').value = eventId;
        document.getElementById('paymentModal').style.display = 'block';
    }
    
    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('paymentModal');
        if (event.target == modal) {
            closePaymentModal();
        }
    }
</script>

</body>
</html>