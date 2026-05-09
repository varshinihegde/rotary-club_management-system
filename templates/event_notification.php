<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'club management');
if ($conn->connect_error) {
    exit();
}

// Check if we've shown notifications in this session
if (isset($_SESSION['events_notified'])) {
    exit();
}

// Get events happening within 2 days
$today = date('Y-m-d');
$two_days_later = date('Y-m-d', strtotime('+2 days'));

$query = "SELECT event_name, event_date FROM event 
          WHERE event_date BETWEEN '$today' AND '$two_days_later'
          ORDER BY event_date ASC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    // Mark as shown in this session
    $_SESSION['events_notified'] = true;
    ?>
    <div class="modal fade" id="eventNotification" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-day me-2"></i>
                        Upcoming Events Alert!
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>These events are happening soon:</p>
                    <ul class="list-group">
                        <?php foreach ($events as $event): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($event['event_name']) ?>
                            <span class="badge bg-primary">
                                <?= date('M j', strtotime($event['event_date'])) ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var notificationModal = new bootstrap.Modal(document.getElementById('eventNotification'));
        notificationModal.show();
    });
    </script>
    <?php
}
$conn->close();
?>