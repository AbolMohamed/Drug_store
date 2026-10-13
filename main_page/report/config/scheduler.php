<?php
/**
 * Drug Store Report Scheduler
 * 
 * This script handles automated report generation and delivery.
 * It can be run via cron job or as a standalone script.
 * 
 * Usage:
 * - Run via cron: 0 9 * * * /usr/bin/php /path/to/scheduler.php
 * - Run manually: php scheduler.php
 * - Run with specific date: php scheduler.php --date=2025-08-24
 */

// Include required files
require_once __DIR__ . '/../../../dbcon.php';
// Set Ethiopian timezone
date_default_timezone_set('Africa/Addis_Ababa');

// Configuration - FIXED LOG DIRECTORY HANDLING
$logDir = __DIR__ . '/logs/';
$logFile = $logDir . 'scheduler.log';
$configFile = __DIR__ . '/config/schedule_config.json';
if (!function_exists('getSalesData')) {
    require_once '../analytics_functions_demo.php';
}
    require_once __DIR__ . '/telegram_config.php';

// Configuration - FIXED LOG DIRECTORY HANDLING
$logDir = __DIR__ . '/logs/';
$logFile = $logDir . 'scheduler.log';
$configFile = __DIR__ . '/config/schedule_config.json';

// Ensure logs directory exists with proper error handling
if (!file_exists($logDir)) {
    if (!mkdir($logDir, 0755, true)) {
        // Fallback to system temp directory if we can't create the logs directory
        $logDir = sys_get_temp_dir() . '/drugstore_scheduler_logs/';
        $logFile = $logDir . 'scheduler.log';
        if (!file_exists($logDir) && !mkdir($logDir, 0755, true)) {
            // Ultimate fallback - use system temp directly
            $logDir = sys_get_temp_dir() . '/';
            $logFile = $logDir . 'drugstore_scheduler.log';
        }
    }
}

// Ensure config directory exists
$configDir = dirname($configFile);
if (!file_exists($configDir)) {
    mkdir($configDir, 0755, true);
}

/**
 * Log message with timestamp
 */
function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    try {
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // If logging fails, output to error log as fallback
        error_log("Scheduler log error: " . $e->getMessage());
    }
    
    // Also output to console if running in CLI
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

/**
 * Load schedule configuration
 */
function loadScheduleConfig() {
    global $configFile;
    
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if ($config) {
            return array_merge([
                'enabled' => false,
                'time' => '09:00',
                'last_run' => null
            ], $config);
        }
    }
    
    return [
        'enabled' => false,
        'time' => '09:00',
        'last_run' => null
    ];
}

/**
 * Save schedule configuration
 */
function saveScheduleConfig($config) {
    global $configFile;
    
    // Ensure config directory exists
    $configDir = dirname($configFile);
    if (!file_exists($configDir)) {
        mkdir($configDir, 0755, true);
    }
    
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
}

/**
 * Check if report should be sent - IMPROVED LOGIC
 */
function shouldSendReport($config, $targetDate = null) {
    if (!$config['enabled']) {
        logMessage("Scheduler is disabled in configuration");
        return false;
    }
    
    if (!$targetDate) {
        $targetDate = date('Y-m-d');
    }
    
    $currentTime = time();
    $scheduledTime = strtotime($targetDate . ' ' . $config['time'] . ':00');
    
    // Debug information
    logMessage("Current time: " . date('Y-m-d H:i:s', $currentTime));
    logMessage("Scheduled time: " . date('Y-m-d H:i:s', $scheduledTime));
    logMessage("Last run: " . ($config['last_run'] ? $config['last_run'] : 'Never'));
    
    // Check if current time is past scheduled time for today
    if ($currentTime >= $scheduledTime) {
        // Check if we haven't run today yet
        if (!$config['last_run'] || date('Y-m-d', strtotime($config['last_run'])) !== $targetDate) {
            logMessage("Should send report: YES - It's past scheduled time and not run today");
            return true;
        } else {
            logMessage("Should send report: NO - Already ran today at " . $config['last_run']);
        }
    } else {
        logMessage("Should send report: NO - Not yet time (current < scheduled)");
    }
    
    return false;
}

/**
 * Generate and send daily report
 */
function generateAndSendReport($con, $reportDate = null) {
    try {
        if (!$reportDate) {
            $reportDate = date('Y-m-d');
        }
        
        logMessage("Generating daily report for $reportDate");
        
        // Generate report data
        $reportData = getDailyReportData($con, $reportDate);
        
        // Format for Telegram
        $telegramMessage = formatTelegramReport($reportData);
        
        // Send via Telegram
        $result = sendTelegramMessage($telegramMessage);
        
        if ($result['success']) {
            logMessage("Daily report sent successfully via Telegram");
            return ['success' => true, 'message' => 'Report sent successfully'];
        } else {
            logMessage("Failed to send report via Telegram: " . $result['error'], 'ERROR');
            return ['success' => false, 'error' => $result['error']];
        }
        
    } catch (Exception $e) {
        logMessage("Exception while generating report: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send critical alerts if any
 */
function sendCriticalAlerts($con) {
    try {
        // Get critical stock and expiration alerts
        $lowStock = getLowStockItems($con, 5); // Only critical items (<=5)
        $expiredItems = getExpirationAnalysis($con, 7); // Items expiring in 7 days
        
        $criticalExpired = array_filter($expiredItems, function($item) {
            return $item['days_remaining'] < 0; // Already expired
        });
        
        $criticalExpiring = array_filter($expiredItems, function($item) {
            return $item['days_remaining'] >= 0 && $item['days_remaining'] <= 3; // Expiring in 3 days
        });
        
        if (empty($lowStock) && empty($criticalExpired) && empty($criticalExpiring)) {
            return ['success' => true, 'message' => 'No critical alerts'];
        }
        
        $alertMessage = "🚨 *CRITICAL DRUG STORE ALERTS*\n\n";
        
        if (!empty($criticalExpired)) {
            $alertMessage .= "❌ *EXPIRED ITEMS (" . count($criticalExpired) . "):*\n";
            foreach (array_slice($criticalExpired, 0, 5) as $item) {
                $daysAgo = abs($item['days_remaining']);
                $alertMessage .= "• " . $item['medicine_name'] . " (expired $daysAgo days ago)\n";
            }
            $alertMessage .= "\n";
        }
        
        if (!empty($criticalExpiring)) {
            $alertMessage .= "⏰ *EXPIRING SOON (" . count($criticalExpiring) . "):*\n";
            foreach (array_slice($criticalExpiring, 0, 5) as $item) {
                $days = $item['days_remaining'];
                $alertMessage .= "• " . $item['medicine_name'] . " ($days days left)\n";
            }
            $alertMessage .= "\n";
        }
        
        if (!empty($lowStock)) {
            $alertMessage .= "📦 *CRITICAL LOW STOCK (" . count($lowStock) . "):*\n";
            foreach (array_slice($lowStock, 0, 5) as $item) {
                $alertMessage .= "• " . $item['medicine_name'] . " (" . $item['remain_quantity'] . " left)\n";
            }
            $alertMessage .= "\n";
        }
        
        $alertMessage .= "_Immediate attention required!_\n";
        $alertMessage .= "📱 " . date('Y-m-d H:i:s');
        
        $result = sendTelegramMessage($alertMessage);
        
        if ($result['success']) {
            logMessage("Critical alerts sent successfully");
            return ['success' => true, 'message' => 'Critical alerts sent'];
        } else {
            logMessage("Failed to send critical alerts: " . $result['error'], 'ERROR');
            return ['success' => false, 'error' => $result['error']];
        }
        
    } catch (Exception $e) {
        logMessage("Exception while sending critical alerts: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Main scheduler execution
 */
function runScheduler($forceRun = false, $targetDate = null) {
    global $con;
    
    logMessage("Scheduler started - Ethiopian Timezone: " . date_default_timezone_get());
    
    // Load configuration
    $config = loadScheduleConfig();
    
    if ($forceRun || shouldSendReport($config, $targetDate)) {
        logMessage("Sending scheduled report");
        
        // Generate and send report
        $reportResult = generateAndSendReport($con, $targetDate);
        
        if ($reportResult['success']) {
            // Update last run time
            $config['last_run'] = date('Y-m-d H:i:s');
            saveScheduleConfig($config);
            logMessage("Report sent successfully, updated last run time");
        }
        
        // Send critical alerts separately
        $alertResult = sendCriticalAlerts($con);
        
        return [
            'report' => $reportResult,
            'alerts' => $alertResult
        ];
    } else {
        $message = $config['enabled'] ? "Not time to send report yet" : "Scheduler is disabled";
        logMessage($message);
        return [
            'report' => ['success' => true, 'message' => $message],
            'alerts' => ['success' => true, 'message' => 'No alerts checked']
        ];
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $options = getopt('', ['date:', 'force', 'alerts-only', 'help']);
    
    if (isset($options['help'])) {
        echo "Drug Store Report Scheduler\n\n";
        echo "Usage: php scheduler.php [options]\n\n";
        echo "Options:\n";
        echo "  --date=YYYY-MM-DD    Generate report for specific date\n";
        echo "  --force              Force run regardless of schedule\n";
        echo "  --alerts-only        Send only critical alerts\n";
        echo "  --help               Show this help message\n\n";
        echo "Current Ethiopian Time: " . date('Y-m-d H:i:s') . "\n";
        exit(0);
    }
    
    $targetDate = isset($options['date']) ? $options['date'] : null;
    $forceRun = isset($options['force']);
    $alertsOnly = isset($options['alerts-only']);
    
    if ($alertsOnly) {
        logMessage("Sending critical alerts only");
        $result = sendCriticalAlerts($con);
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        $result = runScheduler($forceRun, $targetDate);
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    }
    
    logMessage("Scheduler finished");
}

// Web execution (for testing)
if (php_sapi_name() !== 'cli' && isset($_GET['run'])) {
    header('Content-Type: application/json');
    
    $forceRun = isset($_GET['force']);
    $targetDate = isset($_GET['date']) ? $_GET['date'] : null;
    
    $result = runScheduler($forceRun, $targetDate);
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}