<?php
require '../includes/db.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Empty the booking history
$query = "TRUNCATE TABLE booking_history";
if ($conn->query($query)) {
    header('Location: booking_history.php?success=Booking history emptied successfully');
} else {
    header('Location: booking_history.php?error=Failed to empty booking history');
}
exit();
?> 