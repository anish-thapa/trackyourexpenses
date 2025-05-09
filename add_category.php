<?php
$pageTitle = "Add New Category"; // Set Page Title
require_once 'includes/session_check.php';
// No DB connection needed just to display the form initially
// helpers.php is included in header.php

// Retrieve form data from session if it exists (after a failed submission from handler)
$form_data = $_SESSION['form_data'] ?? [];
$formName = $form_data['name'] ?? '';
$formType = $form_data['type'] ?? 'expense'; // Default to expense
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

<!-- Form messages are now handled in header.php (for $_SESSION['form_message']) -->

<div class="card shadow-sm">
    <div class="card-body">
        <form action="category_handler.php" method="POST" id="addCategoryForm">
            <input type="hidden" name="action" value="add">
            
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
            
            <button type="submit" class="btn btn-success"><i class="fas fa-plus-circle me-1"></i>Add Category</button>
        </form>
    </div>
</div>
<?php unset($_SESSION['form_data']); // Clear preserved form data after displaying ?>

<?php require_once 'includes/footer.php'; ?>