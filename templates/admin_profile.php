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

// Get current admin details - first check if email is set in session
if (!isset($_SESSION['admin_email'])) {
    die("Session error: Admin email not found. Please login again.");
}

$admin_email = $_SESSION['admin_email'];
$stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    die("Admin not found in database. Please contact system administrator.");
}

// Set admin_id in session for future use
$_SESSION['admin_id'] = $admin['admin_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone_no = filter_input(INPUT_POST, 'phone_no', FILTER_SANITIZE_NUMBER_INT);
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    
    // Check if password is being changed
    $password = $admin['password']; // keep existing password by default
    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        } else {
            $_SESSION['error'] = "Passwords do not match";
            header("Location: admin_profile.php");
            exit();
        }
    }
    
    // Update admin details
    $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ?, phone_no = ?, role = ?, password = ? WHERE admin_id = ?");
    $stmt->bind_param("ssisss", $name, $email, $phone_no, $role, $password, $admin['admin_id']);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully";
        // Update session variables
        $_SESSION['admin_name'] = $name;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_role'] = $role;
    } else {
        $_SESSION['error'] = "Error updating profile: " . $conn->error;
    }
    
    header("Location: admin_profile.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --dark-bg: #1a1a2e;
            --dark-card: #16213e;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
        }

        .profile-card {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            background: white;
            overflow: hidden;
        }

        [data-theme="dark"] .profile-card {
            background: var(--dark-card);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .profile-header {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            padding: 20px;
            text-align: center;
        }

        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            margin-bottom: 15px;
        }

        .form-label {
            font-weight: 500;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }
    </style>
</head>
<body data-theme="light">
    <div class="sidebar" id="sidebar">
        <!-- Your existing sidebar content here -->
    </div>

    <div class="main-content" id="mainContent">
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="profile-card">
                        <div class="profile-header">
                        
                            <h3><?php echo htmlspecialchars($admin['name']); ?></h3>
                            <p class="mb-0"><?php echo htmlspecialchars($admin['role']); ?></p>
                        </div>
                        
                        <div class="card-body">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger"><?php echo $_SESSION['error']; ?></div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success"><?php echo $_SESSION['success']; ?></div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>
                            
                            <form method="POST" action="admin_profile.php">
                                <div class="mb-3">
                                    <label for="admin_id" class="form-label">Admin ID</label>
                                    <input type="text" class="form-control" id="admin_id" value="<?php echo htmlspecialchars($admin['admin_id']); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone_no" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone_no" name="phone_no" value="<?php echo htmlspecialchars($admin['phone_no']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="President" <?php echo ($admin['role'] === 'President') ? 'selected' : ''; ?>>President</option>
                                    
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <a href="admin_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Reuse your existing theme toggle script from dashboard
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        body.setAttribute('data-theme', savedTheme);
        
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const isDark = body.getAttribute('data-theme') === 'dark';
                body.setAttribute('data-theme', isDark ? 'light' : 'dark');
                localStorage.setItem('theme', isDark ? 'light' : 'dark');
            });
            
            // Set initial toggle state
            if (savedTheme === 'dark') {
                const icons = themeToggle.querySelectorAll('i');
                icons[0].classList.remove('active');
                icons[1].classList.add('active');
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>