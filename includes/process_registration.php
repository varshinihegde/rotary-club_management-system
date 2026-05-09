<?php
// process_registration.php

// Database connection
$conn = new mysqli('localhost', 'root', '', 'club management');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$errors = [];
$pending_id = uniqid(); // Generate a unique ID for the pending registration

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $full_name = htmlspecialchars($_POST['full_name'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $address = htmlspecialchars($_POST['address'] ?? '');
    
    // File upload handling
    $public_path = 'public_path'; // Define your public path
    $pdf_path = '';
    
    if (isset($_FILES['achievements']) && $_FILES['achievements']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES['achievements']['name']);
        
        if (move_uploaded_file($_FILES['achievements']['tmp_name'], $target_file)) {
            $pdf_path = $target_file;
        } else {
            $errors[] = "Sorry, there was an error uploading your file.";
        }
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO pending_registrations 
                               (id, full_name, path1, path2, email, phone, address, pdf_path) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Bind parameters (all variables, not direct function calls)
        $stmt->bind_param("ssssssss", 
            $pending_id,
            $full_name,
            $public_path,
            $public_path,
            $email,
            $phone,
            $address,
            $pdf_path
        );

        if ($stmt->execute()) {
            // Success - redirect or show success message
            header("Location: success.php");
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        
        $stmt->close();
    }
}
$conn->close();

// If we got here, there were errors or the form wasn't submitted properly
// Display errors or redirect back to form
header("Location: registration.php?errors=" . urlencode(implode(', ', $errors)));
exit();
?>