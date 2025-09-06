<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Role - BUKSU Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .role-container {
            background-color: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        .role-card {
            border: none;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            padding: 2rem;
            text-align: center;
            background-color: #f8f9fa;
            margin-bottom: 1rem;
        }
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .role-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
        .role-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .role-description {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo img {
            max-width: 200px;
            height: auto;
            margin-bottom: 1rem;
        }
        .logo h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .logo p {
            color: #6c757d;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="role-container">
        <div class="logo">
            <img src="img/logo2.png" alt="BUKSU Library Logo" class="img-fluid">
            <h2>BUKSU Library System</h2>
        </div>

        <div class="row">
            <div class="col-md-6">
                <a href="main/admin_login.php" class="text-decoration-none">
                    <div class="role-card">
                        <i class='bx bxs-user-detail role-icon'></i>
                        <h3 class="role-title">Admin</h3>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="login.php" class="text-decoration-none">
                    <div class="role-card">
                        <i class='bx bxs-user role-icon'></i>
                        <h3 class="role-title">Student</h3>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 