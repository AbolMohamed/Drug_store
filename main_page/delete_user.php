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

if($id > 0) {
    // First, check if the user exists
    $stmt = $con->prepare("SELECT username FROM tbl_users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if($user) {
        // Prevent deleting the current logged-in user
        if($_SESSION['user_id'] == $id) {
            $_SESSION['message'] = "You cannot delete your own account while logged in";
            $_SESSION['showAlert'] = "block";
            header("location: manage_users.php");
            exit();
        }
        
        // Delete the user
        $stmt = $con->prepare("DELETE FROM tbl_users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if($stmt->execute()) {
            $_SESSION['message'] = "User deleted successfully: " . $user['username'];
            $_SESSION['showAlert'] = "block";
        } else {
            $_SESSION['message'] = "Error deleting user: " . $con->error;
            $_SESSION['showAlert'] = "block";
        }
    } else {
        $_SESSION['message'] = "User not found";
        $_SESSION['showAlert'] = "block";
    }
}

header("location: manage_users.php");
exit();
?>