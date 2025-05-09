<?php
$pageTitle = "Edit Category"; // Set Page Title
require_once 'includes/session_check.php';
require_once 'includes/db.php';
// helpers.php is included in header.php

$userId = $_SESSION['user_id'];
$categoryId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$category = null;
$fetch_error = false;

if (!$categoryId) {
    $_SESSION['message'] = "Invalid category ID specified.";
    $_SESSION['message_type'] = "error";
    header('Location: categories.php');
    exit();
}

if (!isset($conn) || $conn === false) {
    error_log("Database connection not established in edit_category.php for user ID: " . $userId);
    $_SESSION['form_message'] = "Database error. Cannot load category data.";
    $_SESSION['form_message_type'] = "error";
    $fetch_error = true;
} else {
    $stmt = $conn->prepare("SELECT id, name, type FROM categories WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $categoryId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $category = $result->fetch_assoc();
        } else {
            $_SESSION['message'] = "Category not found or you don't have permission to edit it.";
            $_SESSION['message_type'] = "error";
            $stmt->close();
            $conn->close();
            header('Location: categories.php');
            exit();
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare category fetch statement in edit_category.php: " . $conn->error);
        $_SESSION['form_message'] = "Could not load category data. Please try again.";
        $_SESSION['form_message_type'] = "error";
        $fetch_error = true;
    }
    $conn->close(); // Close connection after fetching
}


// Use session form data if available (e.g., after a validation error on POST from handler)
// Otherwise, use data from the fetched category
$form_data = $_SESSION['form_data'] ?? ($category ?? []); // If category not fetched, $form_data might be empty

$formName = $form_data['name'] ?? '';
$formType = $form_data['type'] ?? 'expense';

?>
<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $pageTitle; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="categories.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Categories
        </a>
    </div>
</div>

<!-- Form messages are now handled in header.php -->

<?php if ($fetch_error && !$category): // If category itself couldn't be loaded, don't show form ?>
    <?php // Message is already set and will be shown by header.php ?>
<?php else: ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form action="category_handler.php" method="POST" id="editCategoryForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category['id'] ?? ''); ?>">
            
            <div class="mb-3">
                <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($formName); ?>" placeholder="e.g., Salary, Groceries" required>
            </div>
            
            <div class="mb-3">
                <label for="type" class="form-label">Category Type <span class="text-danger">*</span></label>
                <select class="form-select" id="type" name="type" required>
                    <option value="expense" <?php echo ($formType == 'expense') ? 'selected' : ''; ?>>Expense</option>
                    <option value="income" <?php echo ($formType == 'income') ? 'selected' : ''; ?>>Income</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Category</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php unset($_SESSION['form_data']); // Clear preserved form data after displaying ?>

<?php require_once 'includes/footer.php'; ?>