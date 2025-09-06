<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../includes/db.php';
require_once 'phpmailer_helper.php';

session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check if logged in at the beginning of index.php
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Handle room booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $room_id = intval($_POST['room_id']);
    $user_id = $_SESSION['user_id'];

    // Check room status
    $check_sql = "SELECT status, room_name FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();

    if (!$room) {
        header("Location: index.php?error=Room not found");
        exit();
    }

    // Book Room if Available
    if ($room['status'] === 'Available') {
        $stmt = $conn->prepare("UPDATE rooms SET status = 'Occupied', booked_at = NOW(), booked_by = ? WHERE id = ?");
        $stmt->bind_param('ii', $user_id, $room_id);
        if ($stmt->execute()) {
            // Get user email
            $user_email_query = "SELECT email FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($user_email_query);
            $user_stmt->bind_param('i', $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            
            // Insert into booking history
            $history_stmt = $conn->prepare("INSERT INTO booking_history (room_name, user_email, booked_at) SELECT room_name, ?, booked_at FROM rooms WHERE id = ?");
            $history_stmt->bind_param('si', $user_data['email'], $room_id);
            $history_stmt->execute();
            
            // Send booking active email
            $subject = "Your BUKSU Library Room Booking is Active";
            $message = "Dear User,\n\nYour booking for room '" . $room['room_name'] . "' is now active. You may now proceed to the room.\n\nThank you!";
            send_email($user_data['email'], $subject, $message);
            
            header("Location: index.php?success=1&room_id=$room_id");
            exit();
        }
    }
    // If Occupied, Join Queue
    elseif ($room['status'] === 'Occupied') {
        // Check if user is already in queue for this room
        $check_queue_sql = "SELECT id FROM room_queue WHERE room_id = ? AND user_id = ?";
        $check_queue_stmt = $conn->prepare($check_queue_sql);
        $check_queue_stmt->bind_param("ii", $room_id, $user_id);
        $check_queue_stmt->execute();
        $check_queue_result = $check_queue_stmt->get_result();

        if ($check_queue_result->num_rows > 0) {
            header("Location: index.php");
            exit();
        }

        // Add user to queue table
        $insert_queue_sql = "INSERT INTO room_queue (room_id, user_id, created_at) VALUES (?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_queue_sql);
        $insert_stmt->bind_param("ii", $room_id, $user_id);
        
        if ($insert_stmt->execute()) {
            // Increment queue_count in rooms table
            $conn->query("UPDATE rooms SET queue_count = queue_count + 1 WHERE id = $room_id");
            header("Location: index.php?queued=1");
            exit();
        } else {
            header("Location: index.php?error=Failed to join queue");
            exit();
        }
    }
    else {
        header("Location: index.php?error=Room is in an unknown state");
        exit();
    }
}

// Fix for notifications showing on every reload
$success = isset($_GET['success']) && $_SERVER['HTTP_REFERER'] !== null;
$queued = isset($_GET['queued']) && $_SERVER['HTTP_REFERER'] !== null;
$error = isset($_GET['error']) && $_SERVER['HTTP_REFERER'] !== null;

// Fetch rooms by category
$discussion_sql = "SELECT * FROM rooms WHERE category = 'Discussion'";
$makers_sql = "SELECT * FROM rooms WHERE category = 'Makers Space'";
$conference_sql = "SELECT * FROM rooms WHERE category = 'Conference'";

$discussion_result = $conn->query($discussion_sql);
$makers_result = $conn->query($makers_sql);
$conference_result = $conn->query($conference_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        function startCountdown(elementId, endTime, queueCount, roomId) {
            const timerElement = document.getElementById(elementId);
            let countdownFinished = false;

            function updateCountdown() {
                const now = new Date().getTime();
                const distance = endTime - now;

                if (distance < 0) {
                    if (countdownFinished) return;
                    countdownFinished = true;
                    timerElement.innerHTML = "Time's up!";
                    return;
                }

                const hours = Math.floor(distance / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                timerElement.innerHTML = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }

            updateCountdown();
            const countdownInterval = setInterval(updateCountdown, 1000);
        }

        // Function to show booking modal
        function showBookingModal(roomId, endTime) {
            const modal = document.getElementById('bookingModal');
            const timerElement = document.getElementById('bookingTimer');
            const endTimeDate = new Date(endTime);
            
            // Start the countdown
            startCountdown('bookingTimer', endTimeDate.getTime(), 0, roomId);
            
            // Show the modal
            modal.classList.add('active');
            
            // Store the booking state in localStorage
            localStorage.setItem('activeBooking', JSON.stringify({
                roomId: roomId,
                endTime: endTime
            }));
        }

        // Function to end booking
        function endBooking(roomId) {
            if (confirm('Are you sure you want to end your booking?')) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                if (!csrfToken) {
                    alert('Security token missing. Please refresh the page and try again.');
                    return;
                }

                fetch('end_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `room_id=${roomId}&csrf_token=${csrfToken}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Remove booking from localStorage
                        localStorage.removeItem('activeBooking');
                        // Hide modal
                        hideBookingModal();
                        // Reload page to update room status
                        location.reload();
                    } else {
                        alert('Failed to end booking: ' + (data.error || 'Unknown error occurred'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while ending the booking. Please try again.');
                });
            }
        }

        // Function to hide booking modal
        function hideBookingModal() {
            document.getElementById('bookingModal').classList.remove('active');
        }
    </script>
    <!--SwiperJS--> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <!--Google Fonts--> 
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Room Reservation System</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            transition: box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .section-heading {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 5px solid #0d6efd;
        }

        .category-container {
            margin-bottom: 40px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal.active {
            display: block;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            text-align: center;
            border-radius: 8px;
        }

        .modal-content h2 {
            margin-bottom: 20px;
        }

        .modal-content button {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .modal-content button:hover {
            background-color: #c82333;
        }

        .status-available {
            color: #28a745;
            font-weight: bold;
        }

        .status-occupied {
            color: #fd7e14;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!--boxicons-->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!--bootstrap-->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"></script>
    <!--Scroll Reveal effect-->
    <script src="https://unpkg.com/scrollreveal"></script>
    <!--SwiperJS--> 
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <header>
        <nav>
            <input type="checkbox" id="sidebar-active">
            <label for="sidebar-active" class="open-sidebar"> 
                <i class='bx bx-menu' style="color: white; font-size: 3.5rem;"></i>
            </label>  

            <div class="links-container">
                <label for="sidebar-active" class="close-sidebar"> 
                    <i class='bx bx-x' style="color: white; font-size: 3.5rem;"></i>
                </label> 

                <img class="home-link" src="img/logo.png" alt="logo"> 
                <a href="logout.php" class="logout-btn">
                     Logout
                </a>
            </div>
        </nav>
    </header>

        <!-- ROOMS -->
        <div id="rooms-section" class="rooms-container" style="background-color: white; display: flex; justify-content: center; align-items: center; padding: 20px;">
            <div class="container mt-5">
                <h1 class="mb-4 text-center" style="font-weight: 600;">BUKSU Library Rooms</h1>

                <!-- Discussion Rooms Section -->
                <div class="category-container">
                    <div class="section-heading">
                        <h2><i class='bx bx-conversation'></i> Discussion Rooms</h2>
                        <p>Perfect for group study sessions and collaborative projects</p>
                    </div>
                    
                    <?php if ($discussion_result && $discussion_result->num_rows > 0): ?>
                        <div class="row">
                            <?php while($row = $discussion_result->fetch_assoc()): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($row['room_name']); ?></h5>
                                            <p class="card-text">Status: 
                                                <?php if ($row['status'] == 'Available'): ?>
                                                    <span class="status-available">Available</span>
                                                <?php elseif ($row['status'] == 'Occupied'): ?>
                                                    <span class="status-occupied">Occupied</span>
                                                <?php else: ?>
                                                    <span><?php echo htmlspecialchars($row['status']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="card-text">Users in Queue: <?php echo htmlspecialchars($row['queue_count']); ?></p>

                                            <?php if ($row['status'] == 'Available'): ?>
                                                <form action="index.php" method="post">
                                                    <input type="hidden" name="room_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <button type="submit" class="btn btn-primary">Book room</button>
                                                </form>
                                            <?php else: ?>
                                                <form action="index.php" method="post">
                                                    <input type="hidden" name="room_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <button type="submit" class="btn btn-warning">Join Queue</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p>No discussion rooms available.</p>
                    <?php endif; ?>
                </div>

                <!-- Maker's Space Section -->
                <div class="category-container">
                    <div class="section-heading">
                        <h2><i class='bx bx-bulb'></i> Maker's Space</h2>
                        <p>The Maker's Space is a creative area in the library for hands-on projects, crafting, and innovation</p>
                    </div>
                    
                    <?php if ($makers_result && $makers_result->num_rows > 0): ?>
                        <div class="row">
                            <?php while($row = $makers_result->fetch_assoc()): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($row['room_name']); ?></h5>
                                            <p class="card-text">Status: 
                                                <?php if ($row['status'] == 'Available'): ?>
                                                    <span class="status-available">Available</span>
                                                <?php elseif ($row['status'] == 'Occupied'): ?>
                                                    <span class="status-occupied">Occupied</span>
                                                <?php else: ?>
                                                    <span><?php echo htmlspecialchars($row['status']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="card-text">Users in Queue: <?php echo htmlspecialchars($row['queue_count']); ?></p>

                                            <?php if ($row['status'] == 'Available'): ?>
                                                <form action="index.php" method="post">
                                                    <input type="hidden" name="room_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <button type="submit" class="btn btn-primary">Book room</button>
                                                </form>
                                            <?php else: ?>
                                                <form action="index.php" method="post">
                                                    <input type="hidden" name="room_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <button type="submit" class="btn btn-warning">Join Queue</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p>No maker's space rooms available.</p>
                    <?php endif; ?>
                </div>

                <!-- Conference Room Section -->
                <div class="category-container">
                    <div class="section-heading">
                        <h2><i class='bx bx-building'></i> Conference Room</h2>
                        <p>Larger rooms for formal presentations and meetings</p>
                    </div>
                    
                    <?php if ($conference_result && $conference_result->num_rows > 0): ?>
                        <div class="row">
                            <?php while($row = $conference_result->fetch_assoc()): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($row['room_name']); ?></h5>
                                            <p class="card-text">Status: 
                                                <?php if ($row['status'] == 'Available'): ?>
                                                    <span class="status-available">Available</span>
                                                <?php elseif ($row['status'] == 'Occupied'): ?>
                                                    <span class="status-occupied">Occupied</span>
                                                <?php else: ?>
                                                    <span><?php echo htmlspecialchars($row['status']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="card-text">Users in Queue: <?php echo htmlspecialchars($row['queue_count']); ?></p>

                                            <?php if ($row['status'] == 'Available'): ?>
                                                <form action="index.php" method="post">
                                                    <input type="hidden" name="room_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <button type="submit" class="btn btn-primary">Book room</button>
                                                </form>
                                            <?php else: ?>
                                                <form action="index.php" method="post">
                                                    <input type="hidden" name="room_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <button type="submit" class="btn btn-warning">Join Queue</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p>No conference rooms available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <script>
        var swiper = new Swiper(".swiper-container", {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: true,
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
        });

        const sr = ScrollReveal({
            distance: '65px',
            duration: 2600,
            delay: 450,
            reset: false
        });

    </script>

    <!-- Add the booking modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <h2>Your Booking is Active</h2>
            <p>Time Remaining:</p>
            <h1 id="bookingTimer">00:00:00</h1>
            <button onclick="endBooking(JSON.parse(localStorage.getItem('activeBooking')).roomId)">End Booking</button>
        </div>
    </div>

    <script>
    // Always check for active booking on page load

    document.addEventListener('DOMContentLoaded', function() {
        function checkAndShowBookingModal() {
            const booking = localStorage.getItem('activeBooking');
            if (booking) {
                const parsed = JSON.parse(booking);
                fetch(`check_booking.php?room_id=${parsed.roomId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.occupied && data.is_mine) {
                            showBookingModal(parsed.roomId, parsed.endTime);
                        } else {
                            localStorage.removeItem('activeBooking');
                            hideBookingModal();
                        }
                    })
                    .catch(() => {
                        showBookingModal(parsed.roomId, parsed.endTime);
                    });
            } else {
                // No active booking in localStorage, check if user is now the booker
                fetch('get_my_active_booking.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            localStorage.setItem('activeBooking', JSON.stringify({
                                roomId: data.roomId,
                                endTime: data.endTime
                            }));
                            showBookingModal(data.roomId, data.endTime);
                        } else {
                            hideBookingModal();
                        }
                    });
            }
        }
        checkAndShowBookingModal();
        setInterval(checkAndShowBookingModal, 5000); // Poll every 5 seconds
    });
    </script>

    <?php if (isset($_GET['success']) && isset($_GET['room_id'])): ?>
    <script>
        // Fetch the booked_at and time_limit for this room via PHP
        <?php
            $room_id = (int)$_GET['room_id'];
            $result = $conn->query("SELECT booked_at, time_limit FROM rooms WHERE id = $room_id");
            $row = $result->fetch_assoc();
            $booked_at = $row['booked_at'];
            $time_limit = $row['time_limit'];
            $end_time = date('Y-m-d H:i:s', strtotime($booked_at) + $time_limit * 60);
        ?>
        localStorage.setItem('activeBooking', JSON.stringify({
            roomId: <?php echo $room_id; ?>,
            endTime: "<?php echo $end_time; ?>"
        }));
    </script>
    <?php endif; ?>
</body>
</html>