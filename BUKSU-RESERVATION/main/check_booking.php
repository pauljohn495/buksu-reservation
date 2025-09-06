<?php

require '../includes/db.php';
session_start();

// Get the room ID from the GET request, default to 0 if not set
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
// Get the current user's ID from the session
$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT status, booked_by FROM rooms WHERE id = $room_id");
$row = $result ? $result->fetch_assoc() : null;

echo json_encode([
    'occupied' => ($row && $row['status'] === 'Occupied'),
    'is_mine' => ($row && $row['booked_by'] == $user_id)
]); 