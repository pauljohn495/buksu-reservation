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

// Fetch booking history
$user_email = isset($_GET['user_email']) ? $_GET['user_email'] : '';
$query = "SELECT bh.room_name, bh.user_email, bh.booked_at 
          FROM booking_history bh 
          WHERE 1=1";
if (!empty($user_email)) {
    $query .= " AND bh.user_email = '" . $conn->real_escape_string($user_email) . "'";
}
$query .= " ORDER BY bh.booked_at DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - BUKSU Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
                        <a class="nav-link" href="admin_dashboard.php">
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
                        <a class="nav-link active" href="booking_history.php">
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
                <h1 class="mb-4">Booking History</h1>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Room Name</th>
                                        <th>User Email</th>
                                        <th>Booking Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['user_email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['booked_at']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No booking history found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <form action="empty_booking_history.php" method="post" style="margin-bottom: 20px;">
                            <button type="submit" class="btn btn-danger">Empty Booking History</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 