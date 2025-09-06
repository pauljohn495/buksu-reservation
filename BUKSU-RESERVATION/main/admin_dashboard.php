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

// Handle success/error messages
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Get room stats
$room_stats = $conn->query("
    SELECT 
        COUNT(*) as total_rooms,
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available_rooms,
        SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied_rooms,
        SUM(queue_count) as total_in_queue
    FROM rooms
");
$stats = $room_stats->fetch_assoc();

// Fetch all rooms
$rooms_sql = "SELECT * FROM rooms ORDER BY id";
$rooms_result = $conn->query($rooms_sql);

// Handle delete room
if (isset($_GET['delete_id']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['admin_csrf_token']) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        header('Location: admin_dashboard.php?success=Room deleted successfully');
    } else {
        header('Location: admin_dashboard.php?error=Failed to delete room');
    }
    exit();
}

// Handle update status
if (isset($_GET['update_id']) && isset($_GET['status']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['admin_csrf_token']) {
    $update_id = (int)$_GET['update_id'];
    $new_status = $_GET['status'];
    
    if (in_array($new_status, ['Available', 'Occupied', 'Maintenance'])) {
        $stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $update_id);
        if ($stmt->execute()) {
            header('Location: admin_dashboard.php?success=Room status updated successfully');
        } else {
            header('Location: admin_dashboard.php?error=Failed to update room status');
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BUKSU Library System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color:rgb(223, 223, 223);
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
            background-color:rgb(255, 255, 255);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            padding: 20px;
        }
        .stats-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,.1);
            padding: 20px;
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 10px rgba(0,0,0,.15);
        }
        .stats-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.5rem;
            margin-bottom: 15px;
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
                        <a class="nav-link active" href="admin_dashboard.php">
                            <i class='bx bx-home'></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="rooms.php">
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
                    <h1 class="h2">Admin Dashboard</h1>
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

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon bg-primary text-white">
                                <i class='bx bx-building'></i>
                            </div>
                            <h5>Total Rooms</h5>
                            <h2><?php echo $stats['total_rooms']; ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon bg-success text-white">
                                <i class='bx bx-check-circle'></i>
                            </div>
                            <h5>Available Rooms</h5>
                            <h2><?php echo $stats['available_rooms']; ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon bg-warning text-white">
                                <i class='bx bx-user'></i>
                            </div>
                            <h5>Occupied Rooms</h5>
                            <h2><?php echo $stats['occupied_rooms']; ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon bg-info text-white">
                                <i class='bx bx-group'></i>
                            </div>
                            <h5>Users in Queue</h5>
                            <h2><?php echo $stats['total_in_queue']; ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Rooms Table -->
                <div class="card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">All Rooms</h5>
                        </div>
                    </div>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rooms_result && $rooms_result->num_rows > 0): ?>
                                        <?php while ($room = $rooms_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($room['id']); ?></td>
                                                <td><?php echo htmlspecialchars($room['room_name']); ?></td>
                                                <td><?php echo htmlspecialchars($room['category']); ?></td>
                                                <td>
                                                    <?php if ($room['status'] == 'Available'): ?>
                                                        <span class="badge bg-success">Available</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Occupied</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($room['queue_count']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No rooms found</td>
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
                            <input type="text" class="form-control" id="room_name" name="room_name" required 
                                   pattern="[A-Za-z0-9\s-]+" title="Only letters, numbers, spaces, and hyphens are allowed">
                            <div class="form-text">Enter the name of the room here</div>
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

    <!-- Add Room Form Validation -->
    <script>
        $(document).ready(function() {
            // Initialize all modals
            var modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                new bootstrap.Modal(modal);
            });

            // Add Room Form Validation
            const addRoomForm = document.getElementById('addRoomForm');
            
            if (addRoomForm) {
                addRoomForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    
                    // Basic validation
                    const roomName = document.getElementById('room_name').value.trim();
                    const capacity = document.getElementById('capacity').value;
                    const timeLimit = document.getElementById('time_limit').value;
                    
                    let isValid = true;
                    let errorMessage = '';
                    
                    // Room name validation
                    if (!/^[A-Za-z0-9\s-]+$/.test(roomName)) {
                        errorMessage += 'Room name can only contain letters, numbers, spaces, and hyphens.\n';
                        isValid = false;
                    }
                    
                    // Capacity validation
                    if (capacity < 1 || capacity > 100) {
                        errorMessage += 'Capacity must be between 1 and 100.\n';
                        isValid = false;
                    }
                    
                    // Time limit validation
                    if (timeLimit < 30 || timeLimit > 480) {
                        errorMessage += 'Time limit must be between 30 minutes and 8 hours.\n';
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        alert('Please correct the following errors:\n\n' + errorMessage);
                        return;
                    }
                    
                    // If validation passes, submit the form
                    this.submit();
                });
            }
            
            // Real-time character count for description
            const description = document.getElementById('description');
            if (description) {
                description.addEventListener('input', function() {
                    const remaining = 500 - this.value.length;
                    this.nextElementSibling.textContent = `${remaining} characters remaining`;
                });
            }

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Handle action buttons
            $('.action-btn').on('click', function(e) {
                e.preventDefault();
                if ($(this).data('confirm')) {
                    if (confirm($(this).data('confirm'))) {
                        window.location.href = $(this).attr('href');
                    }
                } else {
                    window.location.href = $(this).attr('href');
                }
            });
        });
    </script>
</body>
</html>
