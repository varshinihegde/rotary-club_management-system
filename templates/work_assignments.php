<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'club management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_work'])) {
    $event_id = $conn->real_escape_string($_POST['event_id']);
    $member_id = $conn->real_escape_string($_POST['member_id']);
    $description = $conn->real_escape_string($_POST['description']);
    
    // First verify the event and member exist
    $check_event = $conn->query("SELECT event_id FROM event WHERE event_id = '$event_id'");
    $check_member = $conn->query("SELECT member_id FROM members WHERE member_id = '$member_id'");
    
    if ($check_event->num_rows == 0) {
        $_SESSION['notification'] = [
            'type' => 'danger',
            'message' => "Error: Selected event does not exist"
        ];
    } elseif ($check_member->num_rows == 0) {
        $_SESSION['notification'] = [
            'type' => 'danger',
            'message' => "Error: Selected member does not exist"
        ];
    } else {
        // Insert the assignment
        $stmt = $conn->prepare("INSERT INTO workassignment (event_id, member_id, description, w_status, payment_status) VALUES (?, ?, ?, 'pending', 'pending')");
        $stmt->bind_param("sss", $event_id, $member_id, $description);
        
        if ($stmt->execute()) {
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => "Work assigned successfully!"
            ];
        } else {
            $_SESSION['notification'] = [
                'type' => 'danger',
                'message' => "Error assigning work: " . $conn->error
            ];
        }
    }
    
    header("Location: work_assignments.php");
    exit();
}

// Get current date
$current_date = date('Y-m-d');

// Get only upcoming events for dropdown
$events = $conn->query("SELECT event_id, event_name FROM event WHERE event_date >= '$current_date' ORDER BY event_date ASC");
if (!$events) die("Error getting events: " . $conn->error);

// Get all members for dropdown
$members = $conn->query("SELECT member_id, m_name FROM members ORDER BY m_name");
if (!$members) die("Error getting members: " . $conn->error);

// Get all work assignments with join data (only upcoming events or past events with pending payment)
$assignments_query = "SELECT w.*, e.event_name, e.event_date, m.m_name
                      FROM workassignment w
                      JOIN event e ON w.event_id = e.event_id
                      JOIN members m ON w.member_id = m.member_id
                      WHERE e.event_date >= '$current_date' OR 
                            (e.event_date < '$current_date' AND w.payment_status = 'pending')
                      ORDER BY e.event_date ASC, w.w_status, w.assignment_id DESC";
$assignments = $conn->query($assignments_query);
if (!$assignments) die("Error getting assignments: " . $conn->error);

// Display notification if exists
$notification = $_SESSION['notification'] ?? null;
unset($_SESSION['notification']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Assignments Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .badge-pending { background-color: #ffc107; color: #212529; }
        .badge-approved { background-color: #28a745; color: white; }
        .badge-rejected { background-color: #dc3545; color: white; }
        .badge-payment-pending { background-color: #ffc107; color: #212529; }
        .badge-payment-completed { background-color: #28a745; color: white; }
        .badge-payment-failed { background-color: #dc3545; color: white; }
        .rejection-reason {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .payment-details {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
        .past-event-row {
            background-color: rgba(0,0,0,0.03);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Notification Alert -->
        <?php if ($notification): ?>
        <div class="alert alert-<?= $notification['type'] ?> alert-dismissible fade show">
            <?= $notification['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <h2 class="mb-4"><i class="fas fa-tasks me-2"></i> Work Assignments</h2>
        
        <!-- Assignment Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-plus-circle me-2"></i> Assign New Work
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Event</label>
                            <select class="form-select" name="event_id" required>
                                <option value="">Select Event</option>
                                <?php while($event = $events->fetch_assoc()): ?>
                                <option value="<?= $event['event_id'] ?>"><?= htmlspecialchars($event['event_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Member</label>
                            <select class="form-select" name="member_id" required>
                                <option value="">Select Member</option>
                                <?php while($member = $members->fetch_assoc()): ?>
                                <option value="<?= $member['member_id'] ?>"><?= htmlspecialchars($member['m_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Description</label>
                            <input type="text" class="form-control" name="description" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="assign_work" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Assign Work
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Assignments Table -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list-check me-2"></i> Current Assignments</span>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="showRejectedToggle" checked>
                    <label class="form-check-label text-white" for="showRejectedToggle">Show Rejected</label>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Description</th>
                                <th>Work Status</th>
                                <th>Payment Status</th>
                                <th>Rejection Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($assignments->num_rows > 0): ?>
                                <?php while($assignment = $assignments->fetch_assoc()): 
                                    $is_past_event = strtotime($assignment['event_date']) < strtotime($current_date);
                                ?>
                                <tr class="assignment-row <?= $is_past_event ? 'past-event-row' : '' ?>" data-status="<?= $assignment['w_status'] ?>">
                                    <td><?= htmlspecialchars($assignment['assignment_id']) ?></td>
                                    <td><?= htmlspecialchars($assignment['event_name']) ?></td>
                                    <td><?= date('M j, Y', strtotime($assignment['event_date'])) ?></td>
                                    <td><?= htmlspecialchars($assignment['m_name']) ?></td>
                                    <td><?= htmlspecialchars($assignment['description']) ?></td>
                                    <td>
                                        <span class="status-badge badge-<?= $assignment['w_status'] ?>">
                                            <?= ucfirst($assignment['w_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-payment-<?= $assignment['payment_status'] ?>">
                                            <?= ucfirst($assignment['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td class="rejection-reason" title="<?= htmlspecialchars($assignment['rejection_reason'] ?? '') ?>">
                                        <?= htmlspecialchars($assignment['rejection_reason'] ?? 'N/A') ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No work assignments found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment Management Section -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-money-bill-wave me-2"></i> Payment Management
            </div>
            <div class="card-body">
                <?php 
                // Get only approved assignments with pending payment or past events with pending payment
                $assignments_payment = $conn->query("SELECT w.*, e.event_name, e.event_date, m.m_name 
                                                   FROM workassignment w
                                                   JOIN event e ON w.event_id = e.event_id
                                                   JOIN members m ON w.member_id = m.member_id
                                                   WHERE w.w_status = 'approved' AND 
                                                         (e.event_date >= '$current_date' OR 
                                                          (e.event_date < '$current_date' AND w.payment_status = 'pending'))
                                                   ORDER BY e.event_date ASC, w.payment_status, w.assignment_id DESC");
                ?>
                
                <?php if ($assignments_payment->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Description</th>
                                <th>Payment Status</th>
                                <th>Transaction ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($assignment = $assignments_payment->fetch_assoc()): 
                                $is_past_event = strtotime($assignment['event_date']) < strtotime($current_date);
                            ?>
                            <tr class="<?= $is_past_event ? 'past-event-row' : '' ?>">
                                <td><?= $assignment['assignment_id'] ?></td>
                                <td><?= htmlspecialchars($assignment['event_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($assignment['event_date'])) ?></td>
                                <td><?= htmlspecialchars($assignment['m_name']) ?></td>
                                <td><?= htmlspecialchars($assignment['description']) ?></td>
                                <td>
                                    <span class="status-badge badge-payment-<?= $assignment['payment_status'] ?>">
                                        <?= ucfirst($assignment['payment_status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($assignment['transaction_id'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                    <p class="text-muted">No approved assignments available for payment management</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle rejected assignments
        document.getElementById('showRejectedToggle').addEventListener('change', function() {
            const show = this.checked;
            document.querySelectorAll('.assignment-row[data-status="rejected"]').forEach(row => {
                row.style.display = show ? '' : 'none';
            });
        });

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>