<?php
$pageTitle = "Edit Transaction"; // Set Page Title
require_once 'includes/session_check.php';
require_once 'includes/db.php';
// helpers.php is included in header.php

$userId = $_SESSION['user_id'];
$user_currency = $_SESSION['preferred_currency'] ?? 'USD';
$transactionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$transaction = null;
$categories = ['income' => [], 'expense' => []]; // Initialize
$fetch_error_trans = false;
$fetch_error_cat = false;

if (!$transactionId) {
    $_SESSION['message'] = "Invalid transaction ID specified.";
    $_SESSION['message_type'] = "error";
    header('Location: transactions.php');
    exit();
}

if (!isset($conn) || $conn === false) {
    error_log("Database connection not established in edit_transaction.php for user ID: " . $userId);
    $_SESSION['message'] = "Database error. Page may not function correctly.";
    $_SESSION['message_type'] = "danger";
    $fetch_error_trans = true;
    $fetch_error_cat = true; // Assume categories also can't be fetched
} else {
    // Fetch the transaction to edit
    $stmt_trans = $conn->prepare("SELECT id, transaction_date, category_id, type, amount, description FROM transactions WHERE id = ? AND user_id = ?");
    if ($stmt_trans) {
        $stmt_trans->bind_param("ii", $transactionId, $userId);
        $stmt_trans->execute();
        $result_trans = $stmt_trans->get_result();
        if ($result_trans->num_rows === 1) {
            $transaction = $result_trans->fetch_assoc();
        } else {
            $_SESSION['message'] = "Transaction not found or you don't have permission to edit it.";
            $_SESSION['message_type'] = "error";
            $stmt_trans->close();
            $conn->close();
            header('Location: transactions.php');
            exit();
        }
        $stmt_trans->close();
    } else {
        error_log("Failed to prepare transaction fetch statement in edit_transaction.php: " . $conn->error);
        $_SESSION['message'] = "Could not load transaction data. Please try again.";
        $_SESSION['message_type'] = "error";
        $fetch_error_trans = true;
    }

    // Fetch categories for the dropdown (only if transaction was fetched or connection is okay)
    if (!$fetch_error_trans) { // If transaction fetch failed, likely DB issue, skip category fetch
        $stmt_cat = $conn->prepare("SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY name ASC");
        if ($stmt_cat) {
            $stmt_cat->bind_param("i", $userId);
            $stmt_cat->execute();
            $result_cat = $stmt_cat->get_result();
            while ($row_cat = $result_cat->fetch_assoc()) {
                if (isset($categories[$row_cat['type']])) {
                    $categories[$row_cat['type']][] = $row_cat;
                }
            }
            $stmt_cat->close();
        } else {
            error_log("Failed to prepare categories statement in edit_transaction.php: " . $conn->error);
            $_SESSION['message'] = ($_SESSION['message'] ?? '') . "<br>Could not load categories. Please try again or add them from the 'Manage Categories' page.";
            $_SESSION['message_type'] = "warning"; // Keep overall page message, or override if more severe
            $fetch_error_cat = true;
        }
    }
    $conn->close();
}

// Use session form data if available (after a failed update attempt), else use fetched transaction data
$form_data = $_SESSION['form_data'] ?? ($transaction ?? []);

$transaction_date = $form_data['transaction_date'] ?? date('Y-m-d');
$selected_type = $form_data['type'] ?? 'expense';
$selected_category_id = $form_data['category_id'] ?? ''; // Default to empty string if null for select
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

<?php if ($fetch_error_trans && !$transaction): ?>
    <?php // Message already set and will be displayed by header.php, no form needed. ?>
<?php else: ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form action="transaction_handler.php" method="POST" id="editTransactionForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="transaction_id" value="<?php echo htmlspecialchars($transaction['id'] ?? ''); ?>">

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
                        if (!$fetch_error_cat) {
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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Transaction</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php unset($_SESSION['form_data']); // Clear preserved form data after displaying ?>

<script>
// --- COPY THE EXACT SAME JAVASCRIPT FROM add_transaction.php HERE ---
// It is designed to work for both add and edit forms.
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type');
    const categorySelect = document.getElementById('category_id');

    const originalCategoryOptions = [];
    if (categorySelect) {
        for (let i = 0; i < categorySelect.options.length; i++) {
            const option = categorySelect.options[i];
            if (option.value !== "" && option.getAttribute('data-type')) {
                originalCategoryOptions.push({
                    value: option.value,
                    text: option.text,
                    dataType: option.getAttribute('data-type')
                });
            }
        }
    }
    // console.log("Edit Original Categories Parsed by JS:", JSON.stringify(originalCategoryOptions));


    function populateFilteredCategories() {
        if (!typeSelect || !categorySelect) return;

        const selectedTransactionType = typeSelect.value;
        const currentCategorySelectedValue = categorySelect.value;

        while (categorySelect.options.length > 1) {
            categorySelect.remove(1);
        }

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
            if (optData.dataType === selectedTransactionType) {
                const optionElement = document.createElement('option');
                optionElement.value = optData.value;
                optionElement.textContent = optData.text;

                if (selectedTransactionType === 'income') {
                    incomeOptgroup.appendChild(optionElement);
                    hasIncomeCategories = true;
                } else if (selectedTransactionType === 'expense') {
                    expenseOptgroup.appendChild(optionElement);
                    hasExpenseCategories = true;
                }
            }
        });

        if (selectedTransactionType === 'income' && hasIncomeCategories) {
            categorySelect.appendChild(incomeOptgroup);
        } else if (selectedTransactionType === 'expense' && hasExpenseCategories) {
            categorySelect.appendChild(expenseOptgroup);
        }
        
        if (selectedTransactionType === 'income' && !hasIncomeCategories && originalCategoryOptions.some(o => o.dataType === 'income')) {
            const noCatOption = document.createElement('option');
            noCatOption.value = ""; noCatOption.textContent = "No Income categories available."; noCatOption.disabled = true;
            categorySelect.appendChild(noCatOption);
        } else if (selectedTransactionType === 'expense' && !hasExpenseCategories && originalCategoryOptions.some(o => o.dataType === 'expense')) {
            const noCatOption = document.createElement('option');
            noCatOption.value = ""; noCatOption.textContent = "No Expense categories available."; noCatOption.disabled = true;
            categorySelect.appendChild(noCatOption);
        } else if (originalCategoryOptions.length === 0 && !<?php echo json_encode($fetch_error_cat); ?>) {
            const noCatOption = document.createElement('option');
            noCatOption.value = ""; noCatOption.textContent = "No categories defined yet."; noCatOption.disabled = true;
            categorySelect.appendChild(noCatOption);
        }

        let foundPreviousSelection = false;
        for (let i = 0; i < categorySelect.options.length; i++) {
            if (categorySelect.options[i].value === currentCategorySelectedValue) {
                const parentOptgroup = categorySelect.options[i].parentElement;
                if (parentOptgroup && parentOptgroup.tagName === 'OPTGROUP' && parentOptgroup.label.toLowerCase().includes(selectedTransactionType)) {
                    categorySelect.selectedIndex = i;
                    foundPreviousSelection = true;
                    break;
                }
            }
        }

        if (!foundPreviousSelection && currentCategorySelectedValue !== "") {
            const previousOptionData = originalCategoryOptions.find(opt => opt.value === currentCategorySelectedValue);
            if (previousOptionData && previousOptionData.dataType !== selectedTransactionType) {
                 categorySelect.value = "";
            } else if (previousOptionData) {
                 // It was a valid category for the current type but not found in the dynamically built list above
                 // This might happen if its optgroup wasn't added.
                 // We need to ensure the optgroup is added if ANY category of that type exists.
                 // The current logic for appending optgroups should handle this.
                 // If still an issue, might need to force categorySelect.value = currentCategorySelectedValue; here if previousOptionData exists.
            } else {
                categorySelect.value = "";
            }
        } else if (!foundPreviousSelection && currentCategorySelectedValue === "") {
            categorySelect.value = "";
        }
    }

    if (typeSelect && categorySelect) {
        populateFilteredCategories();
        typeSelect.addEventListener('change', populateFilteredCategories);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>