<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../includes/db.php';
require 'phpmailer_helper.php';
session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf_token'];

$error = '';
$success = '';

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $error = "Invalid request";
    } else {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $error = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                // Generate verification code
                $code = sprintf("%06d", mt_rand(0, 999999));
                
                // Store code in database
                $stmt = $conn->prepare("UPDATE admins SET reset_code = ? WHERE email = ?");
                $stmt->bind_param("ss", $code, $email);
                
                if ($stmt->execute()) {
                    // Send verification code email
                    $subject = "Password Reset Verification Code - BUKSU Library System";
                    $message = "Your verification code for password reset is: {$code} ";
                    $message .= "If you did not request this password reset, please ignore this email.";
                    
                    if (send_email($email, $subject, $message)) {
                        // Store email in session for verification
                        $_SESSION['reset_email'] = $email;
                        header('Location: admin_verify_code.php');
                        exit();
                    } else {
                        $error = "Failed to send verification code. Please try again.";
                    }
                } else {
                    $error = "Failed to process request. Please try again.";
                }
            } else {
                // For security reasons, show the same message whether email exists or not
                $success = "If your email is registered, you will receive a verification code shortly.";
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
    <title>Forgot Password - BUKSU Library System</title>
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
        .forgot-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        .forgot-logo {
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
    <div class="forgot-container">
        <div class="forgot-logo">
            <h2>BUKSU Library</h2>
            <h4>Forgot Password</h4>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required>
                <div class="form-text">Enter your registered email address to receive a verification code.</div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Send Verification Code</button>
            </div>
            
            <div class="mt-3 text-center">
                <a href="admin_login.php" class="text-decoration-none">Back to Login</a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 