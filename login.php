<?php
$pageTitle = "Login"; // Set Page Title for header.php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<?php require_once 'includes/header.php'; ?>

<!-- Welcome Overlay Modal -->
<div class="modal fade" id="welcomeModal" tabindex="-1" aria-labelledby="welcomeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-primary" id="welcomeModalLabel">Welcome to TrackYourExpenses</h5>
            </div>
            <div class="modal-body py-0">
                <p>To start managing your finances and tracking expenses, please login to your account.</p>
                <p class="mb-0">If you're new here, you can register for free and start gaining control over your spending habits.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Get Started</button>
            </div>
        </div>
    </div>
</div>

<div class="form-auth-container">
    <div class="text-center mb-4">
        <i class="fas fa-chart-line fa-2x text-primary mb-3"></i>
        <h2 class="h4">Login to TrackYourExpenses</h2>
    </div>

    <form id="loginForm">
        <input type="hidden" name="action" value="login">
        <div class="mb-3">
            <label for="identifier" class="form-label">Username or Email:</label>
            <input type="text" class="form-control" id="identifier" name="identifier" placeholder="Enter username or email" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password:</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
        </div>
    </form>
    <div class="text-center mt-3">
        <p class="mb-1 small">Don't have an account? <a href="register.php" class="fw-bold">Register here</a></p>
    </div>
</div>

<script>
// Show welcome modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    var welcomeModal = new bootstrap.Modal(document.getElementById('welcomeModal'));
    welcomeModal.show();
    
    // Focus on identifier field after modal closes
    document.getElementById('welcomeModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('identifier').focus();
    });
});
</script>

<script src="js/auth.js"></script>
<?php require_once 'includes/footer.php'; ?>