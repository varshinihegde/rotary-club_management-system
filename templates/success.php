<?php
// success.php - STANDALONE VERSION
session_start();

// Check if coming from successful registration
if (empty($_SESSION['registration_success'])) {
    die('Invalid access - Please complete registration first');
}

// Get data from session
$pending_id = $_SESSION['pending_id'] ?? 'UNKNOWN';
$email = $_SESSION['email'] ?? '';

// Clear session immediately
session_unset();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registration Complete</title>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: #f5f5f5;
        }
        .success-box {
            background: white;
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .tick {
            color: #4CAF50;
            font-size: 60px;
            margin-bottom: 20px;
        }
        .id-box {
            background: #f0f8ff;
            padding: 10px;
            display: inline-block;
            margin: 15px 0;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="success-box">
        <div class="tick">✓</div>
        <h1>Registration Submitted!</h1>
        
        <p>Thank you for registering with us.</p>
        
        <div class="id-box">
            Your Registration ID: <?php echo htmlspecialchars($pending_id); ?>
        </div>
        
        <p>Our team will verify your details and notify you at:<br>
        <strong><?php echo htmlspecialchars($email); ?></strong></p>
        
        <p>You'll receive an approval/rejection email within 3 business days.</p>
        
        <p><em>Please check your spam folder if you don't see our email.</em></p>
    </div>
</body>
</html>