<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../../login.php");
}
require_once '../../dbcon.php';

// Use only one functions file to avoid redeclaration errors
if (!function_exists('getSalesData')) {
    require_once 'analytics_functions_demo.php';
}

require_once 'config/telegram_config.php';

// Use server's default timezone instead of hardcoding
// date_default_timezone_set('Africa/Addis_Ababa'); // Removed to use server timezone

// Handle form submissions
$message = '';
$messageType = '';

if ($_POST) {
    if (isset($_POST['save_telegram_config'])) {
        $botToken = trim($_POST['bot_token']);
        $chatId = trim($_POST['chat_id']);
        
        if (empty($botToken) || empty($chatId)) {
            $message = "❌ Please provide both Bot Token and Chat ID";
            $messageType = 'danger';
        } else {
            if (saveTelegramConfig($botToken, $chatId)) {
                $message = "✅ Telegram configuration saved successfully!";
                $messageType = 'success';
                // Reload the page to show updated configuration
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = "❌ Failed to save configuration";
                $messageType = 'danger';
            }
        }
    }
    
    if (isset($_POST['test_connection'])) {
        $result = testTelegramConnection();
        if ($result['success']) {
            $message = "✅ Telegram connection successful! Bot name: " . $result['bot_name'];
            $messageType = 'success';
        } else {
            $message = "❌ Connection failed: " . $result['error'];
            $messageType = 'danger';
        }
    }
    
    if (isset($_POST['send_test_report'])) {
        $reportData = getDailyReportData($con, date('Y-m-d'));
        $telegramMessage = formatTelegramReport($reportData);
        $result = sendTelegramMessage($telegramMessage);
        
        if ($result['success']) {
            $message = "✅ Test report sent successfully!";
            $messageType = 'success';
        } else {
            $message = "❌ Failed to send report: " . $result['error'];
            $messageType = 'danger';
        }
    }
    
    if (isset($_POST['update_schedule'])) {
        $scheduleTime = $_POST['schedule_time'];
        $enabled = isset($_POST['schedule_enabled']) ? 1 : 0;
        
        // Save schedule settings to file or database
        $scheduleConfig = [
            'enabled' => $enabled,
            'time' => $scheduleTime,
            'last_run' => null
        ];
        
        // Ensure config directory exists
        $configDir = __DIR__ . '/config';
        if (!file_exists($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        file_put_contents($configDir . '/schedule_config.json', json_encode($scheduleConfig));
        $message = "✅ Schedule settings updated successfully!";
        $messageType = 'success';
    }
    
    if (isset($_POST['test_scheduler'])) {
        // Test the scheduler
        require_once 'config/scheduler.php';
        $result = runScheduler(true); // Force run
        
        if ($result['report']['success']) {
            $message = "✅ Scheduler test completed successfully!";
            $messageType = 'success';
        } else {
            $message = "❌ Scheduler test failed: " . ($result['report']['error'] ?? 'Unknown error');
            $messageType = 'danger';
        }
    }
}

// Load current schedule settings
$scheduleConfig = [];
$configFile = __DIR__ . '/config/schedule_config.json';
if (file_exists($configFile)) {
    $scheduleConfig = json_decode(file_get_contents($configFile), true);
}
$scheduleConfig = array_merge([
    'enabled' => false,
    'time' => '09:00',
    'last_run' => null
], $scheduleConfig);

// Calculate next run time using server timezone
$nextRun = '';
if ($scheduleConfig['enabled']) {
    $nextRun = date('Y-m-d') . ' ' . $scheduleConfig['time'] . ':00';
    if (time() >= strtotime($nextRun)) {
        $nextRun = date('Y-m-d', strtotime('+1 day')) . ' ' . $scheduleConfig['time'] . ':00';
    }
}

?>

<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="16x16" href="../../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HDS Drug Store - Pharmacy Management System">
  <title>Telegram Bot Configuration - Drug Store</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    .config-card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .section-header { background: linear-gradient(45deg, #667eea, #764ba2); color: white; border-radius: 8px 8px 0 0; }
    .status-online { color: #16a34a; }
    .status-offline { color: #dc2626; }
    pre { background-color: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
  </style>
</head>
<body class="bg-light">
<br>
<br>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="reports.php"><i class="bi bi-arrow-left"></i> Back to Reports</a>
    <div class="navbar-nav ms-auto">
      <span class="nav-link"><i class="bi bi-telegram"></i> Telegram Bot Configuration</span>
    </div>
  </div>
</nav>

<div class="container py-4">

  <?php if ($message): ?>
  <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Bot Configuration Form -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card config-card">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-gear"></i> Telegram Bot Configuration</h5>
        </div>
        <div class="card-body">
          <form method="POST">
            <div class="row">
              <div class="col-md-6">
                <h6><i class="bi bi-key"></i> Bot Credentials</h6>
                
                <div class="mb-3">
                  <label for="bot_token" class="form-label"><strong>Bot Token</strong></label>
                  <input type="text" class="form-control" id="bot_token" name="bot_token" 
                         value="<?= htmlspecialchars(TELEGRAM_BOT_TOKEN) ?>" 
                         placeholder="Enter your Telegram bot token"
                         required>
                  <small class="form-text text-muted">Get this from @BotFather on Telegram</small>
                </div>
                
                <div class="mb-3">
                  <label for="chat_id" class="form-label"><strong>Chat ID</strong></label>
                  <input type="text" class="form-control" id="chat_id" name="chat_id" 
                         value="<?= htmlspecialchars(TELEGRAM_CHAT_ID) ?>" 
                         placeholder="Enter your chat ID"
                         required>
                  <small class="form-text text-muted">Your personal chat ID or group chat ID</small>
                </div>
                
                <button type="submit" name="save_telegram_config" class="btn btn-primary">
                  <i class="bi bi-save"></i> Save Configuration
                </button>
              </div>
              
              <div class="col-md-6">
                <h6><i class="bi bi-info-circle"></i> Current Status</h6>
                <table class="table table-borderless">
                  <tr>
                    <td><strong>Bot Token:</strong></td>
                    <td><?= TELEGRAM_BOT_TOKEN ? '***' . substr(TELEGRAM_BOT_TOKEN, -10) : '<span class="text-danger">Not configured</span>' ?></td>
                  </tr>
                  <tr>
                    <td><strong>Chat ID:</strong></td>
                    <td><?= TELEGRAM_CHAT_ID ?: '<span class="text-danger">Not configured</span>' ?></td>
                  </tr>
                  <tr>
                    <td><strong>Status:</strong></td>
                    <td>
                      <span class="<?= (TELEGRAM_BOT_TOKEN && TELEGRAM_CHAT_ID) ? 'status-online' : 'status-offline' ?>">
                        <i class="bi bi-circle-fill"></i> 
                        <?= (TELEGRAM_BOT_TOKEN && TELEGRAM_CHAT_ID) ? 'Configured' : 'Not Configured' ?>
                      </span>
                    </td>
                  </tr>
                  <tr>
                    <td><strong>Server Timezone:</strong></td>
                    <td><?= date_default_timezone_get() ?></td>
                  </tr>
                </table>
                
                <h6><i class="bi bi-tools"></i> Test Actions</h6>
                <div class="d-grid gap-2">
                  <button type="submit" name="test_connection" class="btn btn-outline-primary" 
                          <?= !(TELEGRAM_BOT_TOKEN && TELEGRAM_CHAT_ID) ? 'disabled' : '' ?>>
                    <i class="bi bi-wifi"></i> Test Connection
                  </button>
        
                </div>
              </div>
            </div>
          </form>
          
          <?php if (!(TELEGRAM_BOT_TOKEN && TELEGRAM_CHAT_ID)): ?>
          <div class="alert alert-info mt-3">
            <h6><i class="bi bi-info-triangle"></i> Configuration Required</h6>
            <p>Please configure your Telegram bot credentials above to enable automated reporting.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Schedule Configuration -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card config-card">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-clock"></i> Automated Report Schedule</h5>
        </div>
        <div class="card-body">
          <form method="POST">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label"><strong>Schedule Time</strong> (Server Time: <?= date('h:i A') ?>)</label>
                <?php
                // Convert stored 24h time to 12h format for display if needed
                $displayTime = $scheduleConfig['time'];
                if (strpos($displayTime, ':') !== false) {
                  $timeParts = explode(':', $displayTime);
                  $hour = (int)$timeParts[0];
                  $minute = $timeParts[1];
                  $ampm = $hour >= 12 ? 'PM' : 'AM';
                  $hour12 = $hour % 12;
                  if ($hour12 == 0) $hour12 = 12;
                  $displayTime12h = sprintf('%02d:%02d', $hour12, $minute) . ' ' . $ampm;
                } else {
                  $displayTime12h = $displayTime;
                }
                ?>
                <input type="time" class="form-control" name="schedule_time" 
                       value="<?= $scheduleConfig['time'] ?>" required>
                <small class="form-text text-muted">
                  Current setting: <?= $displayTime12h ?> (will be stored as 24-hour format: <?= $scheduleConfig['time'] ?>)
                </small>
              </div>
              
              <div class="mb-3">
                <div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input" name="schedule_enabled" 
                         <?= $scheduleConfig['enabled'] ? 'checked' : '' ?>>
                  <label class="form-check-label">
                    <strong>Enable Automated Reports</strong>
                  </label>
                </div>
              </div>
              
              <button type="submit" name="update_schedule" class="btn btn-primary">
                <i class="bi bi-save"></i> Save Schedule
              </button>
              
              <button type="submit" name='test_scheduler' class="btn btn-outline-info ms-2">
                <i class="bi bi-play-circle"></i> Test Scheduler Now
              </button>
            </div>
            
            <div class="col-md-6">
              <h6><i class="bi bi-info-circle"></i> Schedule Status</h6>
              <table class="table table-borderless">
                <tr>
                  <td><strong>Status:</strong></td>
                  <td>
                    <span class="<?= $scheduleConfig['enabled'] ? 'status-online' : 'status-offline' ?>">
                      <i class="bi bi-circle-fill"></i> 
                      <?= $scheduleConfig['enabled'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                  </td>
                </tr>
                <tr>
                  <td><strong>Scheduled Time (24h):</strong></td>
                  <td><?= $scheduleConfig['time'] ?></td>
                </tr>
                <tr>
                  <td><strong>Scheduled Time (12h):</strong></td>
                  <td>
                    <?php
                    $timeParts = explode(':', $scheduleConfig['time']);
                    $hour = (int)$timeParts[0];
                    $minute = $timeParts[1];
                    $ampm = $hour >= 12 ? 'PM' : 'AM';
                    $hour12 = $hour % 12;
                    if ($hour12 == 0) $hour12 = 12;
                    echo sprintf('%02d:%02d', $hour12, $minute) . ' ' . $ampm;
                    ?>
                  </td>
                </tr>
                <tr>
                  <td><strong>Last Run:</strong></td>
                  <td><?= $scheduleConfig['last_run'] ? date('Y-m-d h:i A', strtotime($scheduleConfig['last_run'])) : 'Never' ?></td>
                </tr>
                <tr>
                  <td><strong>Next Run:</strong></td>
                  <td>
                    <?php if ($scheduleConfig['enabled']): ?>
                      <?php
                      $nextRunTime = strtotime($nextRun);
                      echo date('Y-m-d h:i A', $nextRunTime);
                      ?>
                    <?php else: ?>
                      <span class="text-muted">Not scheduled</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <td><strong>Server Time:</strong></td>
                  <td><?= date('Y-m-d h:i A') ?></td>
                </tr>
                <tr>
                  <td><strong>Server Timezone:</strong></td>
                  <td><?= date_default_timezone_get() ?></td>
                </tr>
                <tr>
                  <td><strong>Your Local Time:</strong></td>
                  <td>
                    <script>
                      document.write(new Date().toLocaleString());
                    </script>
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Setup Instructions -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card config-card">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-book"></i> Setup Instructions</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <h6>1. Create Telegram Bot</h6>
              <ol>
                <li>Open Telegram and search for <code>@BotFather</code></li>
                <li>Send <code>/newbot</code> command</li>
                <li>Follow instructions to create your bot</li>
                <li>Save the bot token provided</li>
              </ol>
              
              <h6>2. Get Chat ID</h6>
              <ol>
                <li>Send a message to your bot</li>
                <li>Visit: <code>https://api.telegram.org/bot&lt;YOUR_BOT_TOKEN&gt;/getUpdates</code></li>
                <li>Find the <code>chat.id</code> value in the response</li>
              </ol>
            </div>
            
            <div class="col-md-6">
              <h6>3. Configure Through Web Interface</h6>
              <p>Use the configuration form above to enter your:</p>
              <ul>
                <li><strong>Bot Token:</strong> The token you received from @BotFather</li>
                <li><strong>Chat ID:</strong> Your personal or group chat ID</li>
              </ul>
              <p>No need to edit any code files manually!</p>
              
              <h6>4. Setup Cron Job (Optional)</h6>
              <p>Add to your server's crontab for automatic scheduling:</p>
              <pre class="bg-light p-3 rounded"><code>0 9 * * * /usr/bin/php /path/to/scheduler.php</code></pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Report Preview -->
  <div class="row">
    <div class="col-12">
      <div class="card config-card">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-eye"></i> Daily Report Preview</h5>
        </div>
        <div class="card-body">
          <?php
          $previewData = getDailyReportData($con, date('Y-m-d'));
          $previewMessage = formatTelegramReport($previewData);
          ?>
          <h6>Report Content Preview:</h6>
          <div class="bg-light p-3 rounded" style="white-space: pre-wrap; font-family: monospace;">
<?= htmlspecialchars($previewMessage) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<br>
<br>
<br>
<?php include 'footer.php'; ?>

</body>
</html>