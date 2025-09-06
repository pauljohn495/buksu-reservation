<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../includes/db.php';
session_start();

// Check if email and verification are set in session
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
    header('Location: admin_forgot_password.php');
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf_token'];

$error = '';
$success = '';

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $error = "Invalid request";
    } else {
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $email = $_SESSION['reset_email'];
        
        if (empty($password) || empty($confirm_password)) {
            $error = "All fields are required";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long";
        } elseif (!preg_match("/[A-Z]/", $password)) {
            $error = "Password must contain at least one uppercase letter";
        } elseif (!preg_match("/[a-z]/", $password)) {
            $error = "Password must contain at least one lowercase letter";
        } elseif (!preg_match("/[0-9]/", $password)) {
            $error = "Password must contain at least one number";
        } elseif (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
            $error = "Password must contain at least one special character";
        } else {
            // Hash password and update
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password = ?, reset_code = NULL, reset_code_expires = NULL WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            
            if ($stmt->execute()) {
                // Clear session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_verified']);
                $success = "Password has been reset successfully. You can now login with your new password.";
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BUKSU Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .reset-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        .reset-logo {
            text-align: center;
            margin-bottom: 20px;
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
    <div class="reset-container">
        <div class="reset-logo">
            <h2>BUKSU Library</h2>
            <h4>Reset Password</h4>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <div class="mt-3">
                    <a href="admin_login.php" class="btn btn-primary">Go to Login</a>
                </div>
            </div>
        <?php else: ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required 
                           minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%^&*()\-_=+{};:,<.>]).{8,}"
                           title="Password must be at least 8 characters long and include uppercase, lowercase, number and special character">
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 