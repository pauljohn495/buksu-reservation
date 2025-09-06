<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../includes/db.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf_token'];

// Handle room deletion
if (isset($_GET['delete_id']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['admin_csrf_token']) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        header('Location: rooms.php?success=Room deleted successfully');
    } else {
        header('Location: rooms.php?error=Failed to delete room');
    }
    exit();
}

// Handle room updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['admin_csrf_token']) {
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $room_id = (int)$_POST['room_id'];
        $room_name = $_POST['room_name'];
        $category = $_POST['category'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE rooms SET room_name = ?, category = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssi", $room_name, $category, $status, $room_id);
        
        if ($stmt->execute()) {
            header('Location: rooms.php?success=Room updated successfully');
        } else {
            header('Location: rooms.php?error=Failed to update room');
        }
        exit();
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $room_name = $_POST['room_name'];
        $category = $_POST['category'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("INSERT INTO rooms (room_name, category, status) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $room_name, $category, $status);
        
        if ($stmt->execute()) {
            header('Location: rooms.php?success=Room added successfully');
        } else {
            header('Location: rooms.php?error=Failed to add room');
        }
        exit();
    }
}

// Handle room release
if (isset($_GET['release_id']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['admin_csrf_token']) {
    $release_id = (int)$_GET['release_id'];
    
    // Update room status to Available and clear booked_at
    $stmt = $conn->prepare("UPDATE rooms SET status = 'Available', booked_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $release_id);
    
    if ($stmt->execute()) {
        // Clear the queue for this room
        $clear_queue = $conn->prepare("DELETE FROM room_queue WHERE room_id = ?");
        $clear_queue->bind_param("i", $release_id);
        $clear_queue->execute();
        
        // Reset queue count
        $reset_queue = $conn->prepare("UPDATE rooms SET queue_count = 0 WHERE id = ?");
        $reset_queue->bind_param("i", $release_id);
        $reset_queue->execute();
        
        header('Location: rooms.php?success=Room released successfully');
    } else {
        header('Location: rooms.php?error=Failed to release room');
    }
    exit();
}

// Fetch all rooms and the current user if booked
$rooms_sql = "SELECT r.*, u.email as booked_user_email FROM rooms r LEFT JOIN users u ON r.booked_by = u.id ORDER BY r.id";
$rooms_result = $conn->query($rooms_sql);
if (!$rooms_result) {
    die('Query failed: ' . $conn->error);
}

// Handle success/error messages
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - BUKSU Library System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: rgb(223, 223, 223);
        }
        .sidebar {
            background-color: #001c38;
            min-height: 100vh;
            color: white;
            position: sticky;
            top: 0;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
            padding: 0.8rem 1rem;
            border-radius: 5px;
            margin: 0.2rem 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: black;
            background-color: rgb(255, 255, 255);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,.1);
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        #addRoomModal .btn-close {
            position: absolute;
            right: 15px;
            top: 15px;
        }
    </style>
</head>
<body>
    <!-- Bootstrap Admin Modal -->
    <div class="modal fade" id="adminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="modalRoomName"></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Current User:  <span id="currentUser"></span></p>
                    <p>Time Remaining: <span id="adminTimer">00:00:00</span></p>
                    <p>Queue Count: <span id="adminQueueCount"></span></p>
                    <button type="button" class="btn btn-danger" onclick="generateReport()">
                        <i class='bx bx-printer'></i> Print Reports
                    </button>
                </div>
                <div class="modal-footer">
                    <button id="nextQueueBtn" class="btn btn-primary" style="display:none;" onclick="nextQueueAction()">Next Queue</button>

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden printable section -->
    <div id="adminPrintSection" style="display:none;">
        <h2>Room Booking Report</h2>
        <p><strong>Room Name:</strong> <span id="printRoomName"></span></p>
        <p><strong>User Email:</strong> <span id="printUserEmail"></span></p>
        <p><strong>Booking Date & Time:</strong> <span id="printBookingDate"></span></p>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="pt-4 pb-3 px-3 text-center">
                    <h4>BUKSU Library</h4>
                    <p>Admin Panel</p>
                </div>
                <hr class="mx-3">
                <ul class="nav flex-column mb-2 px-2">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class='bx bx-home'></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="rooms.php">
                            <i class='bx bx-building'></i>
                            Rooms
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="booking_history.php">
                            <i class='bx bx-history'></i>
                            Booking History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="accounts.php">
                            <i class='bx bx-user'></i>
                            Accounts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class='bx bx-log-out'></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Manage Rooms</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                        <i class='bx bx-plus'></i> Add New Room
                    </button>
                </div>

                <!-- Success/Error Messages -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Rooms Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Room Name</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Queue Count</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rooms_result && $rooms_result->num_rows > 0): ?>
                                        <?php while ($room = $rooms_result->fetch_assoc()): ?>
                                            <tr
                                                <?php if ($room['status'] == 'Occupied'): ?>
                                                    style="cursor:pointer;"
                                                    data-room-name="<?php echo htmlspecialchars($room['room_name']); ?>"
                                                    data-booked-user-email="<?php echo htmlspecialchars($room['booked_user_email'] ? $room['booked_user_email'] : 'Unknown User'); ?>"
                                                    data-booked-at="<?php echo htmlspecialchars($room['booked_at']); ?>"
                                                    data-time-limit="<?php echo (int)$room['time_limit']; ?>"
                                                    data-queue-count="<?php echo (int)$room['queue_count']; ?>"
                                                    data-room-id="<?php echo (int)$room['id']; ?>"
                                                <?php endif; ?>
                                            >
                                                <td><?php echo htmlspecialchars($room['id']); ?></td>
                                                <td
                                                    <?php if ($room['status'] == 'Occupied'): ?>
                                                        style="cursor:pointer; text-decoration:underline;" onclick="showAdminModalFromRow(this.parentElement)"
                                                    <?php endif; ?>
                                                ><?php echo html_entity_decode($room['room_name']); ?></td>
                                                <td><?php echo htmlspecialchars($room['category']); ?></td>
                                                <td>
                                                    <?php if ($room['status'] == 'Occupied'): ?>
                                                        <span class="badge bg-warning text-dark">Occupied</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Available</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td id="queue-count-<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['queue_count']); ?></td>
                                                <td class="action-buttons">
                                                    <a href="edit_room.php?id=<?php echo $room['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class='bx bx-edit'></i>
                                                    </a>
                                                    <a href="rooms.php?release_id=<?php echo $room['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" 
                                                       class="btn btn-sm btn-success" 
                                                       onclick="event.stopPropagation(); return confirm('Are you sure you want to release this room? This will clear the queue.')">
                                                        <i class='bx bx-check-circle'></i>
                                                    </a>
                                                    <a href="rooms.php?delete_id=<?php echo $room['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="event.stopPropagation(); return confirm('Are you sure you want to delete this room?')">
                                                        <i class='bx bx-trash'></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No rooms found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let adminModalRoomId = null;
        function showAdminModal(roomName, userEmail, bookedAt, timeLimit, queueCount, roomId) {
            document.getElementById('modalRoomName').textContent = roomName;
            document.getElementById('currentUser').textContent = userEmail;
            document.getElementById('adminQueueCount').textContent = queueCount;
            // Calculate end time
            const endTime = new Date(new Date(bookedAt).getTime() + timeLimit * 60000);
            startAdminCountdown('adminTimer', endTime);
            // Show/hide Next Queue button
            adminModalRoomId = roomId;
            const nextBtn = document.getElementById('nextQueueBtn');
            if (queueCount > 0) {
                nextBtn.style.display = 'inline-block';
                nextBtn.disabled = false;
            } else {
                nextBtn.style.display = 'none';
            }
           
            var modal = new bootstrap.Modal(document.getElementById('adminModal'));
            modal.show();
        }
        function closeAdminModal() {
            var modalEl = document.getElementById('adminModal');
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.hide();
            if (window.adminTimerInterval) clearInterval(window.adminTimerInterval);
            location.reload();
        }
        function startAdminCountdown(elementId, endTime) {
            const timerElement = document.getElementById(elementId);
            function updateCountdown() {
                const now = new Date().getTime();
                const distance = endTime - now;
                if (distance < 0) {
                    timerElement.textContent = "Time's up!";
                    clearInterval(window.adminTimerInterval);
                    return;
                }
                const hours = Math.floor(distance / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                timerElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
            updateCountdown();
            if (window.adminTimerInterval) clearInterval(window.adminTimerInterval);
            window.adminTimerInterval = setInterval(updateCountdown, 1000);
        }
        function nextQueueAction() {
            if (!adminModalRoomId) return;
            const csrfToken = '<?php echo $csrf_token; ?>';
            const btn = document.getElementById('nextQueueBtn');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            fetch('next_queue.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `room_id=${adminModalRoomId}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setTimeout(() => {
                        fetch(`get_room_booking.php?room_id=${adminModalRoomId}`)
                            .then(response => response.json())
                            .then(info => {
                                if (info.success) {
                                    showAdminModal(
                                        info.room_name,
                                        info.booked_user_email,
                                        info.booked_at,
                                        info.time_limit,
                                        info.queue_count,
                                        adminModalRoomId
                                    );
                                    // Update the table queue count cell
                                    var queueCell = document.getElementById('queue-count-' + adminModalRoomId);
                                    if (queueCell) queueCell.textContent = info.queue_count;
                                    // Update the row's data attributes
                                    var row = document.querySelector('tr[data-room-id="' + adminModalRoomId + '"]');
                                    if (row) {
                                        row.setAttribute('data-booked-user-email', info.booked_user_email);
                                        row.setAttribute('data-booked-at', info.booked_at);
                                        row.setAttribute('data-time-limit', info.time_limit);
                                        row.setAttribute('data-queue-count', info.queue_count);
                                    }
                                    // Refresh the page
                                    closeAdminModal();
                                    location.reload();
                                } else {
                                    closeAdminModal();
                                    // Update the table and modal queue count cell to 0
                                    var queueCell = document.getElementById('queue-count-' + adminModalRoomId);
                                    if (queueCell) queueCell.textContent = 0;
                                    var modalQueue = document.getElementById('adminQueueCount');
                                    if (modalQueue) modalQueue.textContent = 0;
                                }
                            });
                    }, 200);
                } else {
                    alert('Failed to proceed to next queue: ' + (data.error || 'Unknown error'));
                    // If the error is 'No users in queue', update the table and modal queue count to 0
                    if ((data.error || '').toLowerCase().includes('no users in queue')) {
                        var queueCell = document.getElementById('queue-count-' + adminModalRoomId);
                        if (queueCell) queueCell.textContent = 0;
                        var modalQueue = document.getElementById('adminQueueCount');
                        if (modalQueue) modalQueue.textContent = 0;
                    }
                }
            })
            .catch(() => {
                alert('Network error.');
                btn.disabled = false;
                btn.textContent = 'Next Queue';
            });
        }
        function showAdminModalFromRow(row) {
            console.log('Clicked row:', row);
            // Only show modal if there is a valid user email
            var email = row.getAttribute('data-booked-user-email');
            if (!email || email === 'Unknown User' || email === 'undefined') {
                closeAdminModal();
                return;
            }
            showAdminModal(
                row.getAttribute('data-room-name'),
                email,
                row.getAttribute('data-booked-at'),
                parseInt(row.getAttribute('data-time-limit')),
                parseInt(row.getAttribute('data-queue-count')),
                parseInt(row.getAttribute('data-room-id'))
            );
        }
        document.addEventListener('DOMContentLoaded', function() {
            // Always hide the admin modal on page load
            var adminModal = document.getElementById('adminModal');
            if (adminModal) adminModal.classList.remove('active');
        });
        document.addEventListener('hidden.bs.modal', function () {
            // Remove any stuck Bootstrap modal backdrops and modal-open class
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
        });
        function printAdminReport() {
            document.getElementById('printRoomName').textContent = document.getElementById('modalRoomName').textContent;
            document.getElementById('printUserEmail').textContent = document.getElementById('currentUser').textContent;
            document.getElementById('printBookingDate').textContent = document.getElementById('adminTimer').textContent;
            var printContents = document.getElementById('adminPrintSection').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }

        function generateReport() {
            if (!adminModalRoomId) return;
            
            const roomName = document.getElementById('modalRoomName').textContent;
            const userEmail = document.getElementById('currentUser').textContent;
            const currentTime = new Date().toLocaleString();
            
            // Open the PDF generation page in a new window
            window.open(`generate_report.php?room_id=${adminModalRoomId}&room_name=${encodeURIComponent(roomName)}&user_email=${encodeURIComponent(userEmail)}&current_time=${encodeURIComponent(currentTime)}`, '_blank');
        }
    </script>

    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1" aria-labelledby="addRoomModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRoomModalLabel">Add New Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="add_room.php" method="post" id="addRoomForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3">
                            <label for="room_name" class="form-label">Room Name*</label>
                            <input type="text" class="form-control" id="room_name" name="room_name" required title="Enter the name of the room here">
                        </div>
                        <div class="mb-3">
                            <label for="category" class="form-label">Category*</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select a category</option>
                                <option value="Discussion">Discussion</option>
                                <option value="Makers Space">Makers Space</option>
                                <option value="Conference">Conference</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Initial Status*</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Available">Available</option>
                                <option value="Occupied">Occupied</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 