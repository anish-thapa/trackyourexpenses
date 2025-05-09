<?php
$pageTitle = "Manage Transactions"; // Set Page Title for header.php
require_once 'includes/session_check.php';
require_once 'includes/db.php'; // For fetching transactions and categories
// helpers.php is included in header.php

$userId = $_SESSION['user_id'];
$user_currency = $_SESSION['preferred_currency'] ?? 'USD'; // Get user's currency
$transactions = [];
$fetch_error = false;

// --- Filtering Logic (Basic Example - by Month and Type) ---
$filter_month = $_GET['filter_month'] ?? date('Y-m'); // Default to current month
$filter_type = $_GET['filter_type'] ?? ''; // All types by default

$sql_conditions = "WHERE t.user_id = ?";
$sql_params_types = "i";
$sql_params_values = [$userId];

if (!empty($filter_month)) {
    $sql_conditions .= " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $filter_month;
}

if (!empty($filter_type) && in_array($filter_type, ['income', 'expense'])) {
    $sql_conditions .= " AND t.type = ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $filter_type;
}
// --- End Filtering Logic ---


if (!isset($conn) || $conn === false) {
    error_log("Database connection not established in transactions.php for user ID: " . $userId);
    $_SESSION['message'] = "Database error. Please try again later.";
    $_SESSION['message_type'] = "error";
    $fetch_error = true;
} else {
    $sql = "SELECT t.id, t.transaction_date, t.description, t.type, t.amount, c.name as category_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id AND c.user_id = t.user_id /* Ensure category is also user's */
            $sql_conditions
            ORDER BY t.transaction_date DESC, t.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Dynamically bind parameters
        if (!empty($sql_params_values)) {
            $stmt->bind_param($sql_params_types, ...$sql_params_values);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare list transactions statement in transactions.php: " . $conn->error . " SQL: " . $sql);
        $_SESSION['message'] = "Could not retrieve transactions. Please try again. " . $conn->error;
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
        <a href="add_transaction.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Add New Transaction
        </a>
    </div>
</div>

<!-- Filter Form -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="transactions.php" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="filter_month" class="form-label">Month:</label>
                <input type="month" name="filter_month" id="filter_month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_month); ?>">
            </div>
            <div class="col-md-4">
                <label for="filter_type" class="form-label">Type:</label>
                <select name="filter_type" id="filter_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="income" <?php if($filter_type == 'income') echo 'selected'; ?>>Income</option>
                    <option value="expense" <?php if($filter_type == 'expense') echo 'selected'; ?>>Expense</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-info btn-sm w-100"><i class="fas fa-search me-1"></i>Apply Filters</button>
            </div>
             <div class="col-md-auto">
                <a href="transactions.php" class="btn btn-secondary btn-sm w-100"><i class="fas fa-times me-1"></i>Clear Filters</a>
            </div>
        </form>
    </div>
</div>


<!-- Session messages are handled in header.php -->

<?php if ($fetch_error && empty($transactions)): ?>
    <?php // Message already set and will be displayed by header.php ?>
<?php elseif (empty($transactions)): ?>
    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle me-2"></i>No transactions found for the selected criteria. <a href="add_transaction.php" class="alert-link">Add your first transaction!</a>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Category</th>
                            <th scope="col">Description</th>
                            <th scope="col">Type</th>
                            <th scope="col" class="text-end">Amount</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date("M d, Y", strtotime($transaction['transaction_date']))); ?></td>
                            <td><?php echo htmlspecialchars($transaction['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($transaction['description'])); ?></td>
                            <td>
                                <?php if ($transaction['type'] == 'income'): ?>
                                    <span class="badge bg-success-subtle text-success-emphasis rounded-pill">
                                        <i class="fas fa-arrow-up me-1"></i>Income
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill">
                                        <i class="fas fa-arrow-down me-1"></i>Expense
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold <?php echo ($transaction['type'] == 'income') ? 'text-success' : 'text-danger'; ?>">
                                <?php echo format_currency(floatval($transaction['amount']), $user_currency); ?>
                            </td>
                            <td class="text-end action-links">
                                <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit Transaction">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="transaction_handler.php?action=delete&id=<?php echo $transaction['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete Transaction" onclick="return confirm('Are you sure you want to delete this transaction? This action cannot be undone.');">
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