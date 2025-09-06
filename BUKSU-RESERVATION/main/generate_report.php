<?php
require '../includes/db.php';
require '../vendor/autoload.php'; 
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized access');
}

// Get parameters from URL
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$room_name = isset($_GET['room_name']) ? htmlspecialchars($_GET['room_name']) : '';
$user_email = isset($_GET['user_email']) ? htmlspecialchars($_GET['user_email']) : '';
$current_time = isset($_GET['current_time']) ? htmlspecialchars($_GET['current_time']) : '';

// Get user details from database
$stmt = $conn->prepare("SELECT u.name, u.email FROM rooms r JOIN users u ON r.booked_by = u.id WHERE r.id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Create PDF
$mpdf = new \Mpdf\Mpdf([
    'margin_left' => 20,
    'margin_right' => 20,
    'margin_top' => 20,
    'margin_bottom' => 20,
]);

// Set document properties
$mpdf->SetTitle('Room Booking Report');
$mpdf->SetAuthor('BUKSU Library System');

// Add content to PDF
$html = '
<style>
    body { font-family: Arial, sans-serif; }
    .header { text-align: center; margin-bottom: 30px; }
    .header h1 { color: #001c38; }
    .content { margin: 20px 0; }
    .info-row { margin: 10px 0; }
    .label { font-weight: bold; color: #001c38; }
    .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #666; }
</style>

<div class="header">
    <img src="img/logo2.png" alt="BUKSU Logo">
    <h1>Room Booking Report</h1>
    <p>BUKSU Library System</p>
</div>

<div class="content">
    <div class="info-row">
        <span class="label">Room Name:</span> ' . $room_name . '
    </div>
    <div class="info-row">
        <span class="label">User Name:</span> ' . ($user['name'] ?? 'N/A') . '
    </div>
    <div class="info-row">
        <span class="label">Email Address:</span> ' . ($user['email'] ?? $user_email) . '
    </div>
    <div class="info-row">
        <span class="label">Date and Time:</span> ' . $current_time . '
    </div>
</div>

';

$mpdf->WriteHTML($html);

// Output PDF
$mpdf->Output('Room_Booking_Report.pdf', 'I');
?> 