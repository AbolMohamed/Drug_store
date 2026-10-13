<?php
session_start();
require '../dbcon.php';

// Check if user is logged in
if(!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit();
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $usertype = $_POST['usertype'];
    
    // Validate inputs
    $errors = [];
    
    if(empty($name)) {
        $errors[] = "Full name is required";
    }
    
    if(empty($username)) {
        $errors[] = "Username is required";
    } else {
        // Check if username already exists
        $stmt = $con->prepare("SELECT id FROM tbl_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0) {
            $errors[] = "Username already exists";
        }
    }
    
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if(empty($password)) {
        $errors[] = "Password is required";
    } elseif(strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if(empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user into database
        $stmt = $con->prepare("INSERT INTO tbl_users (name, username, email, mobile, password, usertype, isActive, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW())");
        $stmt->bind_param("ssssss", $name, $username, $email, $mobile, $hashed_password, $usertype);
        
        if($stmt->execute()) {
            $_SESSION['message'] = "User added successfully";
            $_SESSION['showAlert'] = "block";
            header("location: manage_users.php");
            exit();
        } else {
            $errors[] = "Error adding user: " . $con->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>HDS Drug Store - Add New User</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include 'header.php' ?>
    <div class="container mt-7">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <br>
                        <br>
                        <br>
                        <h4 class="mb-0"><i class="fas fa-user-plus"></i> Add New User</h4>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach($errors as $error): ?>
                                    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action ="addUser.php">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="mobile">Mobile Number</label>
                                <input type="text" class="form-control" id="mobile" name="mobile">
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="usertype">User Type</label>
                                <select class="form-control" id="usertype" name="usertype" required>
                                    <option value="Admin">Admin</option>
                                    <option value="Pharmacist">Pharmacist</option>
                                    <option value="Cashier">Cashier</option>
                                </select>
                            </div>
                            
                            <div class="form-group text-center">
                                <button type="submit" class="btn btn-success mr-2"><i class="fas fa-save"></i> Save User</button>
                                <a href="manage_users.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<br>
<br>
    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Password strength indicator
        $('#password').on('input', function() {
            var password = $(this).val();
            var strength = 0;
            
            // Length check
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            
            // Character type checks
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[$@#&!]+/)) strength += 1;
            
            // Update strength bar
            var strengthPercent = (strength / 5) * 100;
            $('#passwordStrengthBar').css('width', strengthPercent + '%');
            
            // Update color based on strength
            if (strength <= 2) {
                $('#passwordStrengthBar').css('background-color', '#dc3545');
            } else if (strength <= 4) {
                $('#passwordStrengthBar').css('background-color', '#ffc107');
            } else {
                $('#passwordStrengthBar').css('background-color', '#28a745');
            }
        });
        
        // Form validation
        $('#passwordForm').submit(function() {
            var password = $('#password').val();
            var confirm_password = $('#confirm_password').val();
            
            if (password !== confirm_password) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        });
    });
    </script>
    <?php include 'footer.php' ?>
</body>
</html>