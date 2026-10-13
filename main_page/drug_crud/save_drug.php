<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit;
}
include('../../dbcon.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic drug information
    $product_code   = mysqli_real_escape_string($con, $_POST['product_code']);
    $medicine_name  = mysqli_real_escape_string($con, $_POST['medicine_name']);
    $category       = mysqli_real_escape_string($con, $_POST['category']);
    $manufacturer   = mysqli_real_escape_string($con, $_POST['manufacturer']);
    $supplier_id    = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : NULL;
    $quantity       = (int)$_POST['quantity'];
    $used_quantity  = (int)$_POST['used_quantity'];
    $remain_quantity= $quantity - $used_quantity;
    $expire_date    = $_POST['expire_date'];
    $company        = mysqli_real_escape_string($con, $_POST['company']);
    $actual_price   = (float)$_POST['actual_price'];
    $selling_price  = (float)$_POST['selling_price'];
    $status         = $remain_quantity > 0 ? 'Available' : 'Out of Stock';
    
    // Handle image upload
    $image_path = NULL;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $medicine_name) . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;
        
        // Validate image type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($fileExtension), $allowedTypes)) {
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
                $image_path = 'uploads/products/' . $fileName;
            }
        }
    }

    // Insert into stock table
    $sql = "INSERT INTO stock 
        (product_code, medicine_name, category, manufacturer, supplier_id, quantity, 
         used_quantity, remain_quantity, expire_date, company, actual_price, 
         selling_price, image_path, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssiiiiissdss', 
        $product_code, $medicine_name, $category, $manufacturer, $supplier_id,
        $quantity, $used_quantity, $remain_quantity, $expire_date, $company,
        $actual_price, $selling_price, $image_path, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        $product_id = mysqli_insert_id($con);
        
        // Handle controlled substance registration if applicable
        if (isset($_POST['is_controlled']) && $_POST['is_controlled'] && !empty($_POST['schedule'])) {
            $schedule = mysqli_real_escape_string($con, $_POST['schedule']);
            
            $controlled_sql = "INSERT INTO controlled_substances 
                (product_id, schedule, created_at) 
                VALUES (?, ?, NOW())";
            
            $controlled_stmt = mysqli_prepare($con, $controlled_sql);
            mysqli_stmt_bind_param($controlled_stmt, 'is', $product_id, $schedule);
            mysqli_stmt_execute($controlled_stmt);
            mysqli_stmt_close($controlled_stmt);
        }
        
        mysqli_stmt_close($stmt);
        
        // Log the action
        $user_id = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'Unknown';
        $log_sql = "INSERT INTO logs (user_id, username, level, action, details, ip_address) 
                   VALUES (?, ?, 'INFO', 'DRUG_REGISTERED', ?, ?)";
        $log_stmt = mysqli_prepare($con, $log_sql);
        $log_details = "Registered drug: $medicine_name (Batch: $product_code)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        mysqli_stmt_bind_param($log_stmt, 'isss', $user_id, $username, $log_details, $ip_address);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
        
        header("Location: ../view.php?msg=added&product=" . urlencode($medicine_name));
        exit;
        
    } else {
        error_log("Drug registration error: " . mysqli_error($con));
        header("Location: ../view.php?msg=error&error=" . urlencode(mysqli_error($con)));
        exit;
    }
} else {
    header("Location: ../view.php");
    exit;
}
?>