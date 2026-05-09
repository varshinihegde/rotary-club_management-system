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

// Function to send email
function sendEmail($to, $subject, $message) {
    $headers = "From: clubmanagement@example.com\r\n";
    $headers .= "Reply-To: clubmanagement@example.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $application_id = $_POST['application_id'];
        $action = $_POST['action'];
        
        // Get application details first
        $stmt = $conn->prepare("SELECT * FROM pending_approval WHERE pending_id = ?");
        $stmt->bind_param("s", $application_id);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($action == 'approve') {
            // Generate a random password
            $password = bin2hex(random_bytes(8)); // 16-character random password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Debug: Print application data
            error_log("Approving application: " . print_r($application, true));
            
            // Generate member ID
            $member_id = 'MEM' . str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
            
            // Move to members table
            $stmt = $conn->prepare("INSERT INTO members 
                                  (member_id, m_name, m_email, m_phone_no, join_date, address, m_password) 
                                  VALUES (?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->bind_param("ssssss", 
                $member_id,
                $application['p_name'],
                $application['p_email'],
                $application['p_phone'],
                $application['address'],
                $hashed_password
            );
            
            if ($stmt->execute()) {
                // Delete from pending table
                $delete = $conn->prepare("DELETE FROM pending_approval WHERE pending_id = ?");
                $delete->bind_param("s", $application_id);
                
                if ($delete->execute()) {
                    // Send approval email
                    $subject = "Your Club Membership Application Has Been Approved";
                    $message = "
                        <html>
                        <head>
                            <title>Membership Approved</title>
                        </head>
                        <body>
                            <h2>Congratulations, {$application['p_name']}!</h2>
                            <p>Your membership application has been approved.</p>
                            <p>Here are your login details:</p>
                            <p><strong>Email:</strong> {$application['p_email']}</p>
                            <p><strong>Password:</strong> $password</p>
                            <p>You can now login to the member portal using these credentials.</p>
                            <p>Thank you for joining our club!</p>
                        </body>
                        </html>
                    ";
                    
                    sendEmail($application['p_email'], $subject, $message);
                    
                    $_SESSION['message'] = "Application approved successfully! Email sent to the member.";
                } else {
                    $_SESSION['message'] = "Error removing from pending table: " . $conn->error;
                }
            } else {
                $_SESSION['message'] = "Error inserting into members table: " . $conn->error;
                // Debug: Log the exact SQL error
                error_log("SQL Error: " . $stmt->error);
            }
        } 
        elseif ($action == 'reject') {
            $stmt = $conn->prepare("DELETE FROM pending_approval WHERE pending_id = ?");
            $stmt->bind_param("s", $application_id);
            
            if ($stmt->execute()) {
                // Send rejection email
                $subject = "Your Club Membership Application Status";
                $message = "
                    <html>
                    <head>
                        <title>Membership Application Update</title>
                    </head>
                    <body>
                        <h2>Dear {$application['p_name']},</h2>
                        <p>We regret to inform you that your membership application has been rejected.</p>
                        <p>Reason: The achievement document you provided didn't meet our requirements.</p>
                        <p>You may try again with a genuine achievement document.</p>
                        <p>Thank you for your interest in our club.</p>
                    </body>
                    </html>
                ";
                
                sendEmail($application['p_email'], $subject, $message);
                
                $_SESSION['message'] = "Application rejected successfully! Email sent to the applicant.";
            } else {
                $_SESSION['message'] = "Error rejecting application: " . $conn->error;
            }
        }
        
        header("Location: pending_approvals.php");
        exit();
    }
}

// Get all pending applications
$query = "SELECT * FROM pending_approval WHERE p_status = 'pending' ORDER BY registration_date DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            margin-top: 20px;
        }
        .table {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php 
    // Check if admin_navbar.php exists in the same directory
    $navbarPath = __DIR__ . '/admin_navbar.php';
    if (file_exists($navbarPath)) {
        include($navbarPath);
    } else {
        // Fallback navigation if navbar file doesn't exist
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
                <div class="container">
                    <a class="navbar-brand" href="#">Club Management</a>
                    <div class="d-flex">
                        <a href="admin_dashboard.php" class="btn btn-outline-light me-2">Dashboard</a>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </div>
                </div>
              </nav>';
    }
    ?>
    
    <div class="container mt-4">
        <h2><i class="fas fa-user-clock me-2"></i> Pending Approvals</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= strpos($_SESSION['message'], 'successfully') !== false ? 'success' : 'danger' ?>">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <div class="card mt-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Application Date</th>
                                <th>Document</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['pending_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['p_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['p_email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['p_phone'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['address'] ?? '') ?></td>
                                    <td><?= date('M j, Y', strtotime($row['registration_date'])) ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($row['achievements'])) {
                                            if (strpos($row['achievements'], 'uploads/') === 0) {
                                                echo '<a href="'.$row['achievements'].'" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-file-pdf"></i> View Document
                                                </a>';
                                            } else {
                                                $temp_file = 'temp_document_'.$row['pending_id'].'.pdf';
                                                file_put_contents($temp_file, $row['achievements']);
                                                echo '<a href="'.$temp_file.'" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-file-pdf"></i> View Document
                                                </a>';
                                            }
                                        } else {
                                            echo 'No document';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="application_id" value="<?= $row['pending_id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="application_id" value="<?= $row['pending_id'] ?>">
                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No pending applications found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>