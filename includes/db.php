<?php
// includes/db.php

define('DB_SERVER', 'localhost');     // Your database host (e.g., 'localhost' or '127.0.0.1')
define('DB_USERNAME', 'root');        // Your database username (CHANGE THIS)
define('DB_PASSWORD', '');            // Your database password (CHANGE THIS)
define('DB_NAME', 'finance_manager_db'); // Your database name

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn === false) {
    error_log("ERROR: Could not connect to database. " . mysqli_connect_error());
    // If this is called from an API endpoint, it should return JSON
    // Check if the script is an API handler by its filename or a defined constant
    $is_api_call = (basename($_SERVER['SCRIPT_FILENAME']) === 'auth_handler.php'); // Adjust if more API files

    if ($is_api_call) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Please contact support.']);
        exit;
    } else {
        die("FATAL ERROR: Could not connect to the database. Please check your configuration and ensure the database server is running. " . mysqli_connect_error());
    }
}

// Set character set to utf8mb4 for full Unicode support
mysqli_set_charset($conn, "utf8mb4");
?>