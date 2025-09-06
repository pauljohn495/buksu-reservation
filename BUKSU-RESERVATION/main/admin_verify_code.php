<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../includes/db.php';
session_start();

// Check if email is set in session
if (!isset($_SESSION['reset_email'])) {
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

// Process verification code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $error = "Invalid request";
    } else {
        $code = trim($_POST['code']);
        $email = $_SESSION['reset_email'];
        
        if (empty($code)) {
            $error = "Verification code is required";
        } else {
            // Verify code
            $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND reset_code = ?");
            $stmt->bind_param("ss", $email, $code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                // Code is valid, redirect to reset password page
                $_SESSION['reset_verified'] = true;
                header('Location: admin_reset_password.php');
                exit();
            } else {
                $error = "Invalid or expired verification code";
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
    <title>Verify Code - BUKSU Library System</title>
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
        .verify-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        .verify-logo {
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
        .code-input {
            letter-spacing: 0.5em;
            text-align: center;
            font-size: 1.5em;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-logo">
            <h2>BUKSU Library</h2>
            <h4>Verify Code</h4>
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
                <label for="code" class="form-label">Verification Code</label>
                <input type="text" class="form-control code-input" id="code" name="code" required 
                       maxlength="6" pattern="[0-9]{6}" title="Please enter the 6-digit code">
                <div class="form-text">Enter the 6-digit code sent to your email.</div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Verify Code</button>
            </div>
            
            <div class="mt-3 text-center">
                <a href="admin_forgot_password.php" class="text-decoration-none">Back to Forgot Password</a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format the code input
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html> 