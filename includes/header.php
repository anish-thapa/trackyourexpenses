<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'helpers.php'; // Ensure this is available

$is_logged_in = isset($_SESSION['user_id']);
$current_page = basename($_SERVER['PHP_SELF']);
$user_currency = $_SESSION['preferred_currency'] ?? 'USD'; // Fallback

$pageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) . " | TYE." : "TrackYourExpenses";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta property="og:image" content="assets/tymnobg.png" />
    <title><?php echo $pageTitle; ?></title>
    <link rel="shortcut icon" href="assets/tymnobg.png" type="image/x-icon">

    <!-- Google Fonts (Optional - for nicer typography) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <!-- Chart.js (if needed on many pages, otherwise include per-page) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="d-flex flex-column min-vh-100"> 

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bolder text-primary" href="index.php">
            <i class="fas fa-chart-line fa text-primary mb-2"></i> TrackYourExpenses
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
           <ul class="navbar-nav ms-auto align-items-lg-center">
    <?php if ($is_logged_in): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active text-primary fw-semibold' : ''; ?>" href="index.php">Dashboard</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (in_array($current_page, ['transactions.php', 'add_transaction.php', 'edit_transaction.php'])) ? 'active text-primary fw-semibold' : ''; ?>" href="transactions.php">Transactions</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (in_array($current_page, ['categories.php', 'add_category.php', 'edit_category.php'])) ? 'active text-primary fw-semibold' : ''; ?>" href="categories.php">Categories</a>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?php echo ($current_page == 'profile.php') ? 'active text-primary fw-semibold' : ''; ?>" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" aria-labelledby="navbarDropdownUser">
                <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-cog me-2 text-muted"></i>Profile Settings</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </li>
    <?php else: ?>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'login.php') ? 'active text-primary fw-semibold' : ''; ?>" href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a>
        </li>
        <li class="nav-item">
            <a href="register.php" class="btn btn-primary btn-sm ms-lg-2"><i class="fas fa-user-plus me-1"></i>Register</a>
        </li>
    <?php endif; ?>
</ul>

        </div>
    </div>
</nav>

<main class="container flex-grow-1 py-4">
    <div id="page-message-area" class="mb-3">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message_type'] == 'error' ? 'danger' : ($_SESSION['message_type'] == 'success' ? 'success' : ($_SESSION['message_type'] ?? 'info'))); ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['form_message'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_SESSION['form_message_type'] == 'error' ? 'danger' : ($_SESSION['form_message_type'] ?? 'warning')); ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['form_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['form_message']); unset($_SESSION['form_message_type']); ?>
        <?php endif; ?>
    </div>
    <!-- Page-specific content starts here -->