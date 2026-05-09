<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

if (isset($_POST['reset_password'])) {
    $otp = $_POST['otp'];
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate OTP
    if (!isset($_SESSION['otp_expiry']) || time() > $_SESSION['otp_expiry']) {
        die("OTP expired. Please request a new one.");
    }

    if (!password_verify($otp, $_SESSION['reset_otp'])) {
        die("Invalid OTP.");
    }

    if ($new_password !== $confirm_password) {
        die("Passwords do not match.");
    }

    // Update password with hashing
    $table = ($_SESSION['reset_user_type'] == 'admin') ? 'admin' : 'members';
    $password_field = ($_SESSION['reset_user_type'] == 'admin') ? 'a_password' : 'm_password';
    $email_field = ($_SESSION['reset_user_type'] == 'admin') ? 'email' : 'm_email';

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE $table SET $password_field = ? WHERE $email_field = ?");
    $stmt->bind_param("ss", $hashed_password, $_SESSION['reset_email']);
    $stmt->execute();

    // Clear session
    unset($_SESSION['reset_otp']);
    unset($_SESSION['reset_email']);
    
    header("Location: login.php?reset=success");
    exit();
}
?>

<!-- HTML Form -->
<form method="POST">
    <div class="form-group">
        <label>Enter OTP</label>
        <input type="text" name="otp" class="form-control" required>
    </div>
    
    <div class="form-group">
        <label>New Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    
    <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
    </div>
    
    <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
</form>