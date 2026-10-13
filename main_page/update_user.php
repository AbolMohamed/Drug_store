<?php
session_start();
require '../dbcon.php';

// Check if user is logged in
if(!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit();
}

// Get user ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch user data
$stmt = $con->prepare("SELECT * FROM tbl_users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if(!$user) {
    $_SESSION['message'] = "User not found";
    $_SESSION['showAlert'] = "block";
    header("location: manage_users.php");
    exit();
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $usertype = $_POST['usertype'];
    $status = $_POST['status'];
    
    // Validate inputs
    $errors = [];
    
    if(empty($name)) {
        $errors[] = "Full name is required";
    }
    
    if(empty($username)) {
        $errors[] = "Username is required";
    }
    
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if(empty($errors)) {
        // Check if username already exists (excluding current user)
        $check_stmt = $con->prepare("SELECT id FROM tbl_users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $errors[] = "Username already exists";
        } else {
            // Update user in database
            $stmt = $con->prepare("UPDATE tbl_users SET name=?, username=?, email=?, mobile=?, usertype=?, isActive=? WHERE id=?");
            $stmt->bind_param("ssssssi", $name, $username, $email, $mobile, $usertype, $status, $id);
            
            if($stmt->execute()) {
                $_SESSION['message'] = "User updated successfully";
                $_SESSION['showAlert'] = "block";
                header("location: manage_users.php");
                exit();
            } else {
                $errors[] = "Error updating user: " . $con->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>HDS Drug Store - Update User</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .error {
            color: #dc3545;
            margin-bottom: 15px;
        }
        body {
            background-color: #f5f5f5;
        }
        .container.mt-7 {
            margin-top: 70px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container mt-10">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="form-container">
                    <br>
                    <br>
                    <br>
                    <h5 class="text-center mb-4"><i class="fas fa-user-edit"></i> Update User</h5>
                    
                    <?php if(!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach($errors as $error): ?>
                                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="update_user.php?id=<?= $id ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="mobile">Mobile Number</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" value="<?= htmlspecialchars($user['mobile']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="usertype">User Type</label>
                            <select class="form-control" id="usertype" name="usertype" required>
                                <option value="Admin" <?= $user['usertype'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="Pharmacist" <?= $user['usertype'] == 'Pharmacist' ? 'selected' : '' ?>>Pharmacist</option>
                                <option value="Cashier" <?= $user['usertype'] == 'Cashier' ? 'selected' : '' ?>>Cashier</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="Active" <?= $user['isActive'] == 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $user['isActive'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-save"></i> Update User</button>
                            <a href="manage_users.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <br><br>
    <?php include 'footer.php'; ?>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>