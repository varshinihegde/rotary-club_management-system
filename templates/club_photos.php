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

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['club_photo'])) {
    $target_dir = "uploads/club_photos/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . basename($_FILES['club_photo']['name']);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is a actual image
    $check = getimagesize($_FILES['club_photo']['tmp_name']);
    if ($check === false) {
        $error = "File is not an image.";
        $uploadOk = 0;
    }
    
    // Check file size (5MB max)
    if ($_FILES['club_photo']['size'] > 5000000) {
        $error = "File is too large (max 5MB).";
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($imageFileType, $allowed_extensions)) {
        $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }
    
    if ($uploadOk == 1 && move_uploaded_file($_FILES['club_photo']['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO club_photos (photo_path) VALUES (?)");
        $stmt->bind_param("s", $target_file);
        if ($stmt->execute()) {
            $success = "Photo uploaded successfully!";
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

// Handle photo deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT photo_path FROM club_photos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $photo = $result->fetch_assoc();
        if (file_exists($photo['photo_path'])) {
            unlink($photo['photo_path']);
        }
        $stmt = $conn->prepare("DELETE FROM club_photos WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Photo deleted successfully!";
        } else {
            $error = "Error deleting photo.";
        }
    }
}

// Get all photos
$photos = $conn->query("SELECT * FROM club_photos ORDER BY uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Photos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .photo-card {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .photo-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .photo-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            padding: 10px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .photo-actions a {
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
        }
        .photo-actions a:hover {
            background: rgba(255,255,255,0.2);
        }
        .photo-actions a.delete {
            color: #f72585;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-images me-2"></i> Club Photos</h1>
            <a href="admin_dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-upload me-2"></i> Upload New Photo
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="club_photo" class="form-label">Select Photo (JPG, PNG, GIF - max 5MB)</label>
                        <input class="form-control" type="file" id="club_photo" name="club_photo" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i> Upload Photo
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-images me-2"></i> All Photos</span>
                <span class="badge bg-light text-dark"><?= $photos->num_rows ?> photos</span>
            </div>
            <div class="card-body">
                <?php if($photos->num_rows > 0): ?>
                    <div class="photo-gallery">
                        <?php while($photo = $photos->fetch_assoc()): ?>
                            <div class="photo-card">
                                <img src="<?= htmlspecialchars($photo['photo_path']) ?>" alt="Club Photo">
                                <div class="photo-actions">
                                    <a href="<?= htmlspecialchars($photo['photo_path']) ?>" target="_blank" title="View Full Size">
                                        <i class="fas fa-expand"></i>
                                    </a>
                                    <a href="club_photos.php?delete=<?= $photo['id'] ?>" class="delete" title="Delete Photo" onclick="return confirm('Are you sure you want to delete this photo?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-images fa-4x text-muted mb-3"></i>
                        <h5>No photos uploaded yet</h5>
                        <p class="text-muted">Upload your first photo using the form above</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>