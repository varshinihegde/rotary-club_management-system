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
$member_id = '';

// Initialize variables
$member_data = [];
$success_message = '';
$error_message = '';

// Get member data from database
$stmt = $conn->prepare("SELECT * FROM members WHERE m_email = ?");
if ($stmt) {
    $stmt->bind_param("s", $member_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $member_data = $result->fetch_assoc();
    $member_id = $member_data['member_id'];
    $stmt->close();
}

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Initialize photo update variables
    $photo_update = '';
    $photo_path = $member_data['profile_photo'] ?? null;

    // Handle file upload if a new photo was provided
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile_photos/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'member_' . $member_id . '_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $filename;
        
        // Check if file is an actual image
        $check = getimagesize($_FILES['profile_photo']['tmp_name']);
        if ($check !== false) {
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                // Delete old photo if it exists
                if ($photo_path && file_exists($photo_path)) {
                    unlink($photo_path);
                }
                $photo_path = $target_file;
                $photo_update = ", profile_photo = ?";
            } else {
                $error_message = "Sorry, there was an error uploading your file.";
            }
        } else {
            $error_message = "File is not an image.";
        }
    }

    // Validate inputs
    if (empty($name) || empty($phone) || empty($address)) {
        $error_message = "Name, phone and address are required fields";
    } else {
        // Check if password change is requested
        $password_update = '';
        // ONLY CHANGE MADE: Added condition to check if new_password is not empty
        if (!empty($current_password) && !empty($new_password)) {
            if (password_verify($current_password, $member_data['m_password']) || $current_password === $member_data['m_password']) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_update = ", m_password = ?";
                } else {
                    $error_message = "New passwords don't match";
                }
            } else {
                $error_message = "Current password is incorrect";
            }
        }

        if (empty($error_message)) {
            // Prepare the update query based on what needs to be updated
            $query = "UPDATE members SET m_name = ?, m_phone_no = ?, address = ?";
            $params = [$name, $phone, $address];
            $types = "sss";
            
            // Add password update if needed
            if ($password_update) {
                $query .= $password_update;
                $params[] = $hashed_password;
                $types .= "s";
            }
            
            // Add photo update if needed
            if ($photo_update) {
                $query .= $photo_update;
                $params[] = $photo_path;
                $types .= "s";
            }
            
            $query .= " WHERE member_id = ?";
            $params[] = $member_id;
            $types .= "s";
            
            // Update member data
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $success_message = "Profile updated successfully!";
                // Refresh member data
                $stmt = $conn->prepare("SELECT * FROM members WHERE m_email = ?");
                $stmt->bind_param("s", $member_email);
                $stmt->execute();
                $result = $stmt->get_result();
                $member_data = $result->fetch_assoc();
                $_SESSION['member_name'] = $member_data['m_name'];
            } else {
                $error_message = "Error updating profile: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding-top: 20px;
        }

        .sidebar h2 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            padding: 12px 20px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .sidebar-menu li:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu li.active {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .main-content {
            flex: 1;
            padding: 30px;
            background-color: #f8f9fa;
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-title {
            font-size: 24px;
            color: #2c3e50;
        }

        .logout-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }

        .profile-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        .profile-photo-section {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3498db;
            margin-right: 20px;
        }

        .photo-upload {
            display: flex;
            flex-direction: column;
        }

        .photo-upload-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .photo-info {
            font-size: 12px;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input, 
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .password-fields {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border: 1px solid #eee;
        }

        .password-fields h3 {
            margin-top: 0;
            color: #2c3e50;
        }

        .btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #2980b9;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Club Portal</h2>
            <ul class="sidebar-menu">
                <li class="active" onclick="window.location.href='profile.php'">Profile</li>
                <li onclick="window.location.href='member_dashboard.php'">Dashboard</li>
                <li onclick="window.location.href='events.php'">Events</li>
                <li onclick="window.location.href='tasks.php'">Tasks</li>
                <li onclick="window.location.href='feedback.php'">Feedback</li>
            </ul>
        </div>

        <div class="main-content">
            <div class="profile-header">
                <h1 class="profile-title">My Profile</h1>
                <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="profile-card">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="profile-photo-section">
                        <?php if (!empty($member_data['profile_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($member_data['profile_photo']); ?>" alt="Profile Photo" class="profile-photo">
                        <?php else: ?>
                            <img src="assets/default-profile.jpg" alt="Profile Photo" class="profile-photo">
                        <?php endif; ?>
                        <div class="photo-upload">
                            <label for="profile_photo" class="photo-upload-btn">Choose Photo</label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/*" style="display: none;">
                            <span class="photo-info">JPG, PNG or GIF (Max 2MB)</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($member_data['m_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" value="<?php echo htmlspecialchars($member_data['m_email'] ?? ''); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($member_data['m_phone_no'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" required><?php echo htmlspecialchars($member_data['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="join_date">Join Date</label>
                        <input type="text" id="join_date" value="<?php echo date('F j, Y', strtotime($member_data['join_date'] ?? '')); ?>" readonly>
                    </div>

                    <div class="password-fields">
                        <h3>Change Password</h3>
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password">
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                    </div>

                    <button type="submit" class="btn">Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Show selected image preview
        document.getElementById('profile_photo').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-photo').src = e.target.result;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Trigger file input when the button is clicked
        document.querySelector('.photo-upload-btn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('profile_photo').click();
        });
    </script>
</body>
</html>