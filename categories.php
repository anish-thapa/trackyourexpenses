<?php
$pageTitle = "Manage Categories"; // Set Page Title for header.php
require_once 'includes/session_check.php';
require_once 'includes/db.php'; // For fetching categories
// helpers.php is included in header.php

$userId = $_SESSION['user_id'];
$categories = [];
$fetch_error = false; // Flag to track if fetching categories failed

if (!isset($conn) || $conn === false) {
    error_log("Database connection not established in categories.php for user ID: " . $userId);
    $_SESSION['message'] = "Database error. Please try again later.";
    $_SESSION['message_type'] = "error";
    $fetch_error = true;
} else {
    $stmt = $conn->prepare("SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY type ASC, name ASC");
    if($stmt){
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare list categories statement in categories.php: " . $conn->error);
        $_SESSION['message'] = "Could not retrieve categories at this time. Please try again.";
        $_SESSION['message_type'] = "error";
        $fetch_error = true;
    }
    $conn->close();
}
?>
<?php require_once 'includes/header.php'; // Centralized header ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $pageTitle; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="add_category.php" class="btn btn-success">
            <i class="fas fa-plus me-1"></i>Add New Category
        </a>
    </div>
</div>

<!-- Session messages are now handled in header.php -->

<?php if ($fetch_error && empty($categories)): ?>
    <?php // Message already set in PHP block and will be displayed by header.php ?>
<?php elseif (empty($categories)): ?>
    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle me-2"></i>You haven't added any categories yet. <a href="add_category.php" class="alert-link">Add your first category!</a>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Type</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td>
                                <?php if ($category['type'] == 'income'): ?>
                                    <span class="badge bg-success-subtle text-success-emphasis rounded-pill">
                                        <i class="fas fa-arrow-up me-1"></i><?php echo htmlspecialchars(ucfirst($category['type'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill">
                                        <i class="fas fa-arrow-down me-1"></i><?php echo htmlspecialchars(ucfirst($category['type'])); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end action-links">
                                <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit Category">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="category_handler.php?action=delete&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete Category" onclick="return confirm('Are you sure you want to delete the category \'<?php echo htmlspecialchars(addslashes($category['name'])); ?>\'? Transactions using this category will become uncategorized.');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; // Centralized footer ?>