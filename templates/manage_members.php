<?php
// ERROR REPORTING - Enable all for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// SESSION START
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// REDIRECT IF NOT LOGGED IN
if (!isset($_SESSION['admin_logged_in'])) {
    die(header("Location: admin_login.php"));
}

// DATABASE CONNECTION
$conn = new mysqli('localhost', 'root', '', 'club management');

// VERIFY CONNECTION
if ($conn->connect_error) {
    die("<div class='alert alert-danger'>Database connection failed: " . $conn->connect_error . "</div>");
}

// VERIFY MEMBERS TABLE EXISTS
$table_check = $conn->query("SHOW TABLES LIKE 'members'");
if ($table_check->num_rows == 0) {
    die("<div class='alert alert-danger'>Error: 'members' table doesn't exist in database</div>");
}

// DELETE FUNCTIONALITY
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_member'])) {
    $member_id = $conn->real_escape_string($_POST['member_id']);
    
    // First get the profile photo path to delete the file
    $photo_query = "SELECT profile_photo FROM members WHERE member_id = ?";
    $stmt = $conn->prepare($photo_query);
    $stmt->bind_param("s", $member_id);
    $stmt->execute();
    $photo_result = $stmt->get_result();
    $photo_row = $photo_result->fetch_assoc();
    $stmt->close();
    
    // Delete the photo file if it exists
    if (!empty($photo_row['profile_photo']) && file_exists($photo_row['profile_photo'])) {
        unlink($photo_row['profile_photo']);
    }
    
    // Now delete the member record
    $delete_query = "DELETE FROM members WHERE member_id = ?";
    $stmt = $conn->prepare($delete_query);
    
    if ($stmt === false) {
        die("<div class='alert alert-danger'>Prepare failed: " . $conn->error . "</div>");
    }
    
    $stmt->bind_param("s", $member_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Member deleted successfully";
    } else {
        $_SESSION['message'] = "Delete failed: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: members.php");
    exit();
}

// SEARCH FUNCTIONALITY
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$query = "SELECT * FROM members";
$params = [];

if (!empty($search)) {
    $query .= " WHERE m_name LIKE ? OR m_email LIKE ? OR member_id LIKE ?";
    $search_term = "%$search%";
    $params = array_fill(0, 3, $search_term);
}

$query .= " ORDER BY join_date DESC";

// EXECUTE QUERY
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("<div class='alert alert-danger'>Prepare failed: " . $conn->error . "</div>");
    }
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
    if ($result === false) {
        die("<div class='alert alert-danger'>Query failed: " . $conn->error . "</div>");
    }
}

// GET TOTAL COUNT
$count_result = $conn->query("SELECT COUNT(*) FROM members");
$total_members = $count_result ? $count_result->fetch_row()[0] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 20px; background-color: #f8f9fa; }
        .container { max-width: 1200px; }
        .table-responsive { margin-top: 20px; }
        .search-box { margin-bottom: 20px; }
        .action-btn { margin: 0 3px; }
        .member-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .member-photo:hover {
            transform: scale(1.1);
        }
        .photo-header {
            width: 60px;
        }
        /* Modal styles for full-size photo */
        .photo-modal {
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
        .modal-photo {
            display: block;
            max-width: 80%;
            max-height: 80%;
            margin: 5% auto;
        }
        .close-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        .member-info {
            color: white;
            text-align: center;
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- SIMPLIFIED NAVIGATION FOR TESTING -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">Club Management</a>
                <div class="navbar-nav">
                    <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    <a class="nav-link active" href="members.php">Members</a>
                </div>
            </div>
        </nav>

        <h2 class="mb-4"><i class="fas fa-users"></i> Manage Members</h2>
        
        <!-- MESSAGES -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-info"><?= $_SESSION['message'] ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- SEARCH BOX -->
        <div class="card search-box">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-9">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name, email, or ID" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                        <?php if ($search): ?>
                            <a href="members.php" class="btn btn-outline-secondary w-100 mt-2">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- MEMBERS TABLE -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <span>Total Members: <?= $total_members ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="photo-header">Photo</th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($row['profile_photo'])): ?>
                                            <img src="<?= htmlspecialchars($row['profile_photo']) ?>" 
                                                 alt="Profile Photo" 
                                                 class="member-photo"
                                                 onclick="showFullPhoto('<?= htmlspecialchars($row['profile_photo']) ?>', '<?= htmlspecialchars($row['m_name']) ?>')"
                                                 onerror="this.src='assets/default-profile.jpg'">
                                        <?php else: ?>
                                            <img src="assets/default-profile.jpg" 
                                                 alt="Default Profile" 
                                                 class="member-photo"
                                                 onclick="showFullPhoto('assets/default-profile.jpg', '<?= htmlspecialchars($row['m_name']) ?>')">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['member_id']) ?></td>
                                    <td><?= htmlspecialchars($row['m_name']) ?></td>
                                    <td><?= htmlspecialchars($row['m_email']) ?></td>
                                    <td><?= htmlspecialchars($row['m_phone_no'] ?? 'N/A') ?></td>
                                    <td><?= date('M j, Y', strtotime($row['join_date'])) ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="member_id" value="<?= $row['member_id'] ?>">
                                            <button type="submit" name="delete_member" class="btn btn-danger btn-sm action-btn"
                                                    onclick="return confirm('Are you sure? This will permanently delete the member and their profile photo.')">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        No members found <?= $search ? 'matching your search' : '' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Full Photo Modal -->
    <div id="photoModal" class="photo-modal">
        <span class="close-modal" onclick="closePhotoModal()">&times;</span>
        <img id="modalPhoto" class="modal-photo">
        <div id="memberName" class="member-info"></div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Show full-size photo in modal
        function showFullPhoto(photoSrc, memberName) {
            const modal = document.getElementById('photoModal');
            const modalImg = document.getElementById('modalPhoto');
            const nameElement = document.getElementById('memberName');
            
            modal.style.display = "block";
            modalImg.src = photoSrc;
            nameElement.textContent = memberName;
            
            // Close modal if clicked outside the image
            modal.onclick = function(event) {
                if (event.target === modal) {
                    closePhotoModal();
                }
            }
        }
        
        // Close the modal
        function closePhotoModal() {
            document.getElementById('photoModal').style.display = "none";
        }
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closePhotoModal();
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>