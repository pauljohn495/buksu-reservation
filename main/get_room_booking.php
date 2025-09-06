<?php
require '../includes/db.php';

// Get room ID from request and validate
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
if (!$room_id) {
    echo json_encode(['success' => false, 'error' => 'No room ID provided']);
    exit;
}

// Query to get room details including the current user's email
$result = $conn->query("SELECT r.room_name, r.booked_at, r.time_limit, r.queue_count, u.email as booked_user_email 
                       FROM rooms r 
                       LEFT JOIN users u ON r.booked_by = u.id 
                       WHERE r.id = $room_id");

if ($row = $result->fetch_assoc()) {
    // Return success response with room booking details
    echo json_encode([
        'success' => true,
        'room_name' => $row['room_name'],
        'booked_user' => $row['booked_user_email'] ? $row['booked_user_email'] : 'Unknown User',
        'booked_at' => $row['booked_at'],
        'time_limit' => (int)$row['time_limit'],
        'queue_count' => (int)$row['queue_count'],
    ]);
} else {
    // Return error response if room not found
    echo json_encode(['success' => false, 'error' => 'Room not found']);
} 