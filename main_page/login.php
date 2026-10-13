<?php
ob_start();
session_start();

// Error reporting
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
ini_set('display_errors', 0);

// Database connection
require_once("../dbcon.php");
require_once 'Log/functions.php';

// Initialize error message
$error_msg = "";

// Process login form
if (isset($_POST['submit'])) {
    // Sanitize inputs
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Check if fields are empty
    if (empty($username) || empty($password)) {
        $error_msg = "<div class='alert alert-danger'>Please enter both username and password</div>";
    } else {
        $sql = "SELECT id, username, password, usertype, isActive FROM tbl_users WHERE username = ?";
        $stmt = mysqli_prepare($con, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                $storedHash = $row['password'];
                $userId = $row['id'];

                // Check with password_verify (new users)
                $validPassword = password_verify($password, $storedHash);

                // If failed, try legacy sha1
                if (!$validPassword && sha1($password) === $storedHash) {
                    // Upgrade old hash to secure format
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $con->prepare("UPDATE tbl_users SET password = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $newHash, $userId);
                    $updateStmt->execute();
                    $validPassword = true;
                }

                if ($validPassword) {
                    if ($row['isActive'] === 'Active') {
                        $_SESSION['user_session'] = $row['username'];
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['user_type'] = $row['usertype'];
                        $_SESSION['invoice_number'] = "CA-" . generate_invoice_number();

switch ($row['usertype']) {
    case 'Admin':
        header("Location: salling.php");
        break;
    case 'Pharmacist':
        header("Location: pharmacist/salling.php");
        break;
    case 'Manager':
        header("Location: manager/salling.php");
        break;
    case 'Cashier':
        header("Location: cashier/cashier.php");
        break;
    default:
        header("Location: dashboard.php");
        break;
}
                        exit();
                    } else {
                        $error_msg = "<div class='alert alert-warning'>Your account is not active</div>";
                    }
                } else {
                    $error_msg = "<div class='alert alert-danger'>Incorrect username or password</div>";
                }
            } else {
                $error_msg = "<div class='alert alert-danger'>User not found</div>";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "<div class='alert alert-danger'>Database error. Please try again later.</div>";
        }
    }
}

// Invoice number generator
function generate_invoice_number() {
    return str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>H~D~S Drug Store - Login</title>
    <link rel="icon" type="image/png" sizes="16x16" href="image/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap-theme.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <style>
        body {
            background: url('../images/home.jpg') no-repeat center center fixed;
            background-size: cover;
            padding-top: 0;
            margin: 0;
            font-family: Arial, sans-serif;
        }

        .inner-box {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .forms-wrap {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo img {
            max-width: 100px;
            border-radius: 50%;
            border: 3px solid #58be40;
        }

        .heading {
            text-align: center;
            margin-bottom: 20px;
        }

        .login-form .form-control {
            height: 45px;
            width: 100%;
            margin-bottom: 15px;
        }

        .btn-login {
            background-color: #58be40;
            color: white;
            border: none;
            padding: 12px;
            font-size: 16px;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-login:hover {
            background-color: #4aa62f;
        }

        .social-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .social-links a {
            color: #58be40;
            margin: 0 8px;
            font-size: 20px;
            transition: color 0.3s;
        }

        .social-links a:hover {
            color: #4aa62f;
        }

        .alert {
            margin-top: 15px;
        }
    </style>
</head>
<body>

<div class="inner-box">
    <div class="forms-wrap">
        <form method="POST" class="login-form" autocomplete="off">
            <div class="logo">
                <img src="../images/logo.jpg" alt="H~D~S Drug Store">
                <h4>H~D~S Drug Store</h4>
            </div>

            <div class="heading">
                <h2>እንኳን ደህና መጡ!!</h2>
            </div>

            <?php if (!empty($error_msg)) echo $error_msg; ?>

            <div class="form-group">
                <input type="text" class="form-control" name="username" placeholder="Enter Your Username" required>
            </div>

            <div class="form-group">
                <input type="password" class="form-control" name="password" placeholder="Enter Your Password" required>
            </div>

            <button type="submit" name="submit" class="btn btn-login">
                <i class="fa fa-sign-in"></i> Login
            </button>
            <div class="social-links">
                <h4 style="color: #05853aff"><strong>Developed By: Awel Mohamed<strong></h4>
                <h5 style="color: #58be40">Follow us:</h5>
                <a href="https://www.facebook.com/" target="_blank"><i class="fa fa-facebook-square"></i></a>
                <a href="https://twitter.com/akremmohamed1993" target="_blank"><i class="fa fa-twitter-square"></i></a>
                <a href="https://twitter.com/akremtiw" target="_blank"><i class="fa fa-pinterest-square"></i></a>
                <a href="https://www.linkedin.com/in/akremmohamedoumer/" target="_blank"><i class="fa fa-linkedin-square"></i></a>
                <a href="https://www.telegram.org/@akrem123z" target="_blank"><i class="fa fa-telegram"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"></script>

</body>
</html>
<?php ob_end_flush(); ?>
