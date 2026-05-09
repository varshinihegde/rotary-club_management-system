<?php
// events.php - Member Event Viewing Page

// 1. Start session
session_start();

// 2. Database Connection
$host = "localhost";
$username = "root";
$password = "";
$database = "club management";

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// 3. Fetch only future events (fixed query)
$events = [];
$sql = "SELECT * FROM event 
        WHERE event_date >= CURDATE()
        ORDER BY event_date ASC";

$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
}

// 4. Function to get location photos for an event
function getLocationPhotos($conn, $event_id) {
    $photos = [];
    $escaped_id = mysqli_real_escape_string($conn, $event_id);
    $sql = "SELECT photo_url FROM event_photos WHERE event_id = '$escaped_id'";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $photos[] = $row['photo_url'];
        }
    }
    return $photos;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Club Events</title>
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #27ae60;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gray: #95a5a6;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: var(--secondary);
            color: white;
            padding: 30px 20px;
            margin-bottom: 30px;
        }
        h1 {
            margin-bottom: 10px;
        }
        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        .event-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .event-header {
            background-color: var(--primary);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
        }
        .event-title {
            font-size: 1.3rem;
            margin: 0;
        }
        .event-type {
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .event-body {
            padding: 20px;
        }
        .event-detail {
            margin-bottom: 12px;
        }
        .detail-label {
            font-weight: bold;
            color: var(--gray);
        }
        .event-date {
            color: var(--dark);
            font-weight: 500;
        }
        .cost-badge {
            background: var(--success);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            display: inline-block;
        }
        .photo-section {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .photo-item {
            height: 120px;
            border-radius: 5px;
            overflow: hidden;
            cursor: pointer;
            position: relative;
        }
        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .photo-item:hover img {
            transform: scale(1.05);
        }
        .no-events {
            text-align: center;
            padding: 40px;
            grid-column: 1/-1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Modal for enlarged photo viewing */
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
            margin-top: 50px;
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
        
        @media (max-width: 768px) {
            .event-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Upcoming Club Events</h1>
            <p>View and register for upcoming club activities</p>
        </div>
    </header>

    <div class="container">
        <div class="event-grid">
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <div class="event-header">
                            <h3 class="event-title"><?= htmlspecialchars($event['event_name']) ?></h3>
                            <span class="event-type"><?= htmlspecialchars($event['event_type']) ?></span>
                        </div>
                        <div class="event-body">
                            <div class="event-detail">
                                <div class="detail-label">Date</div>
                                <div class="event-date">
                                    <?= date('F j, Y', strtotime($event['event_date'])) ?>
                                    (<?= date('l', strtotime($event['event_date'])) ?>)
                                </div>
                            </div>
                            <div class="event-detail">
                                <div class="detail-label">Location</div>
                                <div><?= htmlspecialchars($event['event_location']) ?></div>
                            </div>
                            <div class="event-detail">
                                <div class="detail-label">Cost</div>
                                <div>
                                    <?php if ($event['member_share'] > 0): ?>
                                        <span class="cost-badge">₹<?= number_format($event['member_share'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="cost-badge free-badge">Free</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="event-detail">
                                <div class="detail-label">Description</div>
                                <p><?= nl2br(htmlspecialchars($event['e_description'])) ?></p>
                            </div>
                            
                            <div class="photo-section">
                                <div class="detail-label">Location Photos</div>
                                <?php $photos = getLocationPhotos($conn, $event['event_id']); ?>
                                <?php if (!empty($photos)): ?>
                                    <div class="photo-grid">
                                        <?php foreach ($photos as $photo): ?>
                                            <div class="photo-item" onclick="openModal('<?= htmlspecialchars($photo) ?>')">
                                                <img src="<?= htmlspecialchars($photo) ?>" alt="Location photo">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p>No location photos available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-events">
                    <p>There are currently no upcoming events scheduled.</p>
                    <p>Please check back later or contact club administration.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for enlarged photo viewing -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <script>
        // Function to open modal with clicked image
        function openModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = "block";
            modalImg.src = imageSrc;
        }

        // Function to close modal
        function closeModal() {
            document.getElementById('imageModal').style.display = "none";
        }

        // Close modal when clicking outside the image
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>