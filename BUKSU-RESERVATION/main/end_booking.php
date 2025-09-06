<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../includes/db.php';
require_once 'phpmailer_helper.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit();
}

if (isset($_POST['room_id'])) {
    $room_id = intval($_POST['room_id']);
    $user_id = $_SESSION['user_id'];

    // Check if the room is actually booked by this user
    $check_sql = "SELECT status, booked_at, booked_by, room_name FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();

    if ($room && $room['status'] === 'Occupied' && $room['booked_by'] == $user_id) {
        // Get user email before updating room status
        $user_result = $conn->query("SELECT email FROM users WHERE id = $user_id");
        $user = $user_result->fetch_assoc();
        $room_name = $room['room_name'];

        // Update room status to Available
        $update_sql = "UPDATE rooms SET status = 'Available', booked_at = NULL, booked_by = NULL WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $room_id);

        if ($update_stmt->execute()) {
            // Notify the current user
            $subject = "Your BUKSU Library Room Booking has Ended";
            $message = "Dear User,\n\nYour booking for room '{$room['room_name']}' has ended. Thank you for using the BUKSU Library Room Reservation System!";
            send_email($user['email'], $subject, $message);

            // Check if there's a queue
            $queue_sql = "SELECT user_id FROM room_queue WHERE room_id = ? ORDER BY created_at ASC LIMIT 1";
            $queue_stmt = $conn->prepare($queue_sql);
            $queue_stmt->bind_param("i", $room_id);
            $queue_stmt->execute();
            $queue_result = $queue_stmt->get_result();

            if ($queue_result->num_rows > 0) {
                $next_user = $queue_result->fetch_assoc();
                // Book the room for the next user in queue
                $book_sql = "UPDATE rooms SET status = 'Occupied', booked_at = NOW(), booked_by = ? WHERE id = ?";
                $book_stmt = $conn->prepare($book_sql);
                $book_stmt->bind_param("ii", $next_user['user_id'], $room_id);
                $book_stmt->execute();

                // Remove the user from queue
                $remove_queue_sql = "DELETE FROM room_queue WHERE room_id = ? AND user_id = ? LIMIT 1";
                $remove_queue_stmt = $conn->prepare($remove_queue_sql);
                $remove_queue_stmt->bind_param("ii", $room_id, $next_user['user_id']);
                $remove_queue_stmt->execute();

                // Update queue count
                $conn->query("UPDATE rooms SET queue_count = queue_count - 1 WHERE id = $room_id");

                // Notify the next user
                $next_user_result = $conn->query("SELECT email FROM users WHERE id = {$next_user['user_id']}");
                $next_user_data = $next_user_result->fetch_assoc();
                if ($next_user_data && !empty($next_user_data['email'])) {
                    $subject = "Your BUKSU Library Room Booking is Now Active";
                    $message = "Dear User,\n\nYour booking for room '{$room['room_name']}' is now active. You may now proceed to the room.\n\nThank you!";
                    send_email($next_user_data['email'], $subject, $message);
                }
            }

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update room status']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Room is not occupied or you are not the booker']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No room ID provided']);
}
?> 