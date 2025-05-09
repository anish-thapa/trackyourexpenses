<?php
require_once 'includes/session_check.php';
require_once 'includes/db.php';

$userId = $_SESSION['user_id'];

// Ensure $conn is available from db.php
if (!isset($conn) || $conn === false) {
    $_SESSION['message'] = "Database connection error. Please try again later.";
    $_SESSION['message_type'] = "error";
    // Determine a safe redirect, categories.php or index.php might be good choices
    // If categories.php also relies on DB, index.php might be safer.
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $_SESSION['form_data'] = $_POST; // Preserve form data for repopulation on error

        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $type = trim($_POST['type'] ?? '');

            if (empty($name) || strlen($name) > 100) {
                $_SESSION['form_message'] = "Category name is required and must be 1-100 characters.";
                $_SESSION['form_message_type'] = "error";
                header('Location: add_category.php');
                exit();
            }
            if ($type !== 'income' && $type !== 'expense') {
                $_SESSION['form_message'] = "Invalid category type selected.";
                $_SESSION['form_message_type'] = "error";
                header('Location: add_category.php');
                exit();
            }

            $stmt_check = $conn->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND type = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("iss", $userId, $name, $type);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['form_message'] = "A category with this name and type already exists.";
                    $_SESSION['form_message_type'] = "error";
                    $stmt_check->close();
                    header('Location: add_category.php');
                    exit();
                }
                $stmt_check->close();
            } else {
                error_log("Category Add - Check Prepare failed: " . $conn->error);
                $_SESSION['form_message'] = "Error checking existing categories. Please try again.";
                $_SESSION['form_message_type'] = "error";
                header('Location: add_category.php');
                exit();
            }

            $stmt_insert = $conn->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
            if ($stmt_insert) {
                $stmt_insert->bind_param("iss", $userId, $name, $type);
                if ($stmt_insert->execute()) {
                    $_SESSION['message'] = "Category added successfully!";
                    $_SESSION['message_type'] = "success";
                    unset($_SESSION['form_data']); // Clear preserved data on success
                } else {
                    $_SESSION['message'] = "Error adding category. Please try again.";
                    $_SESSION['message_type'] = "error";
                    error_log("Error adding category (User: $userId): " . $stmt_insert->error);
                }
                $stmt_insert->close();
            } else {
                error_log("Category Add - Insert Prepare failed: " . $conn->error);
                $_SESSION['message'] = "Error preparing to add category. Please try again.";
                $_SESSION['message_type'] = "error";
            }
            header('Location: categories.php');
            exit();

        } elseif ($action === 'edit') {
            $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
            $name = trim($_POST['name'] ?? '');
            $type = trim($_POST['type'] ?? '');

            if (!$categoryId) {
                $_SESSION['message'] = "Invalid category ID for editing."; // General message for categories list
                $_SESSION['message_type'] = "error";
                header('Location: categories.php');
                exit();
            }
            if (empty($name) || strlen($name) > 100) {
                $_SESSION['form_message'] = "Category name is required and must be 1-100 characters."; // Form specific
                $_SESSION['form_message_type'] = "error";
                header('Location: edit_category.php?id=' . $categoryId);
                exit();
            }
            if ($type !== 'income' && $type !== 'expense') {
                $_SESSION['form_message'] = "Invalid category type selected."; // Form specific
                $_SESSION['form_message_type'] = "error";
                header('Location: edit_category.php?id=' . $categoryId);
                exit();
            }

            $stmt_check = $conn->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND type = ? AND id != ?");
            if ($stmt_check) {
                $stmt_check->bind_param("issi", $userId, $name, $type, $categoryId);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $_SESSION['form_message'] = "Another category with this name and type already exists.";
                    $_SESSION['form_message_type'] = "error";
                    $stmt_check->close();
                    header('Location: edit_category.php?id=' . $categoryId);
                    exit();
                }
                $stmt_check->close();
            } else {
                error_log("Category Edit - Check Prepare failed: " . $conn->error);
                $_SESSION['form_message'] = "Error checking category details. Please try again.";
                $_SESSION['form_message_type'] = "error";
                header('Location: edit_category.php?id=' . $categoryId);
                exit();
            }

            $stmt_update = $conn->prepare("UPDATE categories SET name = ?, type = ? WHERE id = ? AND user_id = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("ssii", $name, $type, $categoryId, $userId);
                if ($stmt_update->execute()) {
                    if ($stmt_update->affected_rows > 0) {
                        $_SESSION['message'] = "Category updated successfully!";
                        $_SESSION['message_type'] = "success";
                        unset($_SESSION['form_data']);
                    } else {
                        // Verify if the category actually exists for the user if no rows affected
                        $verify_stmt = $conn->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
                        if ($verify_stmt) {
                            $verify_stmt->bind_param("ii", $categoryId, $userId);
                            $verify_stmt->execute();
                            $verify_stmt->store_result();
                            if ($verify_stmt->num_rows == 0) {
                                $_SESSION['message'] = "Category not found or you do not have permission to edit it.";
                                $_SESSION['message_type'] = "error";
                            } else {
                                $_SESSION['message'] = "No changes were made to the category.";
                                $_SESSION['message_type'] = "info";
                            }
                            $verify_stmt->close();
                        } else {
                             $_SESSION['message'] = "No changes made, and verification query failed.";
                             $_SESSION['message_type'] = "warning";
                        }
                    }
                } else {
                    $_SESSION['message'] = "Error updating category. Please try again.";
                    $_SESSION['message_type'] = "error";
                    error_log("Error updating category (ID: $categoryId, User: $userId): " . $stmt_update->error);
                }
                $stmt_update->close();
            } else {
                error_log("Category Edit - Update Prepare failed: " . $conn->error);
                $_SESSION['message'] = "Error preparing to update category. Please try again.";
                $_SESSION['message_type'] = "error";
            }
            header('Location: categories.php');
            exit();
        }
    } else {
        $_SESSION['message'] = "Invalid action specified.";
        $_SESSION['message_type'] = "error";
        header('Location: categories.php');
        exit();
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $categoryId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$categoryId) {
        $_SESSION['message'] = "Invalid category ID for deletion.";
        $_SESSION['message_type'] = "error";
        header('Location: categories.php');
        exit();
    }

    $stmt_delete = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    if (!$stmt_delete) {
        error_log("Category Deletion - Prepare failed: " . $conn->error);
        $_SESSION['message'] = "Error preparing category deletion. Please try again.";
        $_SESSION['message_type'] = "error";
        header('Location: categories.php');
        exit();
    }

    $stmt_delete->bind_param("ii", $categoryId, $userId);
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $_SESSION['message'] = "Category deleted successfully. Related transactions are now uncategorized.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Category not found or you do not have permission to delete it.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Error deleting category. Please try again.";
        $_SESSION['message_type'] = "error";
        error_log("Error deleting category (ID: $categoryId, User: $userId): " . $stmt_delete->error);
    }
    $stmt_delete->close();
    header('Location: categories.php');
    exit();

} else {
    $_SESSION['message'] = "Invalid request for category handler.";
    $_SESSION['message_type'] = "error";
    header('Location: categories.php');
    exit();
}

if (isset($conn) && $conn) {
    $conn->close();
}
?>