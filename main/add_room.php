<?php
require '../includes/db.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
    header('Location: admin_dashboard.php?error=Invalid request');
    exit();
}

// Validate required fields
if (empty($_POST['room_name']) || empty($_POST['category']) || empty($_POST['status'])) {
    header('Location: admin_dashboard.php?error=All required fields must be filled');
    exit();
}

// Sanitize and validate input
$room_name = trim(filter_var($_POST['room_name'], FILTER_SANITIZE_STRING));
$category = trim(filter_var($_POST['category'], FILTER_SANITIZE_STRING));
$status = trim(filter_var($_POST['status'], FILTER_SANITIZE_STRING));



// Check if room name already exists
$stmt = $conn->prepare("SELECT id FROM rooms WHERE room_name = ?");
$stmt->bind_param("s", $room_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header('Location: admin_dashboard.php?error=Room name already exists');
    exit();
}
$stmt->close();

// Prepare and execute the insert statement
$stmt = $conn->prepare("
    INSERT INTO rooms (
        room_name, 
        category, 
        status, 
        time_limit,
        queue_count,
        created_at
    ) VALUES (?, ?, ?, 60, 0, NOW())
");

$stmt->bind_param("sss", 
    $room_name,
    $category,
    $status
);

if ($stmt->execute()) {
    header('Location: rooms.php?success=Room added successfully');
} else {
    header('Location: rooms.php?error=Failed to add room: ' . $conn->error);
}

$stmt->close();
$conn->close();
?> 