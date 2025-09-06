<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to role selection page
header('Location: ../select_role.php');
exit();
?> 