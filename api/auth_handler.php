<?php
// api/auth_handler.php
session_start();
require_once '../includes/db.php'; // Path from api folder

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Invalid request.'];
$action = null;

// Initial DB connection check (db.php should handle fatal errors, but this is an API context check)
if (!isset($conn) || $conn === false) {
    $response['message'] = "Database connection error in handler.";
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $userId = $_SESSION['user_id'] ?? null;

    // Protect actions requiring authentication
    if (!$userId && !in_array($action, ['register', 'login'])) {
        $response['message'] = 'Authentication required for this action.';
        echo json_encode($response);
        mysqli_close($conn);
        exit();
    }

    if ($action == "register") {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $preferred_currency = trim($_POST['preferred_currency'] ?? 'USD');
        $allowed_currencies = ['USD', 'EUR', 'NPR'];

        // Validations
        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) { $response['message'] = 'Username must be 3-50 characters.'; }
        elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) { $response['message'] = 'Username can only contain letters, numbers, and underscores.'; }
        elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $response['message'] = 'Invalid email format.'; }
        elseif (empty($password) || strlen($password) < 6) { $response['message'] = 'Password must be at least 6 characters long.'; }
        elseif ($password !== $confirm_password) { $response['message'] = 'Passwords do not match.'; }
        elseif (!in_array($preferred_currency, $allowed_currencies)) { $response['message'] = 'Invalid preferred currency selected.'; }
        else {
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("ss", $username, $email);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    // Could refine to check which one specifically if needed
                    $response['message'] = 'Username or Email already taken.';
                } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $sql_insert = "INSERT INTO users (username, email, password_hash, preferred_currency) VALUES (?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("ssss", $username, $email, $password_hash, $preferred_currency);
                        if ($stmt_insert->execute()) {
                            $response['status'] = 'success';
                            $response['message'] = 'Registration successful! You can now <a href="login.php">login</a>.';
                        } else {
                            $response['message'] = 'Registration failed due to a database error.';
                            error_log("Registration execute error: ".$stmt_insert->error);
                        }
                        $stmt_insert->close();
                    } else {
                        $response['message'] = 'Registration failed (prepare error).';
                        error_log("Registration prepare error (insert): ".$conn->error);
                    }
                }
                $stmt_check->close();
            } else {
                $response['message'] = 'Registration failed (prepare error for check).';
                error_log("Registration prepare error (check): ".$conn->error);
            }
        }

    } elseif ($action == "login") {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($identifier) || empty($password)) {
            $response['message'] = 'Username/Email and Password are required.';
        } else {
            $sql = "SELECT id, username, email, password_hash, preferred_currency FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $identifier, $identifier);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['preferred_currency'] = $user['preferred_currency'];
                        $response = ['status' => 'success', 'message' => 'Login successful! Redirecting...', 'redirect' => 'index.php'];
                    } else { $response['message'] = 'Invalid username/email or password.'; }
                } else { $response['message'] = 'Invalid username/email or password.'; }
                $stmt->close();
            } else {
                $response['message'] = 'Login failed (prepare error).';
                error_log("Login prepare error: ".$conn->error);
            }
        }

    } elseif ($action == "update_profile_details") {
        $new_username = trim($_POST['username'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $new_currency = trim($_POST['preferred_currency'] ?? '');
        $allowed_currencies = ['USD', 'EUR', 'NPR'];

        $_SESSION['form_data'] = $_POST; // Preserve for form repopulation on error (if not using AJAX fully for display)

        if (empty($new_username) || strlen($new_username) < 3 || strlen($new_username) > 50) { $response['message'] = 'Username must be 3-50 characters.'; }
        elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $new_username)) { $response['message'] = 'Username can only contain letters, numbers, and underscores.'; }
        elseif (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) { $response['message'] = 'Invalid email format.'; }
        elseif (!in_array($new_currency, $allowed_currencies)) { $response['message'] = 'Invalid preferred currency selected.'; }
        else {
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            if ($stmt_check) {
                $stmt_check->bind_param("ssi", $new_username, $new_email, $userId);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $response['message'] = 'The new username or email is already in use by another account.';
                } else {
                    $stmt_update = $conn->prepare("UPDATE users SET username = ?, email = ?, preferred_currency = ? WHERE id = ?");
                    if ($stmt_update) {
                        $stmt_update->bind_param("sssi", $new_username, $new_email, $new_currency, $userId);
                        if ($stmt_update->execute()) {
                            if ($stmt_update->affected_rows > 0) {
                                $_SESSION['username'] = $new_username;
                                $_SESSION['email'] = $new_email;
                                $_SESSION['preferred_currency'] = $new_currency;
                                $response = ['status' => 'success', 'message' => 'Profile details updated successfully!'];
                                unset($_SESSION['form_data']);
                            } else {
                                // Check if data was actually different or if user ID was somehow wrong
                                $response = ['status' => 'info', 'message' => 'No changes were made to your details.'];
                            }
                        } else {
                            $response['message'] = 'Error updating profile details. Please try again.';
                            error_log("Update profile details execute error (User: $userId): " . $stmt_update->error);
                        }
                        $stmt_update->close();
                    } else {
                        $response['message'] = 'Error updating profile (prepare error).';
                        error_log("Update profile details prepare error (update): " . $conn->error);
                    }
                }
                $stmt_check->close();
            } else {
                $response['message'] = 'Error updating profile (prepare error for check).';
                error_log("Update profile details prepare error (check): " . $conn->error);
            }
        }
        // For AJAX responses, these session messages are optional if JS handles display
        if ($response['status'] !== 'success') {
             $_SESSION['form_message'] = $response['message'];
             $_SESSION['form_message_type'] = $response['status'] === 'info' ? 'info' : 'error';
        } else {
             $_SESSION['message'] = $response['message']; // General success message for next page load (if any)
             $_SESSION['message_type'] = "success";
        }
        // If NOT using AJAX for profile.php forms, uncomment below:
        // header('Location: ../profile.php');
        // exit();

    } elseif ($action == "change_password") {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) { $response['message'] = 'All password fields are required.'; }
        elseif (strlen($new_password) < 6) { $response['message'] = 'New password must be at least 6 characters long.'; }
        elseif ($new_password !== $confirm_new_password) { $response['message'] = 'New passwords do not match.'; }
        else {
            $stmt_user = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            if ($stmt_user) {
                $stmt_user->bind_param("i", $userId);
                $stmt_user->execute();
                $result_user = $stmt_user->get_result();
                if ($user_data = $result_user->fetch_assoc()) {
                    if (password_verify($current_password, $user_data['password_hash'])) {
                        $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt_update_pass = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        if ($stmt_update_pass) {
                            $stmt_update_pass->bind_param("si", $new_password_hash, $userId);
                            if ($stmt_update_pass->execute()) {
                                $response = ['status' => 'success', 'message' => 'Password changed successfully!'];
                                // Optionally: force re-login by destroying session and redirecting to login
                                // session_destroy(); $response['redirect'] = 'login.php';
                            } else {
                                $response['message'] = 'Error changing password. Please try again.';
                                error_log("Change password execute error (User: $userId): " . $stmt_update_pass->error);
                            }
                            $stmt_update_pass->close();
                        } else {
                             $response['message'] = 'Error changing password (prepare error).';
                             error_log("Change password prepare error (update): " . $conn->error);
                        }
                    } else { $response['message'] = 'Incorrect current password.'; }
                } else { $response['message'] = 'User not found (critical error).'; } // Should not happen if session is valid
                $stmt_user->close();
            } else {
                $response['message'] = 'Error changing password (prepare error for user fetch).';
                error_log("Change password prepare error (user fetch): " . $conn->error);
            }
        }
        // For AJAX responses, these session messages are optional if JS handles display
        if ($response['status'] !== 'success') {
             $_SESSION['form_message'] = $response['message'];
             $_SESSION['form_message_type'] = 'error';
        } else {
             $_SESSION['message'] = $response['message']; // General success message for next page load (if any)
             $_SESSION['message_type'] = "success";
        }
        // If NOT using AJAX for profile.php forms, uncomment below:
        // header('Location: ../profile.php');
        // exit();
    } else {
        $response['message'] = 'Invalid action specified.';
    }

    if (isset($conn) && $conn) { // Ensure connection is closed if opened
        mysqli_close($conn);
    }
} else { // Request is not POST or action is not set
    if ($_SERVER["REQUEST_METHOD"] != "POST") { $response['message'] = 'Invalid request method.'; }
    else if (!isset($_POST['action'])) { $response['message'] = 'Action not specified.'; }
    // If db.php opened $conn but this script path wasn't a valid POST with action
    if (isset($conn) && $conn) { mysqli_close($conn); }
}

echo json_encode($response);
exit();
?>