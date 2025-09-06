<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../includes/db.php';
require_once 'phpmailer_helper.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit();
}

if (isset($_POST['room_id'])) {
    $room_id = intval($_POST['room_id']);

    // Check if there's a queue
    $queue_sql = "SELECT user_id FROM room_queue WHERE room_id = ? ORDER BY created_at ASC LIMIT 1";
    $queue_stmt = $conn->prepare($queue_sql);
    $queue_stmt->bind_param("i", $room_id);
    $queue_stmt->execute();
    $queue_result = $queue_stmt->get_result();

    if ($queue_result->num_rows > 0) {
        $next_user = $queue_result->fetch_assoc();
        
        // Get the previous user (the one whose booking just ended)
        $prev_user_result = $conn->query("SELECT u.email, r.room_name FROM rooms r LEFT JOIN users u ON r.booked_by = u.id WHERE r.id = $room_id");
        $prev_user = $prev_user_result->fetch_assoc();

        // Update room status, booked_at, and booked_by
        $update_sql = "UPDATE rooms SET status = 'Occupied', booked_at = NOW(), booked_by = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $next_user['user_id'], $room_id);
        
        if ($update_stmt->execute()) {
            // Notify previous user
            if ($prev_user && !empty($prev_user['email'])) {
                $subject = "Your BUKSU Library Room Booking has Ended";
                $message = "Hello,\n\nYour booking for room '{$prev_user['room_name']}' has ended. Thank you for using the BUKSU Library Room Reservation System!";
                send_email($prev_user['email'], $subject, $message);
            }

            // Remove the user from queue
            $remove_queue_sql = "DELETE FROM room_queue WHERE room_id = ? AND user_id = ? LIMIT 1";
            $remove_queue_stmt = $conn->prepare($remove_queue_sql);
            $remove_queue_stmt->bind_param("ii", $room_id, $next_user['user_id']);
            $remove_queue_stmt->execute();

            // Notify next user
            $next_user_id = $next_user['user_id'];
            $next_user_result = $conn->query("SELECT email FROM users WHERE id = $next_user_id");
            $next_user_data = $next_user_result->fetch_assoc();
            if ($next_user_data && !empty($next_user_data['email'])) {
                $subject = "Your BUKSU Library Room Booking is Now Active";
                $message = "Dear user,\n\nYour booking for room '{$prev_user['room_name']}' is now active. You may now proceed to the room.\n\nThank you!";
                send_email($next_user_data['email'], $subject, $message);
            }

            // Update queue count
            $conn->query("UPDATE rooms SET queue_count = queue_count - 1 WHERE id = $room_id");

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update room status']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No users in queue']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No room ID provided']);
}
?> 