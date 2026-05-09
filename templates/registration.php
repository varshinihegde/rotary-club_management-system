<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'club management';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create database connection
    $db = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error);
    }

    // Initialize variables
    $errors = [];
    $success = '';
    
    // Get form data
    $full_name = $db->real_escape_string(trim($_POST['full_name'] ?? ''));
    $email = $db->real_escape_string(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = $db->real_escape_string(trim($_POST['phone'] ?? ''));
    $address = $db->real_escape_string(trim($_POST['address'] ?? ''));

    // Validate form data
    if (empty($full_name)) {
        $errors['full_name'] = 'Full name is required';
    } elseif (strlen($full_name) < 3) {
        $errors['full_name'] = 'Name must be at least 3 characters';
    }

    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || 
               !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors['password'] = 'Password must include uppercase, lowercase, number and special character';
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if (empty($phone)) {
        $errors['phone'] = 'WhatsApp number is required';
    } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid 10-digit WhatsApp number starting with 6,7,8 or 9';
    }

    if (empty($address)) {
        $errors['address'] = 'Address is required';
    } elseif (strlen($address) < 10) {
        $errors['address'] = 'Address is too short';
    }

    // Handle file upload
    $achievements = null;
    if (isset($_FILES['achievements']) && $_FILES['achievements']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['achievements']['type'] !== 'application/pdf') {
            $errors['achievements'] = 'Only PDF files are allowed';
        } elseif ($_FILES['achievements']['size'] > 5 * 1024 * 1024) {
            $errors['achievements'] = 'File size must be less than 5MB';
        } else {
            $achievements = file_get_contents($_FILES['achievements']['tmp_name']);
        }
    } else {
        $errors['achievements'] = 'Achievements file is required';
    }

    // Check for duplicates only if no other errors
    if (empty($errors)) {
        // Check if email exists in pending_approval or members table
        $email_check = $db->prepare("
            SELECT p_email FROM pending_approval WHERE p_email = ?
            UNION
            SELECT m_email FROM members WHERE m_email = ?
        ");
        if ($email_check === false) {
            $errors['database'] = "Database error: " . $db->error;
        } else {
            $email_check->bind_param("ss", $email, $email);
            $email_check->execute();
            $email_check->store_result();

            if ($email_check->num_rows > 0) {
                $errors['email'] = 'This email is already registered';
            }
            $email_check->close();
        }

        // Check if phone exists in pending_approval or members table
        $phone_check = $db->prepare("
            SELECT p_phone FROM pending_approval WHERE p_phone = ?
            UNION
            SELECT m_phone_no FROM members WHERE m_phone_no = ?
        ");
        if ($phone_check === false) {
            $errors['database'] = "Database error: " . $db->error;
        } else {
            $phone_check->bind_param("ss", $phone, $phone);
            $phone_check->execute();
            $phone_check->store_result();

            if ($phone_check->num_rows > 0) {
                $errors['phone'] = 'This phone number is already registered';
            }
            $phone_check->close();
        }
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate a unique pending_id
        $pending_id = uniqid('', true);
        
        $stmt = $db->prepare("INSERT INTO pending_approval 
                            (pending_id, p_name, p_email, password, p_phone, address, achievements, p_status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        if ($stmt === false) {
            $errors['database'] = "Database error: " . $db->error;
        } else {
            $null = null;
            $stmt->bind_param("ssssssb", $pending_id, $full_name, $email, $hashed_password, $phone, $address, $null);
            $stmt->send_long_data(6, $achievements);
            
            if ($stmt->execute()) {
                $success = "Congratulations 🎊! You have registered successfully!";
            } else {
                $errors['database'] = "Registration failed: " . $stmt->error;
            }
            
            $stmt->close();
        }
    }
    
    $db->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Registration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        textarea,
        input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
        }
        .error {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .success {
            color: green;
            font-size: 1.1em;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background-color: #45a049;
        }
        .password-strength {
            margin-top: 5px;
            font-size: 0.9em;
        }
        .weak {
            color: red;
        }
        .medium {
            color: orange;
        }
        .strong {
            color: green;
        }
        .hint {
            font-size: 0.8em;
            color: #666;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Club Membership Registration</h1>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="full_name">Full Name*</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                <?php if (!empty($errors['full_name'])): ?>
                    <div class="error"><?php echo $errors['full_name']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="email">Email*</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <?php if (!empty($errors['email'])): ?>
                    <div class="error"><?php echo $errors['email']; ?></div>
                <?php endif; ?>
                
            </div>
            
            <div class="form-group">
                <label for="password">Password*</label>
                <input type="password" id="password" name="password" required onkeyup="checkPasswordStrength()">
                <div id="password_strength" class="password-strength"></div>
                <?php if (!empty($errors['password'])): ?>
                    <div class="error"><?php echo $errors['password']; ?></div>
                <?php endif; ?>
                <div class="hint">Must be 8+ chars with uppercase, lowercase, number, and special char</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password*</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <?php if (!empty($errors['confirm_password'])): ?>
                    <div class="error"><?php echo $errors['confirm_password']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="phone">WhatsApp Number*</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                <?php if (!empty($errors['phone'])): ?>
                    <div class="error"><?php echo $errors['phone']; ?></div>
                <?php endif; ?>
                
            </div>
            
            <div class="form-group">
                <label for="address">Address*</label>
                <textarea id="address" name="address" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                <?php if (!empty($errors['address'])): ?>
                    <div class="error"><?php echo $errors['address']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="achievements">Achievements (PDF only)*</label>
                <input type="file" id="achievements" name="achievements" accept=".pdf" required>
                <?php if (!empty($errors['achievements'])): ?>
                    <div class="error"><?php echo $errors['achievements']; ?></div>
                <?php endif; ?>
                <div class="hint">Maximum file size: 5MB</div>
            </div>
            
            <?php if (!empty($errors['database'])): ?>
                <div class="error"><?php echo $errors['database']; ?></div>
            <?php endif; ?>
            
            <button type="submit">Submit Application</button>
        </form>
    </div>

    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthText = document.getElementById('password_strength');
            
            if (password.length === 0) {
                strengthText.textContent = '';
                return;
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength <= 3) {
                strengthText.textContent = 'Weak password';
                strengthText.className = 'password-strength weak';
            } else if (strength <= 5) {
                strengthText.textContent = 'Medium password';
                strengthText.className = 'password-strength medium';
            } else {
                strengthText.textContent = 'Strong password';
                strengthText.className = 'password-strength strong';
            }
        }
        
        function validateForm() {
            let isValid = true;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const phone = document.getElementById('phone').value;
            
            // Simple client-side validation (server-side is more important)
            if (password.length < 8) {
                alert('Password must be at least 8 characters');
                isValid = false;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                isValid = false;
            }
            
            if (!/^[6-9]\d{9}$/.test(phone)) {
                alert('Please enter a valid 10-digit WhatsApp number starting with 6,7,8 or 9');
                isValid = false;
            }
            
            return isValid;
        }
    </script>
</body>
</html>