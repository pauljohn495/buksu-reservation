<?php
require '../includes/db.php';
session_start();

// Get the current user's ID from the session
$user_id = $_SESSION['user_id'];

// Query to find any active booking for the current user
$result = $conn->query("SELECT id, booked_at, time_limit FROM rooms WHERE status = 'Occupied' AND booked_by = $user_id LIMIT 1");

if ($row = $result->fetch_assoc()) {
    // Calculate the end time by adding the time limit to the booking time
    $end_time = date('Y-m-d H:i:s', strtotime($row['booked_at']) + $row['time_limit'] * 60);
    
    echo json_encode([
        'success' => true,
        'roomId' => (int)$row['id'],
        'endTime' => $end_time
    ]);
} else {
    echo json_encode(['success' => false]);
} 