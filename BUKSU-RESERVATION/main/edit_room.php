<?php
require '../includes/db.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Get room ID from URL
$room_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($room_id <= 0) {
    header('Location: rooms.php?error=Invalid room ID');
    exit();
}

// Fetch room details
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();

if (!$room) {
    header('Location: rooms.php?error=Room not found');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        header('Location: rooms.php?error=Invalid request');
        exit();
    }

    // Sanitize and validate input
    $room_name = trim(filter_var($_POST['room_name'], FILTER_SANITIZE_STRING));
    $category = trim(filter_var($_POST['category'], FILTER_SANITIZE_STRING));
    $status = trim(filter_var($_POST['status'], FILTER_SANITIZE_STRING));


    // Check if room name already exists (excluding current room)
    $stmt = $conn->prepare("SELECT id FROM rooms WHERE room_name = ? AND id != ?");
    $stmt->bind_param("si", $room_name, $room_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header('Location: edit_room.php?id=' . $room_id . '&error=Room name already exists');
        exit();
    }

    // Update room details
    $stmt = $conn->prepare("
        UPDATE rooms SET 
            room_name = ?,
            category = ?,
            status = ?,
            time_limit = 60
        WHERE id = ?
    ");

    $stmt->bind_param("sssi", 
        $room_name,
        $category,
        $status,
        $room_id
    );

    if ($stmt->execute()) {
        header('Location: rooms.php?success=Room updated successfully');
    } else {
        header('Location: edit_room.php?id=' . $room_id . '&error=Failed to update room');
    }
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['admin_csrf_token'];

// Handle error messages
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Room - BUKSU Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .edit-form {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="edit-form">
            <h2 class="mb-4">Edit Room</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post" action="edit_room.php?id=<?php echo $room_id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="mb-3">
                    <label for="room_name" class="form-label">Room Name*</label>
                    <input type="text" class="form-control" id="room_name" name="room_name" required title="Enter the name of the room here"
                           value="<?php echo html_entity_decode($room['room_name']); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="category" class="form-label">Category*</label>
                    <select class="form-select" id="category" name="category" required>
                        <option value="">Select a category</option>
                        <option value="Discussion" <?php echo $room['category'] == 'Discussion' ? 'selected' : ''; ?>>Discussion</option>
                        <option value="Makers Space" <?php echo $room['category'] == 'Makers Space' ? 'selected' : ''; ?>>Makers Space</option>
                        <option value="Conference" <?php echo $room['category'] == 'Conference' ? 'selected' : ''; ?>>Conference</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label">Status*</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="Available" <?php echo $room['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Occupied" <?php echo $room['status'] == 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                        <option value="Maintenance" <?php echo $room['status'] == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="rooms.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Room</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 