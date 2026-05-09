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

// Get all club photos from database
$photos = [];
$query = "SELECT * FROM club_photos ORDER BY uploaded_at DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $photos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Photos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
        }

        .header {
            background: white;
            padding: 15px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .photos-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .photo-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s;
        }

        .photo-card:hover {
            transform: scale(1.02);
        }

        .photo-img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .photo-info {
            padding: 15px;
        }

        .upload-date {
            color: #666;
            font-size: 0.9rem;
        }

        .no-photos {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            overflow: auto;
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="header">
    <h2>Club Photos</h2>
</div>

<div class="photos-container">
    <?php if (count($photos) > 0): ?>
        <div class="photos-grid">
            <?php foreach ($photos as $photo): ?>
                <div class="photo-card" onclick="openModal('<?php echo htmlspecialchars($photo['photo_path']); ?>')">
                    <img src="<?php echo htmlspecialchars($photo['photo_path']); ?>" alt="Club Photo" class="photo-img">
                    <div class="photo-info">
                        <div class="upload-date">
                            Uploaded: <?php echo date('M j, Y', strtotime($photo['uploaded_at'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-photos">
            <i class="fas fa-camera" style="font-size: 3rem; margin-bottom: 15px;"></i>
            <h3>No photos available yet</h3>
            <p>Check back later for club photos uploaded by admin</p>
        </div>
    <?php endif; ?>
</div>

<!-- The Modal -->
<div id="imageModal" class="modal">
    <span class="close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="expandedImg">
</div>

<script>
    // Open the modal with clicked image
    function openModal(imgSrc) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('expandedImg');
        modal.style.display = "block";
        modalImg.src = imgSrc;
    }

    // Close the modal
    function closeModal() {
        document.getElementById('imageModal').style.display = "none";
    }

    // Close when clicking outside the image
    window.onclick = function(event) {
        const modal = document.getElementById('imageModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

</body>
</html>