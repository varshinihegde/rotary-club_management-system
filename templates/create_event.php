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
$conn->set_charset("utf8mb4");

// Initialize variables
$error = '';
$success = '';
$event_id = '';

// Handle month navigation
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Adjust for invalid month values
if ($current_month < 1) {
    $current_month = 12;
    $current_year--;
} elseif ($current_month > 12) {
    $current_month = 1;
    $current_year++;
}

// Process event creation form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_event'])) {
        try {
            // Validate inputs (amount fields are now optional)
            $required_fields = ['event_type', 'event_name', 'event_date', 'event_location', 'e_description'];
            $missing_fields = array();
            
            foreach ($required_fields as $field) {
                if (empty(trim($_POST[$field]))) {
                    $missing_fields[] = $field;
                }
            }
            
            if (!empty($missing_fields)) {
                throw new Exception("Please fill in all required fields!");
            }

            // Handle optional amount fields
            $total_amount = !empty($_POST['total_amount']) ? $_POST['total_amount'] : NULL;
            $member_share = !empty($_POST['member_share']) ? $_POST['member_share'] : NULL;

            // Generate event ID
            $event_id = 'EVT-' . uniqid();
            
            // Insert event with NULL handling for amounts
            $stmt = $conn->prepare("INSERT INTO event (event_id, event_type, event_name, event_date, event_location, e_description, total_amount, member_share) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", 
                $event_id, 
                $_POST['event_type'], 
                $_POST['event_name'], 
                $_POST['event_date'], 
                $_POST['event_location'], 
                $_POST['e_description'], 
                $total_amount, 
                $member_share
            );
            
            if ($stmt->execute()) {
                $success = "Event created successfully! Event ID: $event_id";
                $_POST = array(); // Clear form
            } else {
                throw new Exception("Error creating event: " . $conn->error);
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    elseif (isset($_POST['upload_photos'])) {
        try {
            if (!isset($_FILES['event_photos']) || empty($_FILES['event_photos']['name'][0])) {
                throw new Exception("Please select at least one photo to upload!");
            }

            $event_id = $_POST['event_id'];
            $upload_dir = "uploads/events/$event_id/";
            
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create upload directory!");
                }
            }

            $uploaded_files = array();
            $file_count = count($_FILES['event_photos']['name']);
            
            if ($file_count > 3) {
                throw new Exception("Maximum 3 photos allowed per event!");
            }

            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['event_photos']['error'][$i] == UPLOAD_ERR_OK) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $_FILES['event_photos']['tmp_name'][$i]);
                    finfo_close($finfo);
                    
                    if (!in_array($mime, ['image/jpeg', 'image/png'])) {
                        throw new Exception("Only JPEG and PNG files are allowed!");
                    }

                    $file_ext = pathinfo($_FILES['event_photos']['name'][$i], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '_hall_location.' . $file_ext;
                    $target_file = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['event_photos']['tmp_name'][$i], $target_file)) {
                        $photo_id = 'PHOTO-' . uniqid();
                        $stmt = $conn->prepare("INSERT INTO event_photos (photo_id, event_id, photo_url, upload_time) VALUES (?, ?, ?, NOW())");
                        $stmt->bind_param("sss", $photo_id, $event_id, $target_file);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to save photo record: " . $conn->error);
                        }
                        $uploaded_files[] = $file_name;
                    } else {
                        throw new Exception("Failed to upload file: " . $_FILES['event_photos']['name'][$i]);
                    }
                }
            }

            if (!empty($uploaded_files)) {
                $success = count($uploaded_files) . " photo(s) uploaded successfully!";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get events for calendar
$first_day_of_month = date('Y-m-01', strtotime("$current_year-$current_month-01"));
$last_day_of_month = date('Y-m-t', strtotime($first_day_of_month));
$events = $conn->query("SELECT event_id, event_name, event_date FROM event WHERE event_date BETWEEN '$first_day_of_month' AND '$last_day_of_month' ORDER BY event_date");
$event_dates = array();
while ($event = $events->fetch_assoc()) {
    $day = date('j', strtotime($event['event_date']));
    $event_dates[$day][] = $event;
}

// Get recent events for photo upload dropdown
$recent_events = $conn->query("SELECT event_id, event_name FROM event ORDER BY event_date DESC LIMIT 10");

// Function to generate calendar for a specific month and year
function generateCalendar($month, $year, $event_dates) {
    $calendar = '';
    
    $first_day = mktime(0, 0, 0, $month, 1, $year);
    $days_in_month = date('t', $first_day);
    $day_of_week = date('w', $first_day);
    
    $month_name = date('F', $first_day);
    
    $calendar .= "<div class='month-calendar'>";
    $calendar .= "<div class='event-calendar'>";
    
    $days = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
    foreach ($days as $day) {
        $calendar .= "<div class='text-center fw-bold'>$day</div>";
    }
    
    for ($i = 0; $i < $day_of_week; $i++) {
        $calendar .= "<div class='calendar-day'></div>";
    }
    
    for ($day = 1; $day <= $days_in_month; $day++) {
        $is_today = ($day == date('j') && $month == date('n') && $year == date('Y')) ? 'today' : '';
        $has_event = isset($event_dates[$day]) ? 'has-event' : '';
        
        $calendar .= "<div class='calendar-day $is_today $has_event'>";
        $calendar .= "<div class='fw-bold'>$day</div>";
        
        if (isset($event_dates[$day])) {
            foreach ($event_dates[$day] as $event) {
                $calendar .= "<div class='small text-truncate' title='" . htmlspecialchars($event['event_name']) . "'>";
                $calendar .= "<i class='fas fa-calendar-check me-1'></i>";
                $calendar .= htmlspecialchars(substr($event['event_name'], 0, 10)) . "...";
                $calendar .= "</div>";
            }
        }
        
        $calendar .= "</div>";
    }
    
    $calendar .= "</div></div>";
    return $calendar;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event | Club Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .event-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin-top: 20px;
        }
        .calendar-day {
            border: 1px solid #ddd;
            padding: 5px;
            min-height: 60px;
        }
        .calendar-day.has-event {
            background-color: #e3f2fd;
        }
        .calendar-day.today {
            background-color: #bbdefb;
            font-weight: bold;
        }
        .card-header {
            background: linear-gradient(135deg, #4361ee, #4895ef);
            color: white;
        }
        .btn-primary {
            background-color: #4361ee;
            border-color: #4361ee;
        }
        .btn-primary:hover {
            background-color: #3a56d4;
            border-color: #3a56d4;
        }
        .photo-preview {
            max-width: 100px;
            max-height: 100px;
            margin: 5px;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .month-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .rupee-symbol {
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- Event Creation Form -->
                <div class="card mb-4 form-section">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-calendar-plus me-2"></i> Create New Event</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Event Type <span class="text-danger">*</span></label>
                                    <select class="form-select" name="event_type" required>
                                        <option value="">Select type</option>
                                        <option value="meeting" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                                        <option value="workshop" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                        <option value="social" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'social' ? 'selected' : ''; ?>>Social</option>
                                        <option value="cultural" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                        <option value="educational" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'educational' ? 'selected' : ''; ?>>Educational</option>
                                        <option value="other" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Event Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="event_name" value="<?php echo isset($_POST['event_name']) ? htmlspecialchars($_POST['event_name']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Event Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="event_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($_POST['event_date']) ? htmlspecialchars($_POST['event_date']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Location <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="event_location" value="<?php echo isset($_POST['event_location']) ? htmlspecialchars($_POST['event_location']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="e_description" rows="3" required><?php echo isset($_POST['e_description']) ? htmlspecialchars($_POST['e_description']) : ''; ?></textarea>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Total Budget</label>
                                    <div class="input-group">
                                        <span class="input-group-text rupee-symbol">₹</span>
                                        <input type="number" class="form-control" name="total_amount" step="0.01" min="0" value="<?php echo isset($_POST['total_amount']) ? htmlspecialchars($_POST['total_amount']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Member Share</label>
                                    <div class="input-group">
                                        <span class="input-group-text rupee-symbol">₹</span>
                                        <input type="number" class="form-control" name="member_share" step="0.01" min="0" value="<?php echo isset($_POST['member_share']) ? htmlspecialchars($_POST['member_share']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <button type="submit" name="create_event" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Create Event
                                    </button>
                                    <a href="admin_dashboard.php" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Event Calendar -->
                <div class="card mb-4 form-section">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Event Calendar</h4>
                    </div>
                    <div class="card-body">
                        <div class="month-navigation">
                            <a href="?month=<?php echo $current_month-1; ?>&year=<?php echo ($current_month == 1) ? $current_year-1 : $current_year; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left me-1"></i> Previous
                            </a>
                            <div class="month-title">
                                <?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?>
                            </div>
                            <a href="?month=<?php echo $current_month+1; ?>&year=<?php echo ($current_month == 12) ? $current_year+1 : $current_year; ?>" class="btn btn-outline-primary">
                                Next <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </div>
                        <?php echo generateCalendar($current_month, $current_year, $event_dates); ?>
                    </div>
                </div>
                
                <!-- Photo Upload Section -->
                <div class="card form-section">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-camera me-2"></i> Upload Photos of Hall/Location</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Note:</strong> Upload photos of the hall/location where the event will take place.<br>
                            Maximum 3 photos allowed (JPEG or PNG only). Photos will be archived after 1 month.
                        </div>
                        
                        <form method="POST" action="" enctype="multipart/form-data" id="photoUploadForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Select Event <span class="text-danger">*</span></label>
                                    <select class="form-select" name="event_id" required>
                                        <option value="">Select an event</option>
                                        <?php while ($event = $recent_events->fetch_assoc()): ?>
                                            <option value="<?php echo htmlspecialchars($event['event_id']); ?>">
                                                <?php echo htmlspecialchars($event['event_name']) . " (" . htmlspecialchars($event['event_id']) . ")"; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Select Photos <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" name="event_photos[]" multiple accept="image/jpeg, image/png" id="photoInput">
                                </div>
                                
                                <div class="col-12">
                                    <div id="photoPreview" class="d-flex flex-wrap mb-3"></div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" name="upload_photos" class="btn btn-success">
                                        <i class="fas fa-upload me-2"></i> Upload Photos
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation and photo preview
        document.addEventListener('DOMContentLoaded', function() {
            const photoInput = document.getElementById('photoInput');
            const photoPreview = document.getElementById('photoPreview');
            
            if (photoInput && photoPreview) {
                photoInput.addEventListener('change', function() {
                    photoPreview.innerHTML = '';
                    
                    if (this.files.length > 3) {
                        alert('Maximum 3 photos allowed!');
                        this.value = '';
                        return;
                    }
                    
                    Array.from(this.files).forEach(file => {
                        if (!file.type.match('image.*')) {
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.classList.add('photo-preview');
                            img.classList.add('img-thumbnail');
                            photoPreview.appendChild(img);
                        }
                        reader.readAsDataURL(file);
                    });
                });
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>