<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../includes/db.php';
session_start();

// Check if admin is already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf_token'];

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $error = "Invalid request";
    } else {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        // Get admin from database
        $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            
            // Verify password (assuming password is hashed with password_hash)
            if (password_verify($password, $admin['password'])) {
                // Set admin session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                
                // Redirect to admin dashboard
                header('Location: admin_dashboard.php');
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - BUKSU Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-logo img {
            max-width: 150px;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
            padding: 10px 0;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <h2>BUKSU Library</h2>
            <h4>Admin Login</h4>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="username" class="form-label">Email or Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
            
            <div class="mt-3 text-center">
                <a href="admin_forgot_password.php" class="text-decoration-none">Forgot Password?</a>
                <br>
                <a href="../select_role.php" class="text-decoration-none">Back to Main Site</a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
</body>
</html>