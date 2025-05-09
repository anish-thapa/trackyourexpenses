<?php
$pageTitle = "Add New Transaction"; // Set Page Title
require_once 'includes/session_check.php';
require_once 'includes/db.php'; // Need DB to fetch categories
// helpers.php is included in header.php

$userId = $_SESSION['user_id'];
$user_currency = $_SESSION['preferred_currency'] ?? 'USD';
$categories = ['income' => [], 'expense' => []]; // Initialize
$fetch_error_cat = false;

if (!isset($conn) || $conn === false) {
    error_log("Database connection not established in add_transaction.php for user ID: " . $userId);
    $_SESSION['message'] = "Database error. Page may not function correctly.";
    $_SESSION['message_type'] = "danger";
    $fetch_error_cat = true;
} else {
    $stmt_cat = $conn->prepare("SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY name ASC"); // Order by name only for now
    if ($stmt_cat) {
        $stmt_cat->bind_param("i", $userId);
        $stmt_cat->execute();
        $result_cat = $stmt_cat->get_result();
        while ($row_cat = $result_cat->fetch_assoc()) {
            // Store directly under its type (income/expense)
            if (isset($categories[$row_cat['type']])) {
                $categories[$row_cat['type']][] = $row_cat;
            }
        }
        $stmt_cat->close();
    } else {
        error_log("Failed to prepare categories statement in add_transaction.php: " . $conn->error);
        $_SESSION['message'] = "Could not load categories. Please try again or add them from the 'Manage Categories' page.";
        $_SESSION['message_type'] = "warning";
        $fetch_error_cat = true;
    }
    $conn->close();
}

// Retrieve form data from session
$form_data = $_SESSION['form_data'] ?? [];
$transaction_date = $form_data['transaction_date'] ?? date('Y-m-d');
$selected_type = $form_data['type'] ?? 'expense';
$selected_category_id = $form_data['category_id'] ?? '';
$amount = $form_data['amount'] ?? '';
$description = $form_data['description'] ?? '';
?>
<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $pageTitle; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="transactions.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Transactions
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="transaction_handler.php" method="POST" id="addTransactionForm">
            <input type="hidden" name="action" value="add">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="transaction_date" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo htmlspecialchars($transaction_date); ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="expense" <?php echo ($selected_type == 'expense') ? 'selected' : ''; ?>>Expense</option>
                        <option value="income" <?php echo ($selected_type == 'income') ? 'selected' : ''; ?>>Income</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="category_id" class="form-label">Category (Optional)</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">-- Select Category --</option>
                        <?php
                        // This initial PHP rendering is important for the JS to grab original options.
                        // The JS will then dynamically show/hide or repopulate based on transaction type.
                        if (!$fetch_error_cat) {
                            // Iterate through all categories and add data-type. JS will handle grouping.
                            foreach ($categories as $type_group => $category_list) {
                                foreach ($category_list as $category) {
                                    $selected_attr = ($selected_category_id == $category['id']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($category['id']) . '" 
                                                  data-type="' . htmlspecialchars($type_group) . '" ' . $selected_attr . '>'
                                          . htmlspecialchars($category['name']) .
                                          '</option>';
                                }
                            }
                            if (empty($categories['income']) && empty($categories['expense'])) {
                                echo '<option value="" disabled>No categories found. Please add categories first.</option>';
                            }
                        } else {
                            echo '<option value="" disabled>Could not load categories.</option>';
                        }
                        ?>
                    </select>
                    <?php if (!$fetch_error_cat && empty($categories['income']) && empty($categories['expense'])): ?>
                        <div class="form-text">
                            No categories available. You can <a href="categories.php" target="_blank">manage your categories here</a>.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="amount" class="form-label">Amount (<?php echo htmlspecialchars($user_currency); ?>) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" value="<?php echo htmlspecialchars($amount); ?>" placeholder="e.g., 50.75" required>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Description (Optional)</label>
                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="e.g., Groceries, Monthly Salary"><?php echo htmlspecialchars($description); ?></textarea>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle me-1"></i>Add Transaction</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php unset($_SESSION['form_data']); // Clear preserved form data after displaying ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type');
    const categorySelect = document.getElementById('category_id');

    // Store all original category options from the PHP-rendered <select>
    const originalCategoryOptions = [];
    if (categorySelect) {
        for (let i = 0; i < categorySelect.options.length; i++) {
            const option = categorySelect.options[i];
            // Only consider actual category options, not placeholders or disabled messages
            if (option.value !== "" && option.getAttribute('data-type')) {
                originalCategoryOptions.push({
                    value: option.value,
                    text: option.text,
                    dataType: option.getAttribute('data-type')
                });
            }
        }
    }
    // console.log("Original Categories Parsed by JS:", JSON.stringify(originalCategoryOptions));

    function populateFilteredCategories() {
        if (!typeSelect || !categorySelect) return;

        const selectedTransactionType = typeSelect.value; // 'income' or 'expense'
        const currentCategorySelectedValue = categorySelect.value; // Preserve current selection before clearing

        // Clear existing category options (except the placeholder "-- Select Category --")
        while (categorySelect.options.length > 1) {
            categorySelect.remove(1);
        }

        // Create or get optgroups
        let incomeOptgroup = categorySelect.querySelector('optgroup[label="Income"]');
        if (!incomeOptgroup) {
            incomeOptgroup = document.createElement('optgroup');
            incomeOptgroup.label = 'Income';
        } else {
            while (incomeOptgroup.firstChild) incomeOptgroup.removeChild(incomeOptgroup.firstChild);
        }

        let expenseOptgroup = categorySelect.querySelector('optgroup[label="Expense"]');
        if (!expenseOptgroup) {
            expenseOptgroup = document.createElement('optgroup');
            expenseOptgroup.label = 'Expense';
        } else {
            while (expenseOptgroup.firstChild) expenseOptgroup.removeChild(expenseOptgroup.firstChild);
        }
        
        let hasIncomeCategories = false;
        let hasExpenseCategories = false;

        originalCategoryOptions.forEach(optData => {
            // Only add options that match the selected transaction type
            if (optData.dataType === selectedTransactionType) {
                const optionElement = document.createElement('option');
                optionElement.value = optData.value;
                optionElement.textContent = optData.text;
                // optionElement.setAttribute('data-type', optData.dataType); // Not strictly needed on created option

                if (selectedTransactionType === 'income') {
                    incomeOptgroup.appendChild(optionElement);
                    hasIncomeCategories = true;
                } else if (selectedTransactionType === 'expense') {
                    expenseOptgroup.appendChild(optionElement);
                    hasExpenseCategories = true;
                }
            }
        });

        // Append optgroups only if they have relevant options
        if (selectedTransactionType === 'income' && hasIncomeCategories) {
            categorySelect.appendChild(incomeOptgroup);
        } else if (selectedTransactionType === 'expense' && hasExpenseCategories) {
            categorySelect.appendChild(expenseOptgroup);
        }

        // If no categories for the selected type after filtering, but categories exist in general
        if (selectedTransactionType === 'income' && !hasIncomeCategories && originalCategoryOptions.some(o => o.dataType === 'income')) {
            const noCatOption = document.createElement('option');
            noCatOption.value = ""; noCatOption.textContent = "No Income categories available."; noCatOption.disabled = true;
            categorySelect.appendChild(noCatOption);
        } else if (selectedTransactionType === 'expense' && !hasExpenseCategories && originalCategoryOptions.some(o => o.dataType === 'expense')) {
            const noCatOption = document.createElement('option');
            noCatOption.value = ""; noCatOption.textContent = "No Expense categories available."; noCatOption.disabled = true;
            categorySelect.appendChild(noCatOption);
        } else if (originalCategoryOptions.length === 0 && !<?php echo json_encode($fetch_error_cat); ?>) {
             // This is covered by PHP initial render, but as a JS fallback if list was empty.
            const noCatOption = document.createElement('option');
            noCatOption.value = ""; noCatOption.textContent = "No categories defined yet."; noCatOption.disabled = true;
            categorySelect.appendChild(noCatOption);
        }


        // Try to re-select the previously selected category if it's still valid
        let foundPreviousSelection = false;
        for (let i = 0; i < categorySelect.options.length; i++) {
            if (categorySelect.options[i].value === currentCategorySelectedValue) {
                 // Check if the selected option's parent optgroup matches the current transaction type
                const parentOptgroup = categorySelect.options[i].parentElement;
                if (parentOptgroup && parentOptgroup.tagName === 'OPTGROUP' && parentOptgroup.label.toLowerCase().includes(selectedTransactionType)) {
                    categorySelect.selectedIndex = i;
                    foundPreviousSelection = true;
                    break;
                } else if (!parentOptgroup && categorySelect.options[i].value !== "") { 
                    // For options not in an optgroup (shouldn't happen with this logic but defensive)
                    // This part is less likely to be hit if optgroups are always used
                }
            }
        }

        if (!foundPreviousSelection && currentCategorySelectedValue !== "") {
            // If the previously selected category is no longer valid for the new type, reset selection.
            // But only if there was an actual previous selection (not just the placeholder).
            const previousOptionData = originalCategoryOptions.find(opt => opt.value === currentCategorySelectedValue);
            if (previousOptionData && previousOptionData.dataType !== selectedTransactionType) {
                 categorySelect.value = ""; // Reset to placeholder
            } else if (previousOptionData) {
                // If it was a valid category for the current type but somehow not re-selected by the loop
                // this indicates a potential logic issue in re-selection. For now, we'll assume the loop handles it.
                // If not, setting categorySelect.value = currentCategorySelectedValue directly might work.
            } else {
                categorySelect.value = ""; // General reset if not found
            }
        } else if (!foundPreviousSelection && currentCategorySelectedValue === "") {
            // If placeholder was selected, keep it selected.
            categorySelect.value = "";
        }
    }

    if (typeSelect && categorySelect) {
        populateFilteredCategories(); // Initial population
        typeSelect.addEventListener('change', populateFilteredCategories);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>