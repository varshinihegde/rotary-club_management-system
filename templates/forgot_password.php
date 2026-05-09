<?php
session_start();
// Updated paths - choose one of these options:

// Option 1: Relative path from templates folder
require_once '../src/Exception.php';
require_once '../src/PHPMailer.php';
require_once '../src/SMTP.php';

// Option 2: Absolute path (more reliable)
// require_once __DIR__ . '/../src/Exception.php';
// require_once __DIR__ . '/../src/PHPMailer.php';
// require_once __DIR__ . '/../src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection (replace with your credentials)
$con = mysqli_connect("localhost", "root", "", "club management");
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

// ==================== MAIL FUNCTION ====================
function sendOTPEmail($email, $name, $otp)
{
    $mail = new PHPMailer(true);

    try {

        // SMTP SETTINGS
        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username = 'your_mail_id';

        $mail->Password = 'your_password';

        // IMPORTANT FIX
       $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->Port = 465;
        // DEBUGGING
        $mail->SMTPDebug = 2;

        $mail->Debugoutput = 'html';

        // SENDER
        $mail->setFrom('varshinihegde83@gmail.com', 'Rotary Club');

        // RECEIVER
        $mail->addAddress($email, $name);

        // CONTENT
        $mail->isHTML(true);

        $mail->Subject = 'Your Password Reset OTP';

        $mail->Body = "
            <h2>Rotary Club Password Reset</h2>
            <p>Hello $name,</p>
            <p>Your OTP code is:</p>
            <h1>$otp</h1>
            <p>This code expires in 10 minutes.</p>
        ";

        $mail->send();

        return true;

    } catch (Exception $e) {

        echo 'Mailer Error: ' . $mail->ErrorInfo;

        return false;
    }
}

// ==================== FORGOT PASSWORD ====================
if (isset($_POST['send_otp'])) {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $user_type = $_POST['user_type']; // 'member' or 'admin'

    // Check if email exists
    $table = ($user_type == 'admin') ? 'admin' : 'members';
    $email_field = ($user_type == 'admin') ? 'email' : 'm_email';
    $name_field = ($user_type == 'admin') ? 'name' : 'm_name';

    $query = "SELECT * FROM $table WHERE $email_field = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $otp = rand(100000, 999999);
        
        // Store hashed OTP in session
        $_SESSION['reset_data'] = [
            'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            'email' => $email,
            'user_type' => $user_type,
            'expiry' => time() + 600 // 10 minutes
        ];

        if (sendOTPEmail($email, $user[$name_field], $otp)) {
            header("Location: ?page=reset");
            exit();
        } else {
            } else {
    $error = "Failed to send OTP.";

    echo "<pre>";
    print_r(error_get_last());
    echo "</pre>";
}
        }
    } else {
        $message = "If this email exists, you'll receive an OTP shortly.";
    }
}

// ==================== RESET PASSWORD ====================
if (isset($_POST['reset_password'])) {
    if (!isset($_SESSION['reset_data'])) die("Invalid request");

    $otp = $_POST['otp'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Validate
    if (time() > $_SESSION['reset_data']['expiry']) {
        $error = "OTP expired. Please request a new one.";
    } elseif (!password_verify($otp, $_SESSION['reset_data']['otp_hash'])) {
        $error = "Invalid OTP code.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords don't match.";
    } else {
        // Update password
        $table = ($_SESSION['reset_data']['user_type'] == 'admin') ? 'admin' : 'members';
        $pass_field = ($_SESSION['reset_data']['user_type'] == 'admin') ? 'a_password' : 'm_password';
        $email_field = ($_SESSION['reset_data']['user_type'] == 'admin') ? 'email' : 'm_email';

        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $query = "UPDATE $table SET $pass_field = ? WHERE $email_field = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "ss", $hashed_pass, $_SESSION['reset_data']['email']);
        mysqli_stmt_execute($stmt);

        // Clear session and redirect
        unset($_SESSION['reset_data']);
        header("Location: login.php?reset=success");
        exit();
    }
}

// ==================== HTML OUTPUT ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Rotary Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .auth-box { 
            max-width: 500px; 
            margin: 5% auto; 
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            background: white;
            border-radius: 10px;
        }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <?php if (!isset($_GET['page']) || $_GET['page'] !== 'reset'): ?>
                <!-- FORGOT PASSWORD FORM -->
                <h2 class="text-center mb-4">Forgot Password</h2>
                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                <?php if (isset($message)) echo "<div class='alert alert-info'>$message</div>"; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <select name="user_type" class="form-select" required>
                            <option value="member" selected>Member</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary w-100">Send OTP</button>
                </form>
            <?php else: ?>
                <!-- RESET PASSWORD FORM -->
                <h2 class="text-center mb-4">Reset Password</h2>
                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">OTP Code</label>
                        <input type="text" name="otp" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-success w-100">Reset Password</button>
                </form>
                <div class="text-center mt-3">
                    <a href="?" class="text-decoration-none">← Back to OTP Request</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>