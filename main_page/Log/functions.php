<?php

/**
 * Adds a new entry to the logs table.
 *
 * @param mysqli $con The database connection object.
 * @param string $action A short description of the action (e.g., 'Login', 'Create Order').
 * @param string $details A more detailed message about the event.
 * @param string $level The severity level ('INFO', 'WARNING', 'ERROR', 'CRITICAL').
 */
function add_log(mysqli $con, string $action, string $details, string $level = 'INFO') {
    // Return early if there's no active session for user-specific logs
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $user_id = $_SESSION['user_id'];
    // Assuming 'username' is stored in the session upon login
    $username = $_SESSION['username'] ?? 'N/A';
    $ip_address = $_SERVER['REMOTE_ADDR'];

    $stmt = $con->prepare("INSERT INTO logs (user_id, username, action, details, level, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $username, $action, $details, $level, $ip_address);
    $stmt->execute();
    $stmt->close();
}
?>