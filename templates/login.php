<?php
session_start();

// Database connection
require_once __DIR__ . '/../includes/db_connect.php';

// Verify connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Initialize variables
$error = '';
$email = '';
$login_type = 'member'; // Default to member login

// CAPTCHA generation
if (!isset($_SESSION['captcha'])) {
    $_SESSION['captcha'] = generateCaptcha();
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_type = $_POST['login_type'] ?? 'member';
    $user_captcha = trim($_POST['captcha'] ?? '');

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } elseif (empty($user_captcha)) {
        $error = "Please enter the CAPTCHA code";
    } elseif (strtoupper($user_captcha) !== $_SESSION['captcha']) {
        $error = "Invalid CAPTCHA code";
        $_SESSION['captcha'] = generateCaptcha();
    } else {
        try {
            // Authenticate based on login type
            if ($login_type == 'admin') {
                $user = authenticateAdmin($email, $password);
                if ($user) {
                    // Successful admin login
                    unset($_SESSION['captcha']);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_name'] = $user['name'];
                    $_SESSION['admin_role'] = $user['role'];
                    header('Location: admin_dashboard.php');
                    exit();
                } else {
                    // Check if admin exists but password is wrong
                    $check = $conn->prepare("SELECT admin_id FROM admin WHERE email = ?");
                    if ($check === false) {
                        $error = "Database error. Please try again.";
                    } else {
                        $check->bind_param("s", $email);
                        $check->execute();
                        $result = $check->get_result();
                        $error = ($result->num_rows > 0) 
                            ? "Incorrect admin password" 
                            : "Admin account not found";
                    }
                }
            } else {
                $user = authenticateMember($email, $password);
                if ($user) {
                    // Successful member login
                    unset($_SESSION['captcha']);
                    $_SESSION['member_logged_in'] = true;
                    $_SESSION['member_id'] = $user['member_id']; // ONLY ADDED THIS LINE
                    $_SESSION['member_email'] = $email;
                    $_SESSION['member_name'] = $user['m_name'];
                    header('Location: member_dashboard.php');
                    exit();
                } else {
                    // Check different failure scenarios
                    $check = $conn->prepare("SELECT m_name FROM members WHERE m_email = ?");
                    if ($check === false) {
                        error_log("Member check prepare error: " . $conn->error);
                        $error = "Database error. Please try again.";
                    } else {
                        $check->bind_param("s", $email);
                        $check->execute();
                        $result = $check->get_result();
                        if ($result->num_rows > 0) {
                            $error = "Incorrect password";
                        } else {
                            $error = "Account not found. <a href='registration.php' style='color:#2b6cb0;'>Register here</a>";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "System error. Please try again later.";
        }
    }
    
    // Regenerate CAPTCHA after each attempt
    $_SESSION['captcha'] = generateCaptcha();
}

function generateCaptcha($length = 6) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    return substr(str_shuffle($chars), 0, $length);
}

function authenticateAdmin($email, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT admin_id, name, role, email, a_password FROM admin WHERE email = ?");
    if ($stmt === false) {
        error_log("Admin prepare error: " . $conn->error);
        return false;
    }
    
    if (!$stmt->bind_param("s", $email)) {
        error_log("Admin bind error: " . $stmt->error);
        return false;
    }
    
    if (!$stmt->execute()) {
        error_log("Admin execute error: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return false;
    }
    
    $admin = $result->fetch_assoc();
    
    // First check plain text password (for migration)
    if ($admin['a_password'] === $password) {
        // Upgrade to hashed password
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE admin SET a_password = ? WHERE admin_id = ?");
        $update->bind_param("si", $hashed, $admin['admin_id']);
        $update->execute();
        return $admin;
    }
    
    // Then check hashed password
    if (password_verify($password, $admin['a_password'])) {
        return $admin;
    }
    
    return false;
}

function authenticateMember($email, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT member_id, m_name, m_email, m_password FROM members WHERE m_email = ?");
    if ($stmt === false) {
        error_log("Member prepare error: " . $conn->error);
        return false;
    }
    
    if (!$stmt->bind_param("s", $email)) {
        error_log("Member bind error: " . $stmt->error);
        return false;
    }
    
    if (!$stmt->execute()) {
        error_log("Member execute error: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return false;
    }
    
    $member = $result->fetch_assoc();
    
    // First check plain text password (for migration)
    if ($member['m_password'] === $password) {
        // Upgrade to hashed password
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE members SET m_password = ? WHERE member_id = ?");
        $update->bind_param("si", $hashed, $member['member_id']);
        $update->execute();
        return $member;
    }
    
    // Then check hashed password
    if (password_verify($password, $member['m_password'])) {
        return $member;
    }
    
    return false;
}
?>

<!-- REST OF YOUR HTML CODE REMAINS EXACTLY THE SAME -->
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ... ALL EXISTING HTML CONTENT REMAINS UNCHANGED ... -->
</head>
<body>
    <!-- ... ALL EXISTING HTML CONTENT REMAINS UNCHANGED ... -->
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotary Club Login</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .login-container {
            background: white;
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e1e5eb;
        }
        
        .logo {
            text-align: center;
            color: #2c5282;
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
        }
        
        .logo span {
            color: #e53e3e;
        }
        
        h1 {
            text-align: center;
            color: #2c5282;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        
        .error {
            background-color: #fff5f5;
            color: #e53e3e;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            text-align: center;
            border: 1px solid #fed7d7;
            font-size: 0.9rem;
        }
        
        .error a {
            color: #2b6cb0;
            text-decoration: underline;
        }
        
        .login-type {
            display: flex;
            justify-content: center;
            margin-bottom: 1.75rem;
            gap: 1rem;
        }
        
        .login-type button {
            padding: 0.6rem 1.75rem;
            background: none;
            border: 2px solid #2c5282;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            color: #2c5282;
        }
        
        .login-type button.active {
            background-color: #2c5282;
            color: white;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.95rem;
        }
        
        input {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .captcha-container {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .captcha-code {
            font-family: 'Courier New', monospace;
            font-size: 1.4rem;
            font-weight: bold;
            letter-spacing: 0.2em;
            padding: 0.5rem 1rem;
            background-color: #edf2f7;
            border-radius: 6px;
            user-select: none;
            color: #2d3748;
        }
        
        .login-btn {
            width: 100%;
            padding: 0.85rem;
            background-color: #2c5282;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 1.5rem;
            transition: background-color 0.3s;
        }
        
        .login-btn:hover {
            background-color: #1a365d;
        }
        
        .links {
            text-align: center;
            font-size: 0.9rem;
        }
        
        .links a {
            color: #2b6cb0;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .links a:hover {
            color: #1a4e8a;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">Rotary <span>Club</span></div>
        <h1>Login to Your Account</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="login-type">
            <button type="button" id="memberBtn" class="<?php echo $login_type == 'member' ? 'active' : ''; ?>">Member Login</button>
            <button type="button" id="adminBtn" class="<?php echo $login_type == 'admin' ? 'active' : ''; ?>">Admin Login</button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="login_type" id="loginType" value="<?php echo $login_type; ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>CAPTCHA Verification</label>
                <div class="captcha-container">
                    <input type="text" name="captcha" placeholder="Enter CAPTCHA" required>
                    <div class="captcha-code"><?php echo $_SESSION['captcha']; ?></div>
                </div>
            </div>
            
            <button type="submit" class="login-btn">Login</button>
            
            <div class="links">
                <a href="forgot_password.php">Forgot Password?</a>
                <?php if ($login_type == 'member'): ?>
                    <div style="margin-top: 1rem;">
                        Don't have an account? <a href="registration.php">Register here</a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
        const memberBtn = document.getElementById('memberBtn');
        const adminBtn = document.getElementById('adminBtn');
        const loginType = document.getElementById('loginType');
        
        memberBtn.addEventListener('click', () => {
            memberBtn.classList.add('active');
            adminBtn.classList.remove('active');
            loginType.value = 'member';
        });
        
        adminBtn.addEventListener('click', () => {
            adminBtn.classList.add('active');
            memberBtn.classList.remove('active');
            loginType.value = 'admin';
        });
    </script>
</body>
</html>