<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit;
}
include('../../dbcon.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic drug information
    $id = intval($_POST['id']);
    $product_code   = mysqli_real_escape_string($con, $_POST['product_code']);
    $medicine_name  = mysqli_real_escape_string($con, $_POST['medicine_name']);
    $category       = mysqli_real_escape_string($con, $_POST['category']);
    $supplier_id    = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : NULL;
    $quantity       = intval($_POST['quantity']);
    $used_quantity  = intval($_POST['used_quantity']);
    $expire_date    = $_POST['expire_date'];
    $company        = mysqli_real_escape_string($con, $_POST['company']);
    $actual_price   = floatval($_POST['actual_price']);
    $selling_price  = floatval($_POST['selling_price']);
    $status         = $_POST['status'];

    // Recalculate remain quantity
    $remain_quantity = $quantity - $used_quantity;
    if ($remain_quantity < 0) $remain_quantity = 0;
    
    // Auto-update status based on remaining quantity
    if ($remain_quantity <= 0) {
        $status = 'Out of Stock';
    } elseif ($status == 'Out of Stock' && $remain_quantity > 0) {
        $status = 'Available';
    }

    // Handle image upload
    $image_path = NULL;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Get current image to delete later
        $current_image_sql = "SELECT image_path FROM stock WHERE id = ?";
        $current_stmt = mysqli_prepare($con, $current_image_sql);
        mysqli_stmt_bind_param($current_stmt, 'i', $id);
        mysqli_stmt_execute($current_stmt);
        mysqli_stmt_bind_result($current_stmt, $current_image_path);
        mysqli_stmt_fetch($current_stmt);
        mysqli_stmt_close($current_stmt);
        
        // Delete old image if exists
        if ($current_image_path && file_exists('../../' . $current_image_path)) {
            unlink('../../' . $current_image_path);
        }
        
        $fileExtension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $medicine_name) . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($fileExtension), $allowedTypes)) {
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
                $image_path = 'uploads/products/' . $fileName;
            }
        }
    } else {
        // Keep existing image if no new upload
        $current_image_sql = "SELECT image_path FROM stock WHERE id = ?";
        $current_stmt = mysqli_prepare($con, $current_image_sql);
        mysqli_stmt_bind_param($current_stmt, 'i', $id);
        mysqli_stmt_execute($current_stmt);
        mysqli_stmt_bind_result($current_stmt, $current_image_path);
        mysqli_stmt_fetch($current_stmt);
        mysqli_stmt_close($current_stmt);
        $image_path = $current_image_path;
    }

    // Handle controlled substance update
    $is_controlled = isset($_POST['is_controlled']) && $_POST['is_controlled'];
    $schedule = isset($_POST['schedule']) ? mysqli_real_escape_string($con, $_POST['schedule']) : NULL;

    // Start transaction
    mysqli_begin_transaction($con);

    try {
        // Update stock table
        if ($image_path) {
            $sql = "UPDATE stock 
                    SET product_code=?, medicine_name=?, category=?, 
                        supplier_id=?, quantity=?, used_quantity=?, remain_quantity=?, 
                        expire_date=?, company=?, actual_price=?, selling_price=?, 
                        image_path=?, status=?
                    WHERE id=?";
            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($stmt, 'sssiiissddsssi', 
                $product_code, $medicine_name, $category, $supplier_id,
                $quantity, $used_quantity, $remain_quantity, $expire_date, $company,
                $actual_price, $selling_price, $image_path, $status, $id
            );
        } else {
            $sql = "UPDATE stock 
                    SET product_code=?, medicine_name=?, category=?, 
                        supplier_id=?, quantity=?, used_quantity=?, remain_quantity=?, 
                        expire_date=?, company=?, actual_price=?, selling_price=?, status=?
                    WHERE id=?";
            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($stmt, 'sssiiissddssi', 
                $product_code, $medicine_name, $category, $supplier_id,
                $quantity, $used_quantity, $remain_quantity, $expire_date, $company,
                $actual_price, $selling_price, $status, $id
            );
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to update drug: " . mysqli_error($con));
        }
        mysqli_stmt_close($stmt);

        // Handle controlled substances
        $controlled_check = mysqli_query($con, "SELECT id FROM controlled_substances WHERE product_id = $id");
        
        if ($is_controlled && $schedule) {
            if (mysqli_num_rows($controlled_check) > 0) {
                // Update existing controlled substance record
                $controlled_sql = "UPDATE controlled_substances SET schedule = ? WHERE product_id = ?";
                $controlled_stmt = mysqli_prepare($con, $controlled_sql);
                mysqli_stmt_bind_param($controlled_stmt, 'si', $schedule, $id);
            } else {
                // Insert new controlled substance record
                $controlled_sql = "INSERT INTO controlled_substances (product_id, schedule, created_at) VALUES (?, ?, NOW())";
                $controlled_stmt = mysqli_prepare($con, $controlled_sql);
                mysqli_stmt_bind_param($controlled_stmt, 'is', $id, $schedule);
            }
            mysqli_stmt_execute($controlled_stmt);
            mysqli_stmt_close($controlled_stmt);
        } else {
            // Remove from controlled substances if unchecked
            if (mysqli_num_rows($controlled_check) > 0) {
                mysqli_query($con, "DELETE FROM controlled_substances WHERE product_id = $id");
            }
        }

        // Log the update action
        $user_id = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'Unknown';
        $log_sql = "INSERT INTO logs (user_id, username, level, action, details, ip_address) 
                   VALUES (?, ?, 'INFO', 'DRUG_UPDATED', ?, ?)";
        $log_stmt = mysqli_prepare($con, $log_sql);
        $log_details = "Updated drug: $medicine_name (Batch: $product_code)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        mysqli_stmt_bind_param($log_stmt, 'isss', $user_id, $username, $log_details, $ip_address);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);

        // Commit transaction
        mysqli_commit($con);

        header("Location: ../view.php?msg=updated&product=" . urlencode($medicine_name));
        exit;

    } catch (Exception $e) {
        mysqli_rollback($con);
        error_log("Drug update error: " . $e->getMessage());
        header("Location: ../view.php?msg=error&error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: ../view.php");
    exit;
}
?>