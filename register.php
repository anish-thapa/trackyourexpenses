<?php
$pageTitle = "Register"; // Set Page Title for header.php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="form-auth-container">
    <div class="text-center mb-4">
        <i class="fas fa-user-plus fa-2x text-primary mb-3"></i> <!-- Matched icon size to login -->
        <h2 class="h4">Create Your Account</h2> <!-- Matched heading size to login -->
    </div>

    <form id="registerForm">
        <input type="hidden" name="action" value="register">
        <div class="mb-3">
            <label for="username" class="form-label">Username:</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Choose a username" required> <!-- Removed form-control-lg -->
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email Address:</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required> <!-- Removed form-control-lg -->
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password (min 6 characters):</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required minlength="6"> <!-- Removed form-control-lg -->
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm Password:</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required> <!-- Removed form-control-lg -->
        </div>
        <div class="mb-3">
            <label for="preferred_currency" class="form-label">Preferred Currency:</label>
            <select class="form-select" id="preferred_currency" name="preferred_currency" required> <!-- Removed form-select-lg -->
                <option value="USD" selected>USD ($) - US Dollar</option>
                <option value="EUR">EUR (€) - Euro</option>
                <option value="NPR">NPR (रु) - Nepalese Rupee</option>
            </select>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary"> <!-- Changed to btn-primary to match login -->
                <i class="fas fa-user-plus me-2"></i>Register <!-- Changed icon to match login style -->
            </button>
        </div>
    </form>
    <div class="text-center mt-3"> <!-- Changed mt-4 to mt-3 -->
        <p class="mb-1 small">Already have an account? <a href="login.php" class="fw-bold">Login here</a></p> <!-- Added small class -->
    </div>
</div>

<script src="js/auth.js"></script>
<?php require_once 'includes/footer.php'; ?>