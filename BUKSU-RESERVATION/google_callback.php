<?php
// Include required files
require_once 'vendor/autoload.php';
require 'includes/db.php';
session_start();

// Create the Google client
$client = new Google_Client();
$client->setClientId('291360629779-blq0a1vat8hvdl29ltqjrp1mjnrtbbfo.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/BUKSU-RESERVATION/google_callback.php');
$client->addScope("email");
$client->addScope("profile");

// Use try-catch for better error handling
try {
    if (isset($_GET['code'])) {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (isset($token['error'])) {
            echo "Authentication Error: " . $token['error'];
            echo "<br>Description: " . (isset($token['error_description']) ? $token['error_description'] : 'No description provided');
            exit;
        }
        
        $client->setAccessToken($token);
        
        // Get user profile
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        // Extract user data
        $email = $google_account_info->email;
        $name = $google_account_info->name;
        $google_id = $google_account_info->id;
        $picture = $google_account_info->picture;
        
        // Check if user exists in database, if not create one
        $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->bind_param("s", $google_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // User exists - update their info and log them in
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            
            // Update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
        } else {
            // New user - insert into database
            $insert_stmt = $conn->prepare("INSERT INTO users (name, email, google_id, profile_picture, created_at) VALUES (?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("ssss", $name, $email, $google_id, $picture);
            $insert_stmt->execute();
            $user_id = $conn->insert_id;
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['picture'] = $picture;
        $_SESSION['logged_in'] = true;
        
        // Redirect to main page
        header('Location: main/index.php');
        exit();
    } else {
        // If no code is present, redirect to login page
        header('Location: login.php');
        exit();
    }
} catch (Exception $e) {
    echo "<h2>Error Details:</h2>";
    echo "<p>Message: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    exit;
}
?>