<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

include('dbcon.php');

if ($_POST['action'] === 'estimate_clear') {
    $days = intval($_POST['days']);
    $sql = "SELECT COUNT(*) as estimated_count FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>