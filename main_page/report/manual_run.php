<?php
/**
 * Manual Scheduler Trigger
 * 
 * Use this to test the scheduler manually
 */

session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../../login.php");
    exit;
}

require_once '../../dbcon.php';
require_once 'config/scheduler.php';

echo "<h2>Manual Scheduler Trigger</h2>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// Run the scheduler
$result = runScheduler(true); // Force run

echo "<h3>Results:</h3>";
echo "<pre>" . print_r($result, true) . "</pre>";

// Show log file
$logFile = __DIR__ . '/logs/scheduler.log';
if (file_exists($logFile)) {
    echo "<h3>Log File Contents:</h3>";
    echo "<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
} else {
    echo "<p>Log file not found at: $logFile</p>";
}

echo '<br><br><a href="telegram_bot.php">Return to Telegram Bot Configuration</a>';
?>