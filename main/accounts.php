<?php
require '../includes/db.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$success = '';
$error = '';

// Handle new admin creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Password complexity validation
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('/[!@#$%^&*()_+\-=\[\]{};\'"\\|,.<>\/?]/', $password);
        $length = strlen($password) >= 8;

        // Debug information
        $error = 'Debug info: ';
        $error .= 'Length: ' . strlen($password) . ' ';
        $error .= 'Uppercase: ' . ($uppercase ? 'Yes' : 'No') . ' ';
        $error .= 'Lowercase: ' . ($lowercase ? 'Yes' : 'No') . ' ';
        $error .= 'Number: ' . ($number ? 'Yes' : 'No') . ' ';
        $error .= 'Special: ' . ($specialChars ? 'Yes' : 'No') . ' ';

        if(!$uppercase || !$lowercase || !$number || !$specialChars || !$length) {
            $error .= '<br>Password requirements not met:';
            if(!$length) $error .= ' Must be at least 8 characters.';
            if(!$uppercase) $error .= ' Must include uppercase letter.';
            if(!$lowercase) $error .= ' Must include lowercase letter.';
            if(!$number) $error .= ' Must include number.';
            if(!$specialChars) $error .= ' Must include special character (!@#$%^&*()_+-=[]{};\'"\\|,.<>/?).';
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
            $stmt->bind_param('ss', $username, $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = 'Username or email already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $username, $email, $hashed_password);
                if ($stmt->execute()) {
                    $success = 'Admin account created successfully!';
                } else {
                    $error = 'Failed to create admin account.';
                }
            }
            $stmt->close();
        }
    }
}

// Handle admin deletion
if (isset($_POST['delete_admin_id'])) {
    $delete_id = intval($_POST['delete_admin_id']);
    // Prevent deleting your own account
    if ($delete_id == $_SESSION['admin_id']) {
        $error = 'You cannot delete your own admin account while logged in.';
    } else {
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param('i', $delete_id);
        if ($stmt->execute()) {
            $success = 'Admin account deleted successfully!';
        } else {
            $error = 'Failed to delete admin account.';
        }
        $stmt->close();
    }
}

// Fetch all admin accounts
$admins_result = $conn->query("SELECT id, username, email FROM admins ORDER BY id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts - BUKSU Library System</title>
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
        .form-section {
            margin-bottom: 2rem;
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
                        <a class="nav-link" href="booking_history.php">
                            <i class='bx bx-history'></i>
                            Booking History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="accounts.php">
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
                <h1 class="mb-4">Accounts</h1>
                <div class="form-section card p-4 mb-4">
                    <h4 class="mb-3">Create New Admin Account</h4>
                    <?php if ($success): ?>
                        <div class="alert alert-success"> <?php echo $success; ?> </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"> <?php echo $error; ?> </div>
                    <?php endif; ?>
                    <form method="post" action="">
                        <input type="hidden" name="create_admin" value="1">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="col-md-4">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-2">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required 
                                       pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                                       title="Password must be at least 8 characters long and include uppercase, lowercase, number and special character">
                            </div>
                            <div class="col-md-2">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Admin</button>
                    </form>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                   <span style="font-size: 20px; font-weight: bold;">Accounts </span> 
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($admins_result && $admins_result->num_rows > 0): ?>
                                        <?php while ($admin = $admins_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($admin['id']); ?></td>
                                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                                <td>
                                                    <form method="post" action="" style="display:inline;">
                                                        <input type="hidden" name="delete_admin_id" value="<?php echo $admin['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this admin account?');">
                                                            <i class='bx bx-trash'></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No admin accounts found.</td>
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
</body>
</html> 