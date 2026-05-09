<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session for storing messages
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'club management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$errors = [];
$pending_id = uniqid();
$p_status = 'pending'; // Default status

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = htmlspecialchars($_POST['full_name'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $address = htmlspecialchars($_POST['address'] ?? '');
    
    $pdf_path = '';

    // Check for duplicate email or phone
    $check_sql = "SELECT * FROM pending_approval WHERE p_email = ? OR p_phone = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $email, $phone);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['p_email'] === $email) {
            $errors[] = "This email is already registered.";
        }
        if ($row['p_phone'] === $phone) {
            $errors[] = "This phone number is already registered.";
        }
        echo "<script>alert('".implode("\\n", $errors)."'); window.location.href='registration.php';</script>";
        exit();
    }
    $check_stmt->close();

    // File upload handling
    if (isset($_FILES['achievements']) && $_FILES['achievements']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['achievements']['type'] !== 'application/pdf') {
            $errors[] = "Only PDF files are allowed.";
        } elseif ($_FILES['achievements']['size'] > 10 * 1024 * 1024) {
            $errors[] = "PDF file must be less than 10MB.";
        } else {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $target_file = $target_dir . basename($_FILES['achievements']['name']);
            
            if (move_uploaded_file($_FILES['achievements']['tmp_name'], $target_file)) {
                $pdf_path = $target_file;
            } else {
                $errors[] = "Sorry, there was an error uploading your file.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO pending_approval 
            (pending_id, p_name, achievements, address, p_email, p_phone, p_status)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssss", 
            $pending_id,
            $full_name,
            $pdf_path,
            $address,
            $email,
            $phone,
            $p_status
        );

        if ($stmt->execute()) {
            echo "<script>
                alert('🎉 Congratulations! You have registered successfully. Your application is pending approval.');
                window.location.href = 'registration.php';
            </script>";
            exit();
        } else {
            $errors[] = "Database error: " . $stmt->error;
            echo "<script>alert('".implode("\\n", $errors)."'); window.location.href='registration.php';</script>";
            exit();
        }

        $stmt->close();
    } else {
        echo "<script>alert('".implode("\\n", $errors)."'); window.location.href='registration.php';</script>";
        exit();
    }
}

$conn->close();

// If we reach here, something unexpected happened
echo "<script>alert('An unexpected error occurred. Please try again.'); window.location.href='registration.php';</script>";
exit();
?>