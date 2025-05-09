<?php
$pageTitle = "My Profile"; // Set Page Title for header.php
require_once 'includes/session_check.php';
require_once 'includes/db.php'; // For fetching current user data
// helpers.php is included in header.php

$userId = $_SESSION['user_id'];
$currentUser = null;
$fetch_error = false;

if (!isset($conn) || $conn === false) {
    error_log("Database connection not established in profile.php for user ID: " . $userId);
    $_SESSION['message'] = "Database error. Cannot load profile data.";
    $_SESSION['message_type'] = "error";
    $fetch_error = true;
    // Potentially redirect if critical, but header.php will show the message
    // header("Location: index.php");
    // exit();
} else {
    $stmt = $conn->prepare("SELECT username, email, preferred_currency FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $currentUser = $result->fetch_assoc();
        } else {
            // This should ideally not happen if session is valid and user exists
            $_SESSION['message'] = "Error: User data not found. Please try logging in again.";
            $_SESSION['message_type'] = "error";
            $stmt->close();
            $conn->close();
            header("Location: logout.php"); // Force logout if user data inconsistent
            exit();
        }
        $stmt->close();
    } else {
        error_log("Profile Page - Prepare failed to get user data: " . $conn->error);
        $_SESSION['message'] = "Could not load your profile data at this time.";
        $_SESSION['message_type'] = "error";
        $fetch_error = true;
    }
    $conn->close(); // Close connection after fetching data
}

// Form data preservation from session if a previous update attempt failed (from auth_handler.php)
// If currentUser couldn't be fetched, these will default to empty or preset values.
$form_username = $_SESSION['form_data']['username'] ?? ($currentUser['username'] ?? '');
$form_email = $_SESSION['form_data']['email'] ?? ($currentUser['email'] ?? '');
$form_currency = $_SESSION['form_data']['preferred_currency'] ?? ($currentUser['preferred_currency'] ?? 'USD');
?>
<?php require_once 'includes/header.php'; // Centralized header ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $pageTitle; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>

<!-- General messages are handled in header.php (_SESSION['message']) -->
<!-- Form specific messages (_SESSION['form_message']) are also handled in header.php now -->

<?php if ($fetch_error && !$currentUser): ?>
    <?php // Message is already set if $fetch_error is true, header.php will display it.
          // No need to show forms if user data couldn't be loaded.
    ?>
<?php else: ?>
<div class="row g-4">
    <!-- Section 1: Update Profile Details -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Account Details</h5>
            </div>
            <div class="card-body">
                <form action="api/auth_handler.php" method="POST" id="profileDetailsForm">
                    <input type="hidden" name="action" value="update_profile_details">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($form_username); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($form_email); ?>" required>
                        <div class="form-text">Changing your email might require re-verification in a more complex system.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="preferred_currency" class="form-label">Preferred Currency <span class="text-danger">*</span></label>
                        <select class="form-select" id="preferred_currency" name="preferred_currency" required>
                            <option value="USD" <?php echo ($form_currency == 'USD') ? 'selected' : ''; ?>>USD ($) - US Dollar</option>
                            <option value="EUR" <?php echo ($form_currency == 'EUR') ? 'selected' : ''; ?>>EUR (€) - Euro</option>
                            <option value="NPR" <?php echo ($form_currency == 'NPR') ? 'selected' : ''; ?>>NPR (रु) - Nepalese Rupee</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Details</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Section 2: Change Password -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
            </div>
            <div class="card-body">
                <form action="api/auth_handler.php" method="POST" id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password (min 6 characters) <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning text-dark"><i class="fas fa-lock me-1"></i>Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php unset($_SESSION['form_data']); // Clear preserved form data after displaying ?>

<?php
// If you decide to use JavaScript for AJAX form submission for profile updates (recommended for UX)
// you would include a specific JS file here or add to an existing one.
// For now, the forms will submit traditionally and rely on auth_handler.php to redirect back
// with session messages, which header.php will display.
// If using AJAX via js/auth.js or a new js/profile.js, ensure that script is loaded (likely in footer.php)
// and the 'api/auth_handler.php' is not doing PHP redirects for these actions but echoing JSON.
// The current api/auth_handler.php is set up to echo JSON, so JS AJAX submission is the intended path.
// Ensure js/auth.js has the listeners for 'profileDetailsForm' and 'changePasswordForm'.
// The message display from JS would target the alert area in header.php or a specific one on this page.
?>
<script src="js/auth.js"></script> <!-- If profile form AJAX handlers are in auth.js -->


<?php require_once 'includes/footer.php'; // Centralized footer ?>