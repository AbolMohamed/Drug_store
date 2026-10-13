<?php
/**
 * Cron Job Entry Point for Drug Store Scheduler
 * 
 * This file should be called by the system cron job
 */

// Set the working directory to the script's directory
chdir(dirname(__FILE__));

// Include the scheduler
require_once 'config/scheduler.php';

// Run the scheduler
$result = runScheduler();

// Log the result
logMessage("Cron job execution completed: " . json_encode($result));
?>