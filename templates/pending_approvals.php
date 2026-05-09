<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Include PHPMailer
require_once '../src/Exception.php';
require_once '../src/PHPMailer.php';
require_once '../src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
$conn = new mysqli('localhost', 'root', '', 'club management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to send email using PHPMailer
function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'varshinihegde83@gmail.com'; // Replace with your email
        $mail->Password   = 'egtp mqhq wqbe wghy'; // Replace with app password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('clubmanagement@example.com', 'Club Management');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $application_id = $_POST['application_id'];
        $action = $_POST['action'];
        
        // Get application details first
        $stmt = $conn->prepare("SELECT * FROM pending_approval WHERE pending_id = ?");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $application_id);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($action == 'approve') {
            // Insert into members table with all data including password
            $stmt = $conn->prepare("INSERT INTO members 
                                  (member_id, m_name, m_email, m_phone_no, join_date, address, m_password)
                                  SELECT CONCAT('MEM', LPAD(FLOOR(RAND() * 100000), 5, '0')), 
                                  p_name, p_email, p_phone, NOW(), address, password 
                                  FROM pending_approval WHERE pending_id = ?");
            
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $application_id);
            
            if ($stmt->execute()) {
                // Delete from pending table
                $delete = $conn->prepare("DELETE FROM pending_approval WHERE pending_id = ?");
                if ($delete === false) {
                    die("Prepare failed: " . $conn->error);
                }
                $delete->bind_param("s", $application_id);
                $delete->execute();
                $delete->close();
                
                // Send approval email
                $subject = "Congratulations! Your Rotary Club Membership Has Been Approved";
                $message = "
                    <html>
                    <head>
                        <title>Membership Approved</title>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; }
                            .header { color: #2c3e50; }
                            .footer { margin-top: 20px; font-size: 0.9em; color: #7f8c8d; }
                        </style>
                    </head>
                    <body>
                        <h2 class='header'>Dear {$application['p_name']},</h2>
                        <p>We are pleased to inform you that your application to join the Rotary Club has been <strong>approved</strong>!</p>
                        <p>You are now an official member of our prestigious club. Welcome aboard!</p>
                        
                        <h3>Next Steps:</h3>
                        <ul>
                            <li>You can now login to the member portal using your registered email and password</li>
                            <li>Explore upcoming events and meetings</li>
                            <li>Connect with fellow members through our community platform</li>
                        </ul>
                        
                        <p>We look forward to your active participation in our club activities and initiatives.</p>
                        
                        <div class='footer'>
                            <p>Best regards,</p>
                            <p><strong>The Rotary Club Team</strong></p>
                        </div>
                    </body>
                    </html>
                ";
                
                if (sendEmail($application['p_email'], $subject, $message)) {
                    $_SESSION['message'] = "Application approved successfully! Notification email sent.";
                } else {
                    $_SESSION['error'] = "Application approved but failed to send email notification.";
                }
            } else {
                $_SESSION['error'] = "Error approving application: " . $stmt->error;
            }
            $stmt->close();
        } 
        elseif ($action == 'reject') {
            $stmt = $conn->prepare("DELETE FROM pending_approval WHERE pending_id = ?");
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $application_id);
            
            if ($stmt->execute()) {
                // Send rejection email
                $subject = "Your Rotary Club Membership Application Update";
                $message = "
                    <html>
                    <head>
                        <title>Application Update</title>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; }
                            .header { color: #2c3e50; }
                            .footer { margin-top: 20px; font-size: 0.9em; color: #7f8c8d; }
                        </style>
                    </head>
                    <body>
                        <h2 class='header'>Dear {$application['p_name']},</h2>
                        <p>Thank you for your interest in joining the Rotary Club.</p>
                        <p>After careful consideration, we regret to inform you that your application has not been approved at this time.</p>
                        
                        <h3>Reason for Rejection:</h3>
                        <p>The achievement document you provided didn't meet our verification requirements.</p>
                        
                        <h3>You May:</h3>
                        <ul>
                            <li>Submit a new application with more verifiable achievement documents</li>
                         
                        </ul>
                        
                        <p>We appreciate your understanding and encourage you to try again in the future.</p>
                        
                        <div class='footer'>
                            <p>Sincerely,</p>
                            <p><strong>The Rotary Club Membership Committee</strong></p>
                        </div>
                    </body>
                    </html>
                ";
                
                if (sendEmail($application['p_email'], $subject, $message)) {
                    $_SESSION['message'] = "Application rejected successfully! Notification email sent to the applicant.";
                } else {
                    $_SESSION['error'] = "Application rejected but failed to send email notification.";
                }
            } else {
                $_SESSION['error'] = "Error rejecting application: " . $stmt->error;
            }
            $stmt->close();
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
        .password-field {
            font-family: monospace;
            background-color: #f8f9fa;
            border: none;
            cursor: default;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Club Management</a>
        </div>
    </nav>
    
    <div class="container mt-4">
        <h2><i class="fas fa-user-clock me-2"></i> Pending Approvals</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['message'] ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
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
                                <th>Password</th>
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
                                    <td>
                                        <input type="text" class="password-field" 
                                               value="<?= htmlspecialchars($row['p_password'] ?? '') ?>" 
                                               readonly>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($row['registration_date'])) ?></td>
                                    <td>
                                        <?php if (!empty($row['achievements'])): ?>
                                            <?php if (strpos($row['achievements'], 'uploads/') === 0): ?>
                                                <a href="<?= $row['achievements'] ?>" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-file-pdf"></i> View
                                                </a>
                                            <?php else: ?>
                                                <?php
                                                $temp_file = 'temp_document_'.$row['pending_id'].'.pdf';
                                                file_put_contents($temp_file, $row['achievements']);
                                                ?>
                                                <a href="<?= $temp_file ?>" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-file-pdf"></i> View
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            No document
                                        <?php endif; ?>
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
                                    <td colspan="9" class="text-center">No pending applications found</td>
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