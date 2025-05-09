<?php
require_once 'includes/session_check.php';
require_once 'includes/db.php';

$userId = $_SESSION['user_id'];

if (!isset($conn) || $conn === false) {
    $_SESSION['message'] = "Database connection error. Please try again later.";
    $_SESSION['message_type'] = "error";
    header('Location: index.php'); // Redirect to a safe page
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $_SESSION['form_data'] = $_POST; // Preserve form data

        if ($action === 'add' || $action === 'edit') {
            $transaction_date = trim($_POST['transaction_date'] ?? '');
            $type = trim($_POST['type'] ?? '');
            $raw_category_id = trim($_POST['category_id'] ?? '');
            $category_id = ($raw_category_id === '' || $raw_category_id === null) ? null : filter_var($raw_category_id, FILTER_VALIDATE_INT);
            
            $raw_amount = trim($_POST['amount'] ?? '');
            $amount = ($raw_amount === '') ? null : filter_var($raw_amount, FILTER_VALIDATE_FLOAT);
            
            $description = trim($_POST['description'] ?? '');
            $transaction_id = ($action === 'edit') ? filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT) : null;

            $errors = [];
            if (empty($transaction_date) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $transaction_date) || strtotime($transaction_date) === false) {
                $errors[] = "A valid transaction date is required (YYYY-MM-DD).";
            }
            if ($type !== 'income' && $type !== 'expense') {
                $errors[] = "Invalid transaction type selected.";
            }
            if ($amount === null || $amount === false || $amount <= 0) {
                $errors[] = "Amount must be a positive number.";
            }
            if (strlen($description) > 255) {
                $errors[] = "Description cannot exceed 255 characters.";
            }
            if ($action === 'edit' && !$transaction_id) {
                $errors[] = "Invalid Transaction ID for editing.";
            }
            if ($raw_category_id !== '' && $raw_category_id !== null && $category_id === false) {
                 $errors[] = "Invalid category ID format.";
            }

            if ($category_id !== null) { // category_id is an integer or null
                $stmt_cat_check = $conn->prepare("SELECT type FROM categories WHERE id = ? AND user_id = ?");
                if ($stmt_cat_check) {
                    $stmt_cat_check->bind_param("ii", $category_id, $userId);
                    $stmt_cat_check->execute();
                    $result_cat_check = $stmt_cat_check->get_result();
                    if ($result_cat_check->num_rows === 0) {
                        $errors[] = "Selected category not found or does not belong to you.";
                    } else {
                        $cat_details = $result_cat_check->fetch_assoc();
                        if ($cat_details['type'] !== $type) {
                            $errors[] = "The selected category type ('" . htmlspecialchars($cat_details['type']) . "') does not match the transaction type ('" . htmlspecialchars($type) . "').";
                        }
                    }
                    $stmt_cat_check->close();
                } else {
                    error_log("Transaction Add/Edit - Category Check Prepare failed: " . $conn->error);
                    $errors[] = "Error verifying category. Please try again.";
                }
            }

            if (!empty($errors)) {
                $_SESSION['form_message'] = implode("<br>", $errors);
                $_SESSION['form_message_type'] = "error";
                if ($action === 'add') {
                    header('Location: add_transaction.php');
                } else {
                    header('Location: edit_transaction.php?id=' . ($transaction_id ?? '0')); // Ensure ID is passed
                }
                exit();
            }

            // Proceed with DB operation
            if ($action === 'add') {
                $sql = "INSERT INTO transactions (user_id, category_id, type, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("iisdss", $userId, $category_id, $type, $amount, $description, $transaction_date);
                }
            } else { // 'edit'
                $sql = "UPDATE transactions SET category_id = ?, type = ?, amount = ?, description = ?, transaction_date = ? WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("isdssii", $category_id, $type, $amount, $description, $transaction_date, $transaction_id, $userId);
                }
            }

            if ($stmt) {
                if ($stmt->execute()) {
                    $operation = ($action === 'add') ? "added" : "updated";
                    if ($action === 'edit' && $stmt->affected_rows === 0) {
                        // Verify if the transaction actually exists for the user if no rows affected
                        $verify_stmt = $conn->prepare("SELECT id FROM transactions WHERE id = ? AND user_id = ?");
                        if($verify_stmt){
                            $verify_stmt->bind_param("ii", $transaction_id, $userId);
                            $verify_stmt->execute();
                            $verify_stmt->store_result();
                             if ($verify_stmt->num_rows == 0) {
                                $_SESSION['message'] = "Transaction not found or you do not have permission to edit it.";
                                $_SESSION['message_type'] = "error";
                            } else {
                                $_SESSION['message'] = "No changes were made to the transaction.";
                                $_SESSION['message_type'] = "info";
                            }
                            $verify_stmt->close();
                        } else {
                            $_SESSION['message'] = "No changes made, and verification query failed.";
                            $_SESSION['message_type'] = "warning";
                        }
                    } else {
                       $_SESSION['message'] = "Transaction " . $operation . " successfully!";
                       $_SESSION['message_type'] = "success";
                       unset($_SESSION['form_data']);
                    }
                } else {
                    $_SESSION['message'] = "Error: Could not " . $action . " transaction. Please try again.";
                    $_SESSION['message_type'] = "error";
                    error_log("Transaction " . $action . " error (User: $userId): " . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log("Transaction Add/Edit - SQL Prepare failed: " . $conn->error);
                $_SESSION['message'] = "Error preparing transaction. Please try again.";
                $_SESSION['message_type'] = "error";
            }
            header('Location: transactions.php');
            exit();
        }
    } else {
        $_SESSION['message'] = "Invalid action specified for transaction.";
        $_SESSION['message_type'] = "error";
        header('Location: transactions.php');
        exit();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $transactionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$transactionId) {
        $_SESSION['message'] = "Invalid transaction ID for deletion.";
        $_SESSION['message_type'] = "error";
        header('Location: transactions.php');
        exit();
    }

    $stmt_delete = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    if (!$stmt_delete) {
        error_log("Transaction Deletion - Prepare failed: " . $conn->error);
        $_SESSION['message'] = "Error preparing transaction deletion. Please try again.";
        $_SESSION['message_type'] = "error";
        header('Location: transactions.php');
        exit();
    }

    $stmt_delete->bind_param("ii", $transactionId, $userId);
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $_SESSION['message'] = "Transaction deleted successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Transaction not found or you do not have permission to delete it.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Error deleting transaction. Please try again.";
        $_SESSION['message_type'] = "error";
        error_log("Error deleting transaction (ID: $transactionId, User: $userId): " . $stmt_delete->error);
    }
    $stmt_delete->close();
    header('Location: transactions.php');
    exit();

} else {
    $_SESSION['message'] = "Invalid request method for transaction handler.";
    $_SESSION['message_type'] = "error";
    header('Location: transactions.php');
    exit();
}

if (isset($conn) && $conn) {
    $conn->close();
}
?>