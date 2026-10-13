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
$stmt = $con->prepare("SELECT id, username, password FROM tbl_users WHERE id = ?");
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
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate inputs
    $errors = [];
    
    // Only require current password if admin is changing their own password
    if($_SESSION['user_id'] == $id && empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if(empty($new_password)) {
        $errors[] = "New password is required";
    } elseif(strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    // Verify current password if changing own password
    if($_SESSION['user_id'] == $id && !empty($current_password)) {
        if(!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
    }
    
    if(empty($errors)) {
        // Hash the new password
        $hashed_password = sha1($new_password); // Using SHA1 to match your login system
        
        // Update password in database
        $stmt = $con->prepare("UPDATE tbl_users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $id);
        
        if($stmt->execute()) {
            $_SESSION['message'] = "Password updated successfully for user: " . $user['username'];
            $_SESSION['showAlert'] = "block";
            header("location: manage_users.php");
            exit();
        } else {
            $errors[] = "Error updating password: " . $con->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>HDS Drug Store - Change Password</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .form-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="form-container">
                    <h5 class="text-center mb-12"><i class="fas fa-key"></i> Change Password</h5>
                    <p class="text-center text-muted mb-4">For user: <strong><?= htmlspecialchars($user['username']) ?></strong></p>
                    
                    <?php if(!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach($errors as $error): ?>
                                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="passwordForm" action="change_password.php?id=<?= $id ?>">
                        <?php if($_SESSION['user_id'] == $id): ?>
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <small class="form-text text-muted">Enter your current password</small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="password-strength mt-2">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <div class="password-requirements">
                                <small>Password must contain at least 6 characters</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-group text-center mt-4">
                            <button type="submit" class="btn btn-primary mr-3 px-4">
                                <i class="fas fa-save mr-2"></i>Change
                            </button>
                            <a href="manage_users.php" class="btn btn-secondary px-4">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Password strength indicator
        $('#new_password').on('input', function() {
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
        $('#passwordForm').submit(function(e) {
            var currentPass = $('#current_password').val();
            var newPass = $('#new_password').val();
            var confirmPass = $('#confirm_password').val();
            var isOwnAccount = <?= ($_SESSION['user_id'] == $id) ? 'true' : 'false' ?>;
            
            if (isOwnAccount && currentPass.length === 0) {
                alert('Please enter your current password');
                e.preventDefault();
                return false;
            }
            
            if (newPass.length < 6) {
                alert('Password must be at least 6 characters long');
                e.preventDefault();
                return false;
            }
            
            if (newPass !== confirmPass) {
                alert('New passwords do not match');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    });
    </script>
</body>
</html>