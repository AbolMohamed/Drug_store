<?php
session_start();
require '../dbcon.php';

// Check if user is logged in and has appropriate permissions
// Add your authentication logic here

if(isset($_GET['file'])) {
    $backup_dir = __DIR__ . '/../../secure_backups/';
    $file_name = basename($_GET['file']);
    $file_path = $backup_dir . $file_name;
    
    // Validate file exists and is a .sql file
    if(file_exists($file_path) && pathinfo($file_path, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        $_SESSION['message'] = "File not found or invalid file type";
        $_SESSION['showAlert'] = "block";
        $_SESSION['alertClass'] = "alert-danger";
        header("location: backup.php");
        exit();
    }
} else {
    header("location: backup.php");
    exit();
}
?>