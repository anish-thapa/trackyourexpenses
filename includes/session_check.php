<?php
// includes/session_check.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in (i.e., user_id session variable is set)
if (!isset($_SESSION['user_id'])) {
    // Optional: Store the requested page to redirect after login
    // $_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

    // Redirect to login page
    header('Location: login.php'); // Assuming login.php is in the root
    exit();
}
// You could add further checks here, e.g., user activity timeout, role checks, etc.
?>